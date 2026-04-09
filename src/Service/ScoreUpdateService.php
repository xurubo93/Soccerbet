<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Gleicht API-Daten (football-data.org) mit der lokalen DB ab.
 * Nutzt ApiClientFactory – arbeitet mit dem normalisierten getMatches()-Format.
 */
final class ScoreUpdateService {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $db,
    private readonly ApiClientFactory $clientFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  // ================================================================== //
  // Haupt-Entry-Points                                                   //
  // ================================================================== //

  /**
   * Aktualisiert alle aktiven Turniere.
   *
   * @return array{updated: int, skipped: int, errors: int}
   */
  public function updateAll(): array {
    $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

    $tournaments = $this->db->select('soccerbet_tournament', 't')
      ->fields('t')
      ->condition('t.is_active', 1)
      ->execute()->fetchAll();

    foreach ($tournaments as $tournament) {
      $league = $tournament->oldb_league ?? NULL;
      $season = $tournament->oldb_season ?? NULL;
      if (!$league || !$season) {
        $stats['skipped']++;
        continue;
      }
      try {
        $result = $this->updateTournament((int) $tournament->tournament_id, $league, $season);
        $stats['updated'] += $result['scores_updated'];
        $stats['skipped'] += $result['scores_skipped'];
      }
      catch (\Exception $e) {
        $stats['errors']++;
        $this->logger()->error('Fehler beim Update von Turnier @id: @msg', [
          '@id' => $tournament->tournament_id, '@msg' => $e->getMessage(),
        ]);
      }
    }

    return $stats;
  }

  /**
   * Aktualisiert ein einzelnes Turnier.
   *
   * @return array{scores_updated: int, scores_skipped: int, table_updated: bool}
   */
  public function updateTournament(int $tournament_id, string $league, string $season): array {
    $stats  = ['scores_updated' => 0, 'scores_skipped' => 0, 'table_updated' => FALSE];
    $client = $this->clientFactory->getClient();

    if (!$client->hasChangedSince($league, $season)) {
      $this->logger()->info('Turnier @id: keine API-Änderungen, übersprungen.', ['@id' => $tournament_id]);
      $stats['scores_skipped'] = 1;
      return $stats;
    }

    $api_matches = $client->getMatches($league, $season);
    if (empty($api_matches)) {
      $this->logger()->warning('API lieferte keine Spiele für @league/@season.', [
        '@league' => $league, '@season' => $season,
      ]);
      return $stats;
    }

    $local_games = $this->loadLocalGames($tournament_id);
    $local_teams = $this->loadLocalTeams($tournament_id);
    $team_map    = $this->buildTeamNameMap($local_teams);

    foreach ($api_matches as $match) {
      if (!($match['is_finished'] ?? FALSE)) {
        continue;
      }
      if ($match['score1'] === NULL || $match['score2'] === NULL) {
        continue;
      }

      // Lokales Spiel suchen: zuerst via api_id (exakt), dann via Team-Namen (fuzzy)
      $ext_id = (int) ($match['external_id'] ?? 0);
      $local_game = $ext_id > 0
        ? $this->findLocalGameByApiId($local_games, $ext_id)
        : NULL;

      if (!$local_game) {
        $team1_id = $this->resolveTeamId($match['team1_name'], $team_map);
        $team2_id = $this->resolveTeamId($match['team2_name'], $team_map);
        if (!$team1_id || !$team2_id) {
          $this->logger()->debug('Team nicht gefunden: "@t1" oder "@t2"', [
            '@t1' => $match['team1_name'], '@t2' => $match['team2_name'],
          ]);
          $stats['scores_skipped']++;
          continue;
        }
        $local_game = $this->findLocalGame($local_games, $team1_id, $team2_id);
      }
      if (!$local_game) {
        $stats['scores_skipped']++;
        continue;
      }

      $s1 = (int) $match['score1'];
      $s2 = (int) $match['score2'];

      if ((int) $local_game->team1_score === $s1 && (int) $local_game->team2_score === $s2) {
        $stats['scores_skipped']++;
        continue;
      }

      // Sieger bestimmen
      $winner_side = NULL;
      if ($s1 > $s2) {
        $winner_side = 'team1';
      }
      elseif ($s2 > $s1) {
        $winner_side = 'team2';
      }

      // Bei umgekehrter Spielreihenfolge Teams tauschen
      $game_team1_id = (int) $local_game->team_id_1;
      if ($game_team1_id === $team2_id) {
        // API liefert Heim/Auswärts anders als lokal gespeichert
        [$s1, $s2] = [$s2, $s1];
        $winner_side = match($winner_side) {
          'team1' => 'team2', 'team2' => 'team1', default => NULL,
        };
      }

      $this->saveScore((int) $local_game->game_id, $s1, $s2, $winner_side, $tournament_id);

      $this->logger()->info('Score aktualisiert: @t1 @s1:@s2 @t2 (Spiel #@gid)', [
        '@t1' => $match['team1_name'], '@s1' => $s1,
        '@s2' => $s2, '@t2' => $match['team2_name'],
        '@gid' => $local_game->game_id,
      ]);
      $stats['scores_updated']++;
    }

    // Tabelle aktualisieren
    $table = $client->getTable($league, $season);
    if (!empty($table)) {
      $this->updateTable($tournament_id, $table, $team_map);
      $stats['table_updated'] = TRUE;
    }

    $client->markAsSeen($league, $season);

    $this->logger()->info('Turnier @id Update: @u aktualisiert, @s übersprungen.', [
      '@id' => $tournament_id, '@u' => $stats['scores_updated'], '@s' => $stats['scores_skipped'],
    ]);

    return $stats;
  }

  // ================================================================== //
  // Private Hilfsmethoden                                                //
  // ================================================================== //

  private function saveScore(int $game_id, int $score1, int $score2, ?string $winner_side, int $tournament_id): void {
    $winner_team_id = NULL;
    if ($winner_side !== NULL) {
      $game = $this->db->select('soccerbet_games', 'g')
        ->fields('g', ['team_id_1', 'team_id_2'])
        ->condition('g.game_id', $game_id)
        ->execute()->fetchObject();
      $winner_team_id = match($winner_side) {
        'team1' => $game?->team_id_1 ? (int) $game->team_id_1 : NULL,
        'team2' => $game?->team_id_2 ? (int) $game->team_id_2 : NULL,
        default => NULL,
      };
    }

    $this->db->update('soccerbet_games')
      ->fields([
        'team1_score'    => $score1,
        'team2_score'    => $score2,
        'winner_team_id' => $winner_team_id,
        'changed'        => \Drupal::time()->getRequestTime(),
      ])
      ->condition('game_id', $game_id)
      ->execute();

    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_standings:' . $tournament_id]);
  }

  private function updateTable(int $tournament_id, array $api_table, array $team_map): void {
    foreach ($api_table as $row) {
      // Normalisiertes Format (ApiClientInterface)
      $team_name = $row['team_name'] ?? ($row['teamName'] ?? ($row['shortName'] ?? ''));
      $team_id   = $this->resolveTeamId($team_name, $team_map);
      if (!$team_id) {
        continue;
      }
      $this->db->update('soccerbet_teams')
        ->fields([
          'games_played' => (int) ($row['played']        ?? $row['matches']       ?? 0),
          'games_won'    => (int) ($row['won']            ?? 0),
          'games_drawn'  => (int) ($row['drawn']          ?? $row['draw']          ?? 0),
          'games_lost'   => (int) ($row['lost']           ?? 0),
          'goals_shot'   => (int) ($row['goals_for']      ?? $row['goals']         ?? 0),
          'goals_got'    => (int) ($row['goals_against']  ?? $row['opponentGoals'] ?? 0),
          'points'       => (int) ($row['points']         ?? 0),
          'changed'      => \Drupal::time()->getRequestTime(),
        ])
        ->condition('team_id', $team_id)
        ->execute();
    }
  }

  private function buildTeamNameMap(array $local_teams): array {
    $map = [];
    foreach ($local_teams as $team) {
      $map[$this->normalizeTeamName($team->team_name)] = (int) $team->team_id;
    }
    return $map;
  }

  private function resolveTeamId(string $api_name, array $team_map): ?int {
    if (empty($api_name)) return NULL;
    $normalized = $this->normalizeTeamName($api_name);
    if (isset($team_map[$normalized])) return $team_map[$normalized];
    foreach ($team_map as $local => $id) {
      if (str_contains($normalized, $local) || str_contains($local, $normalized)) return $id;
    }
    $best_id = NULL; $best_dist = PHP_INT_MAX;
    foreach ($team_map as $local => $id) {
      $dist = levenshtein($normalized, $local);
      if ($dist < $best_dist && $dist <= 4) { $best_dist = $dist; $best_id = $id; }
    }
    return $best_id;
  }

  private function normalizeTeamName(string $name): string {
    $name = mb_strtolower($name);
    $name = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $name);
    return preg_replace('/[^a-z0-9]/', '', $name);
  }

  private function loadLocalGames(int $tournament_id): array {
    return $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['game_id','team_id_1','team_id_2','team1_score','team2_score','winner_team_id','phase','api_id'])
      ->condition('g.tournament_id', $tournament_id)
      ->execute()->fetchAll();
  }

  private function loadLocalTeams(int $tournament_id): array {
    return $this->db->select('soccerbet_teams', 't')
      ->fields('t', ['team_id', 'team_name'])
      ->condition('t.tournament_id', $tournament_id)
      ->execute()->fetchAll();
  }

  private function findLocalGameByApiId(array $local_games, int $api_id): ?object {
    foreach ($local_games as $game) {
      if ((int) ($game->api_id ?? 0) === $api_id) {
        return $game;
      }
    }
    return NULL;
  }

  private function findLocalGame(array $local_games, int $team1_id, int $team2_id): ?object {
    foreach ($local_games as $game) {
      if ((int)$game->team_id_1 === $team1_id && (int)$game->team_id_2 === $team2_id) return $game;
      if ((int)$game->team_id_1 === $team2_id && (int)$game->team_id_2 === $team1_id) return $game;
    }
    return NULL;
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }
}
