<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\soccerbet\Exception\SoccerbetNotFoundException;

/**
 * Verwaltet Tipper, Tippergruppen, Teams und Spiele.
 */
final class TipperManager {

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  // ================================================================== //
  // TIPPER                                                               //
  // ================================================================== //

  public function loadTipper(int $tipper_id): object {
    $tipper_id = (int) $tipper_id;
    $row = $this->db->select('soccerbet_tippers', 't')
      ->fields('t')->condition('t.tipper_id', $tipper_id)
      ->execute()->fetchObject();
    if (!$row) {
      throw new SoccerbetNotFoundException("Tipper #$tipper_id nicht gefunden.");
    }
    return $row;
  }

  /**
   * Findet den Tipper-Datensatz für einen Drupal-User in einer Gruppe.
   */
  public function loadTipperByUid(int $uid, int $tipper_grp_id): ?object {
    return $this->db->select('soccerbet_tippers', 't')
      ->fields('t')
      ->condition('t.uid', (int) $uid)
      ->condition('t.tipper_grp_id', (int) $tipper_grp_id)
      ->execute()->fetchObject() ?: NULL;
  }

  /**
   * Alle Tipper einer Gruppe.
   *
   * @return object[]
   */
  public function loadTippersByGroup(int $tipper_grp_id): array {
    return $this->db->select('soccerbet_tippers', 't')
      ->fields('t')
      ->condition('t.tipper_grp_id', (int) $tipper_grp_id)
      ->orderBy('t.tipper_name')
      ->execute()->fetchAll();
  }

  /**
   * Erstellt einen neuen Tipper-Datensatz. Gibt tipper_id zurück.
   *
   * Für echte Drupal-User (uid > 0) wird Eindeutigkeit pro Gruppe geprüft.
   * Anonyme Tipper (uid = 0, z.B. aus D6-Migration) dürfen mehrfach vorkommen.
   */
  public function createTipper(int $uid, int $tipper_grp_id, string $tipper_name): int {
    $uid           = (int) $uid;
    $tipper_grp_id = (int) $tipper_grp_id;

    // Duplikat-Schutz für echte User
    if ($uid > 0) {
      $existing = $this->loadTipperByUid($uid, $tipper_grp_id);
      if ($existing) {
        return (int) $existing->tipper_id;
      }
    }

    $now = \Drupal::time()->getRequestTime();
    return (int) $this->db->insert('soccerbet_tippers')
      ->fields([
        'uid'           => $uid,
        'tipper_grp_id' => $tipper_grp_id,
        'tipper_name'   => $tipper_name,
        'created'       => $now,
        'changed'       => $now,
      ])->execute();
  }

  public function updateTipper(int $tipper_id, string $tipper_name): void {
    $this->db->update('soccerbet_tippers')
      ->fields(['tipper_name' => $tipper_name, 'changed' => \Drupal::time()->getRequestTime()])
      ->condition('tipper_id', (int) $tipper_id)->execute();
  }

  /**
   * Aktualisiert Name und Drupal-User-Zuweisung eines Tippers.
   */
  public function updateTipperWithUid(int $tipper_id, string $tipper_name, int $uid): void {
    $this->db->update('soccerbet_tippers')
      ->fields([
        'tipper_name' => $tipper_name,
        'uid'         => $uid,
        'changed'     => \Drupal::time()->getRequestTime(),
      ])
      ->condition('tipper_id', (int) $tipper_id)
      ->execute();
  }

  public function deleteTipper(int $tipper_id): void {
    $tipper_id = (int) $tipper_id;
    $this->db->delete('soccerbet_tournament_tippers')->condition('tipper_id', $tipper_id)->execute();
    $this->db->delete('soccerbet_tipps')->condition('tipper_id', $tipper_id)->execute();
    $this->db->delete('soccerbet_tippers')->condition('tipper_id', $tipper_id)->execute();
  }

  // ================================================================== //
  // TIPPERGRUPPEN                                                        //
  // ================================================================== //

  public function loadGroup(int $tipper_grp_id): object {
    $tipper_grp_id = (int) $tipper_grp_id;
    $row = $this->db->select('soccerbet_tipper_groups', 'g')
      ->fields('g')->condition('g.tipper_grp_id', $tipper_grp_id)
      ->execute()->fetchObject();
    if (!$row) {
      throw new SoccerbetNotFoundException("Tippergruppe #$tipper_grp_id nicht gefunden.");
    }
    return $row;
  }

  /** @return object[] */
  public function loadAllGroups(): array {
    return $this->db->select('soccerbet_tipper_groups', 'g')
      ->fields('g')->orderBy('g.tipper_grp_name')->execute()->fetchAll();
  }

  /** @return array<int, string> */
  public function getGroupOptions(): array {
    $rows = $this->loadAllGroups();
    $options = [];
    foreach ($rows as $row) {
      $options[$row->tipper_grp_id] = $row->tipper_grp_name;
    }
    return $options;
  }

  public function createGroup(string $name, int $admin_uid, string $slug = '', int $max_members = 5): int {
    $now    = \Drupal::time()->getRequestTime();
    $fields = [
      'tipper_grp_name' => $name,
      'tipper_admin_id' => $admin_uid,
      'max_members'     => $max_members,
      'uid'             => $this->currentUser->id(),
      'created'         => $now,
      'changed'         => $now,
    ];
    if ($slug !== '') {
      $fields['group_slug'] = $slug;
    }
    return (int) $this->db->insert('soccerbet_tipper_groups')
      ->fields($fields)->execute();
  }

  /**
   * Lädt eine Tippergruppe anhand ihres URL-Slugs.
   */
  public function loadGroupBySlug(string $slug): ?object {
    return $this->db->select('soccerbet_tipper_groups', 'g')
      ->fields('g')
      ->condition('g.group_slug', $slug)
      ->execute()->fetchObject() ?: NULL;
  }

  /**
   * Generiert einen eindeutigen URL-Slug aus einem Gruppenname.
   */
  public function generateGroupSlug(string $name): string {
    $slug = mb_strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 50) ?: 'gruppe';

    $base = $slug;
    $i    = 2;
    while ($this->loadGroupBySlug($slug)) {
      $slug = $base . '-' . $i++;
    }
    return $slug;
  }

  public function updateGroup(int $tipper_grp_id, string $name, int $admin_uid): void {
    $this->db->update('soccerbet_tipper_groups')
      ->fields(['tipper_grp_name' => $name, 'tipper_admin_id' => $admin_uid, 'changed' => \Drupal::time()->getRequestTime()])
      ->condition('tipper_grp_id', $tipper_grp_id)->execute();
  }

  public function deleteGroup(int $tipper_grp_id): void {
    // Tipper in dieser Gruppe erst löschen
    $tipper_ids = $this->db->select('soccerbet_tippers', 't')
      ->fields('t', ['tipper_id'])->condition('t.tipper_grp_id', $tipper_grp_id)
      ->execute()->fetchCol();
    foreach ($tipper_ids as $tid) {
      $this->deleteTipper((int) $tid);
    }
    $this->db->delete('soccerbet_tipper_groups')->condition('tipper_grp_id', $tipper_grp_id)->execute();
  }

  // ================================================================== //
  // TEAMS                                                                //
  // ================================================================== //

  public function loadTeam(int $team_id): object {
    $row = $this->db->select('soccerbet_teams', 't')
      ->fields('t')->condition('t.team_id', $team_id)
      ->execute()->fetchObject();
    if (!$row) {
      throw new SoccerbetNotFoundException("Team #$team_id nicht gefunden.");
    }
    return $row;
  }

  /** @return object[] */
  public function loadTeamsByTournament(int $tournament_id): array {
    return $this->db->select('soccerbet_teams', 't')
      ->fields('t')
      ->condition('t.tournament_id', $tournament_id)
      ->orderBy('t.team_group')->orderBy('t.points', 'DESC')
      ->execute()->fetchAll();
  }

  /** @return array<int, string> */
  public function getTeamOptions(int $tournament_id): array {
    $rows = $this->loadTeamsByTournament($tournament_id);
    $options = [];
    foreach ($rows as $row) {
      $label = $row->team_group ? "[{$row->team_group}] {$row->team_name}" : $row->team_name;
      $options[$row->team_id] = $label;
    }
    return $options;
  }

  public function createTeam(int $tournament_id, array $values): int {
    $now = \Drupal::time()->getRequestTime();
    return (int) $this->db->insert('soccerbet_teams')
      ->fields([
        'tournament_id' => $tournament_id,
        'team_name'     => (string) $values['team_name'],
        'team_flag'     => (string) ($values['team_flag'] ?? ''),
        'team_group'    => (string) ($values['team_group'] ?? ''),
        'games_played'  => 0, 'games_won' => 0, 'games_drawn' => 0,
        'games_lost'    => 0, 'goals_shot' => 0, 'goals_got'  => 0, 'points' => 0,
        'uid'           => $this->currentUser->id(),
        'created'       => $now, 'changed' => $now,
      ])->execute();
  }

  public function updateTeam(int $team_id, array $values): void {
    $this->db->update('soccerbet_teams')
      ->fields([
        'team_name'    => (string) $values['team_name'],
        'team_flag'    => (string) ($values['team_flag'] ?? ''),
        'team_group'   => (string) ($values['team_group'] ?? ''),
        'games_played' => (int) ($values['games_played'] ?? 0),
        'games_won'    => (int) ($values['games_won']    ?? 0),
        'games_drawn'  => (int) ($values['games_drawn']  ?? 0),
        'games_lost'   => (int) ($values['games_lost']   ?? 0),
        'goals_shot'   => (int) ($values['goals_shot']   ?? 0),
        'goals_got'    => (int) ($values['goals_got']    ?? 0),
        'points'       => (int) ($values['points']       ?? 0),
        'changed'      => \Drupal::time()->getRequestTime(),
      ])
      ->condition('team_id', $team_id)->execute();
  }

  public function deleteTeam(int $team_id): void {
    $this->db->delete('soccerbet_teams')->condition('team_id', $team_id)->execute();
  }

  // ================================================================== //
  // SPIELE                                                               //
  // ================================================================== //

  public function loadGame(int $game_id): object {
    $row = $this->db->select('soccerbet_games', 'g')
      ->fields('g')->condition('g.game_id', $game_id)
      ->execute()->fetchObject();
    if (!$row) {
      throw new SoccerbetNotFoundException("Spiel #$game_id nicht gefunden.");
    }
    return $row;
  }

  /**
   * Lädt Spiele eines Turniers, optional nach Phase gefiltert.
   *
   * @return object[]
   */
  public function loadGamesByTournament(int $tournament_id, ?string $phase = NULL): array {
    $q = $this->db->select('soccerbet_games', 'g');
    $q->fields('g');
    $q->addField('t1', 'team_name', 'team1_name');
    $q->addField('t1', 'team_flag', 'team1_flag');
    $q->addField('t2', 'team_name', 'team2_name');
    $q->addField('t2', 'team_flag', 'team2_flag');
    $q->join('soccerbet_teams', 't1', 'g.team_id_1 = t1.team_id');
    $q->join('soccerbet_teams', 't2', 'g.team_id_2 = t2.team_id');
    $q->condition('g.tournament_id', $tournament_id);
    if ($phase !== NULL) {
      $q->condition('g.phase', $phase);
    }
    $q->orderBy('g.game_date');
    return $q->execute()->fetchAll();
  }

  public function createGame(int $tournament_id, array $values): int {
    $now = \Drupal::time()->getRequestTime();
    return (int) $this->db->insert('soccerbet_games')
      ->fields([
        'tournament_id'  => $tournament_id,
        'team_id_1'      => (int) $values['team_id_1'],
        'team_id_2'      => (int) $values['team_id_2'],
        'game_date'      => (string) $values['game_date'],
        'game_location'  => (string) ($values['game_location'] ?? ''),
        'game_stadium'   => (string) ($values['game_stadium']  ?? ''),
        'phase'          => (string) ($values['phase'] ?? 'group'),
        'published'      => (int)  ($values['published'] ?? 1),
        'uid'            => $this->currentUser->id(),
        'created'        => $now, 'changed' => $now,
      ])->execute();
  }

  public function updateGame(int $game_id, array $values): void {
    $this->db->update('soccerbet_games')
      ->fields([
        'team_id_1'     => (int) $values['team_id_1'],
        'team_id_2'     => (int) $values['team_id_2'],
        'game_date'     => (string) $values['game_date'],
        'game_location' => (string) ($values['game_location'] ?? ''),
        'game_stadium'  => (string) ($values['game_stadium']  ?? ''),
        'phase'         => (string) ($values['phase'] ?? 'group'),
        'published'     => (int)   ($values['published'] ?? 1),
        'changed'       => \Drupal::time()->getRequestTime(),
      ])
      ->condition('game_id', $game_id)->execute();
  }

  /**
   * Trägt das Ergebnis eines Spiels ein und invalidiert den Ranglisten-Cache.
   */
  public function saveScore(int $game_id, int $score1, int $score2, ?int $winner_team_id = NULL): void {
    $game = $this->loadGame($game_id);
    $this->db->update('soccerbet_games')
      ->fields([
        'team1_score'    => $score1,
        'team2_score'    => $score2,
        'winner_team_id' => $winner_team_id,
        'changed'        => \Drupal::time()->getRequestTime(),
      ])
      ->condition('game_id', $game_id)->execute();

    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_standings:' . $game->tournament_id]);
  }

  public function deleteGame(int $game_id): void {
    $this->db->delete('soccerbet_tipps')->condition('game_id', $game_id)->execute();
    $this->db->delete('soccerbet_games')->condition('game_id', $game_id)->execute();
  }

  // ================================================================== //
  // TIPPS                                                                //
  // ================================================================== //

  /**
   * Speichert oder aktualisiert einen Tipp (upsert).
   */
  public function saveTipp(int $tipper_id, int $game_id, int $tipp1, int $tipp2, ?int $winner_team_id = NULL): void {
    $now = \Drupal::time()->getRequestTime();
    $exists = $this->db->select('soccerbet_tipps', 't')
      ->condition('t.tipper_id', $tipper_id)
      ->condition('t.game_id', $game_id)
      ->countQuery()->execute()->fetchField();

    if ($exists) {
      $this->db->update('soccerbet_tipps')
        ->fields([
          'team1_tipp'     => $tipp1,
          'team2_tipp'     => $tipp2,
          'winner_team_id' => $winner_team_id,
          'changed'        => $now,
        ])
        ->condition('tipper_id', $tipper_id)
        ->condition('game_id', $game_id)->execute();
    }
    else {
      $this->db->insert('soccerbet_tipps')
        ->fields([
          'tipper_id'      => $tipper_id,
          'game_id'        => $game_id,
          'team1_tipp'     => $tipp1,
          'team2_tipp'     => $tipp2,
          'winner_team_id' => $winner_team_id,
          'uid'            => $this->currentUser->id(),
          'created'        => $now,
          'changed'        => $now,
        ])->execute();
    }
  }

  /**
   * Tipps eines Tippers für ein Turnier, indiziert nach game_id.
   *
   * @return array<int, object>
   */
  public function loadTippsByTipper(int $tipper_id, int $tournament_id): array {
    $q = $this->db->select('soccerbet_tipps', 'st');
    $q->fields('st');
    $q->join('soccerbet_games', 'sg', 'sg.game_id = st.game_id AND sg.tournament_id = :tid', [':tid' => $tournament_id]);
    $q->condition('st.tipper_id', $tipper_id);
    return $q->execute()->fetchAllAssoc('game_id');
  }
}
