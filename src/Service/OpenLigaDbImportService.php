<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Erstbefüllung von Teams und Spielen aus der OpenLigaDB API.
 *
 * Ablauf:
 *  1. getMatchData() liefert alle Spiele inkl. Team-Infos
 *  2. Teams aus den Spieldaten extrahieren und anlegen
 *  3. Spiele mit Datum, Teams und Gruppe anlegen
 */
final class OpenLigaDbImportService implements ApiImportInterface {

  public function __construct(
    private readonly Connection $db,
    private readonly OpenLigaDbClient $apiClient,
    private readonly TeamFlagResolver $flagResolver,
    private readonly TeamNameTranslator $nameTranslator,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Importiert Teams und Spiele für ein Turnier aus OpenLigaDB.
   *
   * @return array{
   *   teams_created: int,
   *   teams_skipped: int,
   *   games_created: int,
   *   games_skipped: int,
   *   errors: string[],
   * }
   */
  public function importAll(int $tournament_id, string $league, string $season): array {
    $stats = [
      'teams_created' => 0,
      'teams_skipped' => 0,
      'teams_no_flag' => [],
      'games_created' => 0,
      'games_skipped' => 0,
      'errors'        => [],
    ];

    $matches = $this->apiClient->getMatchData($league, $season);
    if (empty($matches)) {
      $stats['errors'][] = "Keine Spiele von OpenLigaDB für {$league}/{$season} erhalten.";
      return $stats;
    }

    // Teams extrahieren: oldb_team_id → team_name
    $api_teams = $this->extractTeams($matches);

    // Teams anlegen / mappen
    $team_map = $this->importTeams($tournament_id, $api_teams, $stats);

    // Spiele anlegen
    $this->importGames($tournament_id, $matches, $team_map, $stats);

    $this->logger()->info(
      'OpenLigaDB Import für Turnier @tid (@league/@season): @tc Teams, @gc Spiele importiert.',
      [
        '@tid'    => $tournament_id,
        '@league' => $league,
        '@season' => $season,
        '@tc'     => $stats['teams_created'],
        '@gc'     => $stats['games_created'],
      ]
    );

    return $stats;
  }

  /**
   * Extrahiert eindeutige Teams aus den Match-Daten.
   *
   * @return array<int, array{id: int, name: string, shortName: string}>
   */
  private function extractTeams(array $matches): array {
    $teams = [];
    foreach ($matches as $match) {
      foreach (['team1', 'team2'] as $side) {
        $t = $match[$side] ?? [];
        $id = (int) ($t['teamId'] ?? 0);
        if ($id > 0 && !isset($teams[$id])) {
          $teams[$id] = [
            'id'        => $id,
            'name'      => $t['teamName'] ?? '',
            'shortName' => $t['shortName'] ?? '',
            'iconUrl'   => $t['teamIconUrl'] ?? '',
          ];
        }
      }
    }
    return $teams;
  }

  /**
   * Legt Teams in der DB an. Bereits vorhandene (gleicher Name im Turnier)
   * werden übersprungen. Gibt oldb_team_id → local team_id Map zurück.
   *
   * @return array<int, int>
   */
  private function importTeams(int $tournament_id, array $api_teams, array &$stats): array {
    $now = \Drupal::time()->getRequestTime();
    $uid = (int) $this->currentUser->id();

    // Bestehende Teams des Turniers laden (name → id)
    $existing = $this->db->select('soccerbet_teams', 't')
      ->fields('t', ['team_id', 'team_name'])
      ->condition('t.tournament_id', $tournament_id)
      ->execute()->fetchAllAssoc('team_name');

    $team_map = []; // oldb_team_id → local team_id

    foreach ($api_teams as $oldb_id => $api_team) {
      $name = trim($api_team['name']);
      if ($name === '') {
        continue;
      }

      if (isset($existing[$name])) {
        // Team existiert bereits
        $team_map[$oldb_id] = (int) $existing[$name]->team_id;
        $stats['teams_skipped']++;
        continue;
      }

      // Neues Team anlegen
      $flag = $this->flagResolver->resolve($name);

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

      $team_map[$oldb_id] = $team_id;
      $stats['teams_created']++;
      if ($flag === '') {
        $stats['teams_no_flag'][] = $name;
      }
    }

    return $team_map;
  }

  /**
   * Legt Spiele in der DB an. Bereits vorhandene (gleiche Teams + Datum)
   * werden übersprungen.
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
      $oldb_team1_id = (int) ($match['team1']['teamId'] ?? 0);
      $oldb_team2_id = (int) ($match['team2']['teamId'] ?? 0);

      $team1_id = $team_map[$oldb_team1_id] ?? NULL;
      $team2_id = $team_map[$oldb_team2_id] ?? NULL;

      if (!$team1_id || !$team2_id) {
        $stats['errors'][] = sprintf(
          'Team nicht gefunden für Match %d (%s vs %s)',
          $match['matchId'] ?? 0,
          $match['team1']['teamName'] ?? '?',
          $match['team2']['teamName'] ?? '?',
        );
        $stats['games_skipped']++;
        continue;
      }

      // Datum parsen – API liefert Europe/Berlin
      $game_date_utc = $this->parseMatchDate($match['matchDateTime'] ?? '');
      if ($game_date_utc === '') {
        $stats['errors'][] = sprintf(
          'Ungültiges Datum für Match %d', $match['matchId'] ?? 0
        );
        $stats['games_skipped']++;
        continue;
      }

      // Prüfen ob Spiel bereits existiert (gleiche Teams + Turnier)
      $exists = (bool) $this->db->select('soccerbet_games', 'g')
        ->condition('g.tournament_id', $tournament_id)
        ->condition('g.team_id_1', $team1_id)
        ->condition('g.team_id_2', $team2_id)
        ->countQuery()->execute()->fetchField();

      if ($exists) {
        $stats['games_skipped']++;
        continue;
      }

      // Phase aus Gruppen-Info ableiten
      $phase = $this->detectPhase($match);

      // Team-Gruppe aus groupName extrahieren (nur bei Gruppenphase)
      // z.B. "Gruppe A" → "A", "Group B" → "B", "1. Spieltag" → ""
      $team_group = '';
      if ($phase === 'group') {
        $team_group = $this->extractTeamGroup($match['group']['groupName'] ?? '');
        // Gruppe an beiden Teams setzen wenn ermittelt
        if ($team_group !== '') {
          foreach ([$team1_id, $team2_id] as $tid) {
            $this->db->update('soccerbet_teams')
              ->fields(['team_group' => $team_group])
              ->condition('team_id', $tid)
              ->condition('team_group', '')  // Nur überschreiben wenn noch leer
              ->execute();
          }
        }
      }

      // Ergebnis wenn Spiel bereits gespielt
      $team1_score = NULL;
      $team2_score = NULL;
      if ($match['matchIsFinished'] ?? FALSE) {
        $score = $this->extractFinalScore($match);
        if ($score) {
          $team1_score = $score['team1'];
          $team2_score = $score['team2'];
        }
      }

      $this->db->insert('soccerbet_games')
        ->fields([
          'tournament_id' => $tournament_id,
          'team_id_1'     => $team1_id,
          'team_id_2'     => $team2_id,
          'game_date'     => $game_date_utc,
          'game_location' => '',
          'game_stadium'  => $match['location']['locationCity'] ?? '',
          'phase'         => $phase,
          'team1_score'   => $team1_score,
          'team2_score'   => $team2_score,
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
   * Extrahiert die Gruppenbezeichnung (A-Z) aus dem groupName.
   *
   * Beispiele:
   *   "Gruppe A"   → "A"
   *   "Group B"    → "B"
   *   "Gruppe C"   → "C"
   *   "1. Spieltag"→ ""  (kein Buchstabe → keine Gruppe)
   *   "Vorrunde"   → ""
   */
  private function extractTeamGroup(string $group_name): string {
    // Muster: "Gruppe X", "Group X", "Gruppe: X"
    if (preg_match('/\b(?:gruppe|group)\s*:?\s*([A-Z])\b/i', $group_name, $m)) {
      return strtoupper($m[1]);
    }
    // Fallback: einzelner Großbuchstabe am Ende, z.B. "Gruppe A" oder einfach "A"
    if (preg_match('/\b([A-H])\s*$/i', trim($group_name), $m)) {
      return strtoupper($m[1]);
    }
    return '';
  }

  /**
   * Leitet die Phase aus den Match-Daten ab.
   * OpenLigaDB hat kein einheitliches Phase-Feld – wir nutzen groupName.
   */
  private function detectPhase(array $match): string {
    $group_name = strtolower($match['group']['groupName'] ?? '');
    $group_order = (int) ($match['group']['groupOrderID'] ?? 0);

    // KO-Phasen erkennen
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
   * Konvertiert ein OpenLigaDB-Datum (Europe/Berlin) nach UTC-ISO-String.
   */
  private function parseMatchDate(string $raw): string {
    if ($raw === '') {
      return '';
    }
    try {
      $dt = new \DateTimeImmutable($raw, new \DateTimeZone('Europe/Berlin'));
      return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }
    catch (\Exception) {
      return '';
    }
  }

  /**
   * Extrahiert das Endergebnis aus matchResults (resultTypeID = 2).
   *
   * @return array{team1: int, team2: int}|null
   */
  private function extractFinalScore(array $match): ?array {
    foreach ($match['matchResults'] ?? [] as $result) {
      if ((int) ($result['resultTypeID'] ?? 0) === 2) {
        return [
          'team1' => (int) $result['pointsTeam1'],
          'team2' => (int) $result['pointsTeam2'],
        ];
      }
    }
    // Fallback: letztes Ergebnis
    $last = end($match['matchResults'] ?? []);
    if ($last) {
      return [
        'team1' => (int) $last['pointsTeam1'],
        'team2' => (int) $last['pointsTeam2'],
      ];
    }
    return NULL;
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }

  public function getApiName(): string {
    return 'OpenLigaDB';
  }

  public function getLeagueHelp(): string {
    return 'Z.B. <code>bl1</code> (Bundesliga), <code>em2024</code>, <code>wm2022</code>';
  }

  public function getSeasonHelp(): string {
    return 'Z.B. <code>2024</code>. Bei Turnieren (EM, WM) oft gleich wie Liga-Kürzel.';
  }
}
