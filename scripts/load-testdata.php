<?php

/**
 * Testdaten für Soccerbet laden (EM 2024 Szenario).
 *
 * Verwendung:
 *   lando drush php:script web/modules/custom/soccerbet/scripts/load-testdata.php
 *
 * Szenario:
 *   - 1 Tippergruppe, 5 Tipper
 *   - 8 Teams in 2 Gruppen (A + B)
 *   - 12 Gruppenspiele (alle gewertet)
 *   - 2 Halbfinale (gewertet)
 *   - 1 Finale: läuft seit 90 Min, noch kein Ergebnis → Winner-Bet-Spalte sichtbar
 *   - Turniersieger-Tipps für alle Tipper
 */

declare(strict_types=1);

$db  = \Drupal::database();
$now = \Drupal::time()->getRequestTime();

// ============================================================
// Bestehende Testdaten löschen (idempotent)
// ============================================================
echo "Suche bestehende Testdaten...\n";
$existing_tid = (int) $db->select('soccerbet_tournament', 't')
  ->fields('t', ['tournament_id'])
  ->condition('t.tournament_desc', 'EM 2024 (Test)')
  ->execute()->fetchField();

if ($existing_tid) {
  echo "  Lösche Turnier $existing_tid...\n";
  $old_grp = (int) $db->select('soccerbet_tournament', 't')
    ->fields('t', ['tipper_grp_id'])->condition('tournament_id', $existing_tid)
    ->execute()->fetchField();

  $db->delete('soccerbet_winner_tipp')->condition('tournament_id', $existing_tid)->execute();
  $game_ids = $db->select('soccerbet_games', 'g')
    ->fields('g', ['game_id'])->condition('tournament_id', $existing_tid)
    ->execute()->fetchCol();
  if ($game_ids) {
    $db->delete('soccerbet_tipps')->condition('game_id', $game_ids, 'IN')->execute();
  }
  $db->delete('soccerbet_games')->condition('tournament_id', $existing_tid)->execute();
  $db->delete('soccerbet_tournament_tippers')->condition('tournament_id', $existing_tid)->execute();
  $db->delete('soccerbet_teams')->condition('tournament_id', $existing_tid)->execute();
  $db->delete('soccerbet_tournament_groups')->condition('tournament_id', $existing_tid)->execute();
  $db->delete('soccerbet_tournament')->condition('tournament_id', $existing_tid)->execute();
  if ($old_grp) {
    $db->delete('soccerbet_tippers')->condition('tipper_grp_id', $old_grp)->execute();
    $db->delete('soccerbet_tipper_groups')->condition('tipper_grp_id', $old_grp)->execute();
  }
  echo "  Gelöscht.\n";
}

// ============================================================
// 1. Tippergruppe
// ============================================================
echo "Erstelle Tippergruppe...\n";
$grp_id = (int) $db->insert('soccerbet_tipper_groups')->fields([
  'tipper_grp_name' => 'Freunde EM 2024',
  'tipper_admin_id' => 1,
  'uid'     => 1,
  'created' => $now,
  'changed' => $now,
])->execute();
echo "  tipper_grp_id = $grp_id\n";

// ============================================================
// 2. Tipper (5 Personen, uid=0 = kein Drupal-Account)
// ============================================================
echo "Erstelle Tipper...\n";
$tipper_names = ['Anna', 'Hansi', 'Klaus', 'Maria', 'Peter'];
$tid_map = [];
foreach ($tipper_names as $name) {
  $tid = (int) $db->insert('soccerbet_tippers')->fields([
    'uid'           => 0,
    'tipper_name'   => $name,
    'tipper_grp_id' => $grp_id,
    'created'       => $now,
    'changed'       => $now,
  ])->execute();
  $tid_map[$name] = $tid;
  echo "  $name → tipper_id=$tid\n";
}

// ============================================================
// 3. Turnier
// ============================================================
echo "Erstelle Turnier...\n";
$t_id = (int) $db->insert('soccerbet_tournament')->fields([
  'tipper_grp_id'   => $grp_id,
  'tournament_desc' => 'EM 2024 (Test)',
  'start_date'      => '2024-06-14',
  'end_date'        => '2024-07-14',
  'group_count'     => 2,
  'is_active'       => 1,
  'uid'     => 1,
  'created' => $now,
  'changed' => $now,
])->execute();
echo "  tournament_id = $t_id\n";

$db->insert('soccerbet_tournament_groups')->fields([
  'tournament_id' => $t_id,
  'tipper_grp_id' => $grp_id,
])->execute();

\Drupal::configFactory()->getEditable('soccerbet.settings')
  ->set('default_tournament', $t_id)
  ->save();
echo "  default_tournament gesetzt auf $t_id\n";

// ============================================================
// 4. Teams (2 Gruppen à 4 Teams)
// ============================================================
echo "Erstelle Teams...\n";
$teams_def = [
  // [name, flag, gruppe]
  ['Deutschland', 'de', 'A'],
  ['Frankreich',  'fr', 'A'],
  ['Österreich',  'at', 'A'],
  ['Schweiz',     'ch', 'A'],
  ['Spanien',     'es', 'B'],
  ['England',     'gb', 'B'],
  ['Portugal',    'pt', 'B'],
  ['Italien',     'it', 'B'],
];
$team_map = [];
foreach ($teams_def as [$name, $flag, $grp]) {
  $id = (int) $db->insert('soccerbet_teams')->fields([
    'tournament_id' => $t_id,
    'team_name'     => $name,
    'team_flag'     => $flag,
    'team_group'    => $grp,
    'uid'     => 1,
    'created' => $now,
    'changed' => $now,
  ])->execute();
  $team_map[$name] = $id;
  echo "  [$grp] $name → team_id=$id\n";
}

// ============================================================
// 5. Turnier-Teilnehmer verknüpfen
// ============================================================
echo "Verknüpfe Tipper mit Turnier...\n";
foreach ($tid_map as $name => $tid) {
  $db->insert('soccerbet_tournament_tippers')->fields([
    'tournament_id'   => $t_id,
    'tipper_id'       => $tid,
    'tipper_has_paid' => 1,
    'created'         => $now,
  ])->execute();
}

// ============================================================
// 6. Spiele
// ============================================================
echo "Erstelle Spiele...\n";

$DE = $team_map['Deutschland'];
$FR = $team_map['Frankreich'];
$AT = $team_map['Österreich'];
$CH = $team_map['Schweiz'];
$ES = $team_map['Spanien'];
$GB = $team_map['England'];
$PT = $team_map['Portugal'];
$IT = $team_map['Italien'];

$ins = function (int $t1, int $t2, string $date, string $phase, ?int $s1, ?int $s2)
  use ($db, $t_id, $now): int {
  return (int) $db->insert('soccerbet_games')->fields([
    'tournament_id' => $t_id,
    'team_id_1'     => $t1,
    'team_id_2'     => $t2,
    'game_date'     => $date,
    'phase'         => $phase,
    'team1_score'   => $s1,
    'team2_score'   => $s2,
    'published'     => 1,
    'uid'     => 1,
    'created' => $now,
    'changed' => $now,
  ])->execute();
};

// --- Gruppe A (alle gewertet) ---
//                        T1   T2   Datum                  Phase    S1  S2
$ga = [
  $ins($DE, $AT, '2024-06-14T18:00:00', 'group', 2, 0),  // DE:AT 2:0
  $ins($FR, $CH, '2024-06-14T21:00:00', 'group', 1, 1),  // FR:CH 1:1
  $ins($DE, $CH, '2024-06-18T18:00:00', 'group', 3, 0),  // DE:CH 3:0
  $ins($FR, $AT, '2024-06-18T21:00:00', 'group', 2, 0),  // FR:AT 2:0
  $ins($DE, $FR, '2024-06-22T21:00:00', 'group', 0, 0),  // DE:FR 0:0
  $ins($AT, $CH, '2024-06-22T21:00:00', 'group', 1, 2),  // AT:CH 1:2
];

// --- Gruppe B (alle gewertet) ---
$gb = [
  $ins($ES, $IT, '2024-06-15T18:00:00', 'group', 3, 0),  // ES:IT 3:0
  $ins($GB, $PT, '2024-06-15T21:00:00', 'group', 0, 0),  // GB:PT 0:0
  $ins($ES, $PT, '2024-06-19T18:00:00', 'group', 1, 0),  // ES:PT 1:0
  $ins($GB, $IT, '2024-06-19T21:00:00', 'group', 2, 1),  // GB:IT 2:1
  $ins($ES, $GB, '2024-06-23T21:00:00', 'group', 2, 2),  // ES:GB 2:2
  $ins($IT, $PT, '2024-06-23T21:00:00', 'group', 0, 1),  // IT:PT 0:1
];

// --- Halbfinale (gewertet) ---
$sf1 = $ins($DE, $ES, '2024-07-09T21:00:00', 'semi', 2, 1);  // DE:ES 2:1
$sf2 = $ins($FR, $PT, '2024-07-10T21:00:00', 'semi', 1, 0);  // FR:PT 1:0

// --- Finale: läuft seit 90 Min, kein Ergebnis ---
$final_date = gmdate('Y-m-d\\TH:i:s', $now - 90 * 60);
$fin = $ins($DE, $FR, $final_date, 'final', NULL, NULL);

$total_games = count($ga) + count($gb) + 2 + 1;
echo "  $total_games Spiele erstellt. Finale (game_id=$fin) läuft seit 90 Min.\n";

// ============================================================
// 7. Tipps (alle 5 Tipper für alle Spiele)
// ============================================================
echo "Erstelle Tipps...\n";

// [anna, hansi, klaus, maria, peter] je [team1_tipp, team2_tipp]
// Echtergebnis steht im Kommentar
$tipp_matrix = [
  $ga[0] => [[2,0],[2,1],[1,0],[2,0],[3,0]],  // real: 2:0
  $ga[1] => [[1,1],[2,0],[1,1],[0,1],[1,1]],  // real: 1:1
  $ga[2] => [[2,0],[3,0],[2,1],[2,0],[1,0]],  // real: 3:0
  $ga[3] => [[1,0],[2,0],[1,1],[2,0],[1,0]],  // real: 2:0
  $ga[4] => [[1,0],[0,0],[0,0],[1,1],[0,0]],  // real: 0:0
  $ga[5] => [[1,2],[0,1],[1,2],[1,0],[0,1]],  // real: 1:2
  $gb[0] => [[2,0],[3,1],[2,1],[1,0],[3,0]],  // real: 3:0
  $gb[1] => [[1,0],[0,0],[1,1],[0,0],[1,0]],  // real: 0:0
  $gb[2] => [[1,0],[2,0],[1,0],[1,1],[1,0]],  // real: 1:0
  $gb[3] => [[2,1],[2,0],[1,0],[2,1],[2,1]],  // real: 2:1
  $gb[4] => [[2,1],[1,2],[2,2],[2,2],[1,1]],  // real: 2:2
  $gb[5] => [[0,1],[0,2],[1,1],[0,1],[0,1]],  // real: 0:1
  $sf1   => [[1,2],[2,1],[2,1],[2,0],[1,1]],  // real: 2:1
  $sf2   => [[1,0],[1,0],[0,1],[1,0],[2,0]],  // real: 1:0
  $fin   => [[1,2],[2,0],[1,1],[2,1],[1,0]],  // Finale: kein Ergebnis
];

$names = array_keys($tid_map); // Anna, Hansi, Klaus, Maria, Peter
$tipp_count = 0;
foreach ($tipp_matrix as $game_id => $row) {
  foreach ($row as $i => $t) {
    $db->insert('soccerbet_tipps')->fields([
      'tipper_id'  => $tid_map[$names[$i]],
      'game_id'    => $game_id,
      'team1_tipp' => $t[0],
      'team2_tipp' => $t[1],
      'uid'     => 1,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $tipp_count++;
  }
}
echo "  $tipp_count Tipps erstellt.\n";

// ============================================================
// 8. Turniersieger-Tipps
// ============================================================
echo "Erstelle Turniersieger-Tipps...\n";

// phase_index: 0 = vor Turnier (10 Punkte), 1 = nach Gruppe (7 Punkte), 2 = nach HF (5 Punkte)
$winner_bets = [
  'Anna'  => [$team_map['Frankreich'],  0],  // 10 Punkte möglich (früh)
  'Hansi' => [$team_map['Deutschland'], 0],  // 10 Punkte möglich (früh)
  'Klaus' => [$team_map['Spanien'],     1],  //  7 Punkte möglich (nach Gruppen)
  'Maria' => [$team_map['Deutschland'], 2],  //  5 Punkte möglich (nach HF)
  'Peter' => [$team_map['Frankreich'],  0],  // 10 Punkte möglich (früh)
];

$points_map = [0 => 10, 1 => 7, 2 => 5, 3 => 3, 4 => 1];
foreach ($winner_bets as $name => [$team_id, $phase_idx]) {
  $db->insert('soccerbet_winner_tipp')->fields([
    'tournament_id' => $t_id,
    'tipper_id'     => $tid_map[$name],
    'team_id'       => $team_id,
    'phase_index'   => $phase_idx,
    'changed_at'    => $now,
  ])->execute();
  $team_name = array_search($team_id, $team_map);
  $pts = $points_map[$phase_idx];
  echo "  $name → $team_name (Phase $phase_idx = $pts Punkte möglich)\n";
}

// ============================================================
// Zusammenfassung
// ============================================================
echo "\n";
echo "=================================================\n";
echo "Testdaten erfolgreich geladen!\n";
echo "=================================================\n";
echo "  Turnier:    EM 2024 (Test)  [tournament_id=$t_id]\n";
echo "  Gruppen:    A (DE, FR, AT, CH)  B (ES, GB, PT, IT)\n";
echo "  Tipper:     Anna, Hansi, Klaus, Maria, Peter\n";
echo "  Finale:     Deutschland vs Frankreich (läuft, kein Score)\n";
echo "  → Live-Ansicht zeigt Winner-Bet-Spalte (ausstehend)\n";
echo "\n";
echo "Tipp:\n";
echo "  Score eintragen → /soccerbet/admin/games (Finale: game_id=$fin)\n";
echo "  Dann: lando drush cr\n";
echo "\n";
