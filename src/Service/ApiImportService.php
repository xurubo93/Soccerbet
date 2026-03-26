<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * API-agnostischer Import-Service für Teams und Spiele.
 *
 * Nutzt ApiClientFactory um automatisch den konfigurierten Client
 * (OpenLigaDB oder football-data.org) zu verwenden.
 */
final class ApiImportService implements ApiImportInterface {

  public function __construct(
    private readonly Connection $db,
    private readonly ApiClientFactory $clientFactory,
    private readonly TeamFlagResolver $flagResolver,
    private readonly TeamNameTranslator $nameTranslator,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  // ================================================================== //
  // ApiImportInterface                                                   //
  // ================================================================== //

  public function getApiName(): string {
    return $this->clientFactory->getClient()->getLabel();
  }

  public function getLeagueHelp(): string {
    return match ($this->clientFactory->getActiveProvider()) {
      ApiClientFactory::PROVIDER_FOOTBALLDATA =>
        'Competition-Code z.B. <code>BL1</code> (Bundesliga), <code>EC</code> (EM), <code>WC</code> (WM), <code>CL</code> (Champions League)',
      default =>
        'Z.B. <code>bl1</code> (Bundesliga), <code>em2024</code>, <code>wm2022</code>',
    };
  }

  public function getSeasonHelp(): string {
    return match ($this->clientFactory->getActiveProvider()) {
      ApiClientFactory::PROVIDER_FOOTBALLDATA =>
        'Jahr z.B. <code>2024</code> (= Saison 2024/25). Bei EM/WM das Turnierjahr.',
      default =>
        'Z.B. <code>2024</code>. Bei Turnieren oft gleich wie Liga-Kürzel.',
    };
  }

  /**
   * Importiert Teams und Spiele via konfiguriertem API-Client.
   *
   * @param bool $group_only  TRUE = nur Gruppenspiele importieren (keine KO-Spiele)
   */
  public function importAll(int $tournament_id, string $league, string $season, bool $group_only = TRUE): array {
    $stats = [
      'teams_created'  => 0,
      'teams_skipped'  => 0,
      'teams_no_flag'  => [],
      'games_created'  => 0,
      'games_skipped'  => 0,
      'games_ko_skip'  => 0,
      'errors'         => [],
    ];

    $client  = $this->clientFactory->getClient();

    // Bei football-data.org: direkt stage=GROUP_STAGE filtern (spart API-Calls)
    // Bei OpenLigaDB: stage wird ignoriert, filtern wir nachträglich
    $stage = $group_only && $this->clientFactory->getActiveProvider() === ApiClientFactory::PROVIDER_FOOTBALLDATA
      ? 'GROUP_STAGE'
      : '';

    $matches = $client->getMatches($league, $season, $stage);

    if (empty($matches)) {
      $stats['errors'][] = sprintf(
        'Keine Spiele von %s für %s/%s erhalten.',
        $client->getLabel(), $league, $season
      );
      return $stats;
    }

    // OpenLigaDB: KO-Spiele nachträglich herausfiltern
    if ($group_only && $stage === '') {
      $matches_filtered = [];
      foreach ($matches as $match) {
        if ($this->detectPhase($match) === 'group') {
          $matches_filtered[] = $match;
        }
        else {
          $stats['games_ko_skip']++;
        }
      }
      $matches = $matches_filtered;
    }

    if (empty($matches)) {
      $stats['errors'][] = 'Keine Gruppenspiele gefunden. Prüfe Liga-Code und Saison.';
      return $stats;
    }

    $team_map = $this->importTeams($tournament_id, $matches, $stats);
    $this->importGames($tournament_id, $matches, $team_map, $stats);

    $this->logger()->info(
      'API-Import (@api) Turnier @tid (@league/@season): @tc Teams, @gc Spiele@ko.',
      [
        '@api'    => $client->getLabel(),
        '@tid'    => $tournament_id,
        '@league' => $league,
        '@season' => $season,
        '@tc'     => $stats['teams_created'],
        '@gc'     => $stats['games_created'],
        '@ko'     => $group_only ? ', KO-Spiele übersprungen' : '',
      ]
    );

    return $stats;
  }

  // ================================================================== //
  // Private Hilfsmethoden                                               //
  // ================================================================== //

  /**
   * Legt Teams an. Gibt external_id → local team_id Map zurück.
   *
   * @return array<int, int>
   */
  private function importTeams(int $tournament_id, array $matches, array &$stats): array {
    $now = \Drupal::time()->getRequestTime();
    $uid = (int) $this->currentUser->id();

    // Eindeutige Teams aus Matches extrahieren
    $api_teams = [];
    foreach ($matches as $match) {
      foreach ([
        ['id' => $match['team1_id'], 'name' => $match['team1_name'], 'flag' => $match['team1_flag']],
        ['id' => $match['team2_id'], 'name' => $match['team2_name'], 'flag' => $match['team2_flag']],
      ] as $t) {
        if ($t['id'] > 0 && !isset($api_teams[$t['id']])) {
          $api_teams[$t['id']] = $t;
        }
      }
    }

    // Bestehende Teams des Turniers laden (name → id)
    $existing = $this->db->select('soccerbet_teams', 't')
      ->fields('t', ['team_id', 'team_name'])
      ->condition('t.tournament_id', $tournament_id)
      ->execute()->fetchAllAssoc('team_name');

    $team_map = [];

    foreach ($api_teams as $ext_id => $api_team) {
      $name = trim($api_team['name']);
      if ($name === '') {
        continue;
      }

      // Teamnamen übersetzen (z.B. "Germany" → "Deutschland")
      $name = $this->nameTranslator->translate($name);

      if (isset($existing[$name])) {
        $team_map[$ext_id] = (int) $existing[$name]->team_id;
        $stats['teams_skipped']++;
        continue;
      }

      // Flag: use code from API if available (already alpha-3 uppercase),
      // otherwise fall back to name-based resolver (OpenLigaDB has no area codes).
      $flag = $api_team['flag'] ?? '';
      if ($flag === '') {
        $flag = $this->flagResolver->resolve($name);
      }

      $team_id = (int) $this->db->insert('soccerbet_teams')
        ->fields([
          'tournament_id' => $tournament_id,
          'team_name'     => $name,
          'team_flag'     => $flag,
          'team_group'    => '',
          'games_played'  => 0,
          'games_won'     => 0,
          'games_drawn'   => 0,
          'games_lost'    => 0,
          'goals_shot'    => 0,
          'goals_got'     => 0,
          'points'        => 0,
          'uid'           => $uid,
          'created'       => $now,
          'changed'       => $now,
        ])
        ->execute();

      $team_map[$ext_id] = $team_id;
      $stats['teams_created']++;
      if ($flag === '') {
        $stats['teams_no_flag'][] = $name;
      }
    }

    return $team_map;
  }

  /**
   * Legt Spiele an.
   */
  private function importGames(
    int $tournament_id,
    array $matches,
    array $team_map,
    array &$stats,
  ): void {
    $now = \Drupal::time()->getRequestTime();
    $uid = (int) $this->currentUser->id();

    foreach ($matches as $match) {
      $team1_id = $team_map[$match['team1_id']] ?? NULL;
      $team2_id = $team_map[$match['team2_id']] ?? NULL;

      if (!$team1_id || !$team2_id) {
        $stats['errors'][] = sprintf(
          'Team nicht gefunden für Match %d (%s vs %s)',
          $match['external_id'] ?? 0,
          $match['team1_name'] ?? '?',
          $match['team2_name'] ?? '?',
        );
        $stats['games_skipped']++;
        continue;
      }

      if (empty($match['date_utc'])) {
        $stats['errors'][] = sprintf('Ungültiges Datum für Match %d', $match['external_id'] ?? 0);
        $stats['games_skipped']++;
        continue;
      }

      // Doppelte Spiele vermeiden
      $exists = (bool) $this->db->select('soccerbet_games', 'g')
        ->condition('g.tournament_id', $tournament_id)
        ->condition('g.team_id_1', $team1_id)
        ->condition('g.team_id_2', $team2_id)
        ->countQuery()->execute()->fetchField();

      if ($exists) {
        $stats['games_skipped']++;
        continue;
      }

      // Phase aus stage/group_name ableiten
      $phase      = $this->detectPhase($match);
      $team_group = $this->extractTeamGroup($match['group_name'] ?? '');

      // Team-Gruppe setzen wenn Gruppenphase
      if ($team_group !== '' && $phase === 'group') {
        foreach ([$team1_id, $team2_id] as $tid) {
          $this->db->update('soccerbet_teams')
            ->fields(['team_group' => $team_group])
            ->condition('team_id', $tid)
            ->condition('team_group', '')
            ->execute();
        }
      }

      $this->db->insert('soccerbet_games')
        ->fields([
          'tournament_id' => $tournament_id,
          'team_id_1'     => $team1_id,
          'team_id_2'     => $team2_id,
          'game_date'     => $match['date_utc'],
          'game_location' => '',
          'game_stadium'  => $match['stadium'] ?? '',
          'phase'         => $phase,
          'team1_score'   => $match['is_finished'] ? $match['score1'] : NULL,
          'team2_score'   => $match['is_finished'] ? $match['score2'] : NULL,
          'api_id'        => ($match['external_id'] ?? 0) ?: NULL,
          'published'     => 1,
          'uid'           => $uid,
          'created'       => $now,
          'changed'       => $now,
        ])
        ->execute();

      $stats['games_created']++;
    }
  }

  /**
   * Phase aus normalisierten Match-Daten ableiten.
   */
  private function detectPhase(array $match): string {
    // football-data.org liefert stage direkt
    $stage_map = [
      'GROUP_STAGE'    => 'group',
      'ROUND_OF_16'    => 'round_of_16',
      'QUARTER_FINALS' => 'quarter',
      'SEMI_FINALS'    => 'semi',
      'THIRD_PLACE'    => 'third_place',
      'FINAL'          => 'final',
    ];
    if (!empty($match['stage']) && isset($stage_map[$match['stage']])) {
      return $stage_map[$match['stage']];
    }

    // OpenLigaDB-Fallback: groupName analysieren
    $group_name = strtolower($match['group_name'] ?? '');
    if (str_contains($group_name, 'finale') && !str_contains($group_name, 'viertel') && !str_contains($group_name, 'halb') && !str_contains($group_name, 'achtel')) {
      return 'final';
    }
    if (str_contains($group_name, 'halbfinale') || str_contains($group_name, 'semi')) {
      return 'semi';
    }
    if (str_contains($group_name, 'viertelfinale') || str_contains($group_name, 'quarter')) {
      return 'quarter';
    }
    if (str_contains($group_name, 'achtelfinale') || str_contains($group_name, 'round of 16')) {
      return 'round_of_16';
    }
    if (str_contains($group_name, 'platz 3') || str_contains($group_name, 'third')) {
      return 'third_place';
    }
    return 'group';
  }

  /**
   * Gruppenbezeichnung (A-Z) aus groupName extrahieren.
   * Unterstützt beliebige Buchstaben – nicht nur A-H.
   */
  private function extractTeamGroup(string $group_name): string {
    // football-data.org: "GROUP_A", "GROUP_B", ..., "GROUP_L" etc.
    if (preg_match('/GROUP_([A-Z])/i', $group_name, $m)) {
      return strtoupper($m[1]);
    }
    // OpenLigaDB: "Gruppe A", "Group B", "Gruppe: C"
    if (preg_match('/\b(?:gruppe|group)\s*:?\s*([A-Z])\b/i', $group_name, $m)) {
      return strtoupper($m[1]);
    }
    // Fallback: einzelner Großbuchstabe am Wortende, z.B. "Stage A"
    if (preg_match('/\b([A-Z])\s*$/i', trim($group_name), $m)) {
      return strtoupper($m[1]);
    }
    return '';
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }
}
