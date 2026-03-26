<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\soccerbet\Exception\SoccerbetNotFoundException;

/**
 * CRUD-Service für Turniere.
 */
final class TournamentManager {

  public function __construct(
    private readonly Connection $db,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  // ------------------------------------------------------------------ //
  // Lesen                                                                //
  // ------------------------------------------------------------------ //

  /**
   * Lädt ein einzelnes Turnier. Wirft Exception wenn nicht gefunden.
   */
  public function load(int $tournament_id): object {
    $tournament_id = (int) $tournament_id;
    $row = $this->db->select('soccerbet_tournament', 't')
      ->fields('t')
      ->condition('t.tournament_id', $tournament_id)
      ->execute()
      ->fetchObject();

    if (!$row) {
      throw new SoccerbetNotFoundException("Turnier #$tournament_id nicht gefunden.");
    }
    return $row;
  }

  /**
   * Lädt das aktive (Standard-)Turnier oder NULL.
   */
  public function loadActive(): ?object {
    $id = (int) $this->configFactory->get('soccerbet.settings')->get('default_tournament');
    if ($id === 0) {
      return NULL;
    }
    try {
      return $this->load($id);
    }
    catch (SoccerbetNotFoundException) {
      return NULL;
    }
  }

  /**
   * Gibt alle Turniere zurück, optional gefiltert nach Gruppe.
   *
   * @return object[]
   */
  public function loadAll(?int $tipper_grp_id = NULL): array {
    $q = $this->db->select('soccerbet_tournament', 't')->fields('t');
    if ($tipper_grp_id !== NULL) {
      $q->join('soccerbet_tournament_groups', 'tg',
        'tg.tournament_id = t.tournament_id AND tg.tipper_grp_id = :gid',
        [':gid' => $tipper_grp_id]);
    }
    $q->orderBy('t.start_date', 'DESC');
    return $q->execute()->fetchAll();
  }

  /**
   * Gibt alle Turnier-IDs + Namen als Select-Optionen zurück.
   *
   * @return array<int, string>
   */
  public function getOptions(): array {
    $rows = $this->db->select('soccerbet_tournament', 't')
      ->fields('t', ['tournament_id', 'tournament_desc'])
      ->orderBy('t.start_date', 'DESC')
      ->execute()->fetchAll();

    $options = [];
    foreach ($rows as $row) {
      $options[$row->tournament_id] = $row->tournament_desc;
    }
    return $options;
  }

  /**
   * Gibt alle Tippergruppen zurück die einem Turnier zugeordnet sind.
   *
   * @return object[]  Felder: tipper_grp_id, tipper_grp_name
   */
  public function loadTipperGroups(int $tournament_id): array {
    $q = $this->db->select('soccerbet_tipper_groups', 'g');
    $q->fields('g', ['tipper_grp_id', 'tipper_grp_name']);
    $q->join('soccerbet_tournament_groups', 'tg',
      'tg.tipper_grp_id = g.tipper_grp_id AND tg.tournament_id = :tid',
      [':tid' => $tournament_id]);
    $q->orderBy('g.tipper_grp_name');
    return $q->execute()->fetchAll();
  }

  /**
   * Gibt die IDs aller einem Turnier zugeordneten Tippergruppen zurück.
   *
   * @return int[]
   */
  public function loadTipperGroupIds(int $tournament_id): array {
    $rows = $this->db->select('soccerbet_tournament_groups', 'tg')
      ->fields('tg', ['tipper_grp_id'])
      ->condition('tg.tournament_id', $tournament_id)
      ->execute()->fetchAll();
    return array_map(fn($r) => (int) $r->tipper_grp_id, $rows);
  }

  /**
   * Setzt die Tippergruppen-Zuordnung eines Turniers.
   * Bestehende Zeilen (inkl. Sieger-Felder) bleiben erhalten; nur entfernte
   * Gruppen werden gelöscht, neue Gruppen werden hinzugefügt.
   *
   * @param int[] $group_ids
   */
  public function setTipperGroups(int $tournament_id, array $group_ids): void {
    $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));

    // Gruppen löschen, die nicht mehr zur neuen Auswahl gehören
    $del = $this->db->delete('soccerbet_tournament_groups')
      ->condition('tournament_id', $tournament_id);
    if (!empty($group_ids)) {
      $del->condition('tipper_grp_id', $group_ids, 'NOT IN');
    }
    $del->execute();

    // Neue Gruppen einfügen (bestehende Zeilen unberührt lassen)
    foreach ($group_ids as $grp_id) {
      $this->db->merge('soccerbet_tournament_groups')
        ->keys(['tournament_id' => $tournament_id, 'tipper_grp_id' => $grp_id])
        ->execute();
    }
  }

  /**
   * Lädt die Platzierungen (1.–3.) aller Tippergruppen eines Turniers.
   *
   * @return array<int, object>  Keyed by tipper_grp_id
   */
  public function loadGroupWinners(int $tournament_id): array {
    $rows = $this->db->select('soccerbet_tournament_groups', 'tg')
      ->fields('tg', ['tipper_grp_id', 'winner_tipper_id', 'second_tipper_id', 'third_tipper_id'])
      ->condition('tg.tournament_id', $tournament_id)
      ->execute()->fetchAll();
    $result = [];
    foreach ($rows as $row) {
      $result[(int) $row->tipper_grp_id] = $row;
    }
    return $result;
  }

  /**
   * Speichert die Platzierungen einer Tippergruppe für ein Turnier.
   *
   * @param array{winner_tipper_id: ?int, second_tipper_id: ?int, third_tipper_id: ?int} $winners
   */
  public function saveGroupWinners(int $tournament_id, int $tipper_grp_id, array $winners): void {
    $this->db->update('soccerbet_tournament_groups')
      ->fields([
        'winner_tipper_id' => $winners['winner_tipper_id'] ? (int) $winners['winner_tipper_id'] : NULL,
        'second_tipper_id' => $winners['second_tipper_id'] ? (int) $winners['second_tipper_id'] : NULL,
        'third_tipper_id'  => $winners['third_tipper_id']  ? (int) $winners['third_tipper_id']  : NULL,
      ])
      ->condition('tournament_id', $tournament_id)
      ->condition('tipper_grp_id', $tipper_grp_id)
      ->execute();
  }

  /**
   * Gibt alle Tipper zurück, die einem Turnier zugeordnet sind.
   * Berücksichtigt alle verknüpften Tippergruppen (N:M).
   *
   * @return object[]  Felder: tipper_id, tipper_name, uid, tipper_has_paid, payment_note
   */
  public function loadTippers(int $tournament_id): array {
    $tournament_id = (int) $tournament_id;
    $q = $this->db->select('soccerbet_tippers', 'st');
    $q->fields('st', ['tipper_id', 'tipper_name', 'uid']);
    $q->join('soccerbet_tournament_tippers', 'stt',
      'stt.tipper_id = st.tipper_id AND stt.tournament_id = :tid',
      [':tid' => $tournament_id]);
    $q->fields('stt', ['tipper_has_paid', 'payment_note', 'confirmed_date']);
    $q->orderBy('st.tipper_name');
    return $q->execute()->fetchAll();
  }

  // ------------------------------------------------------------------ //
  // Schreiben                                                            //
  // ------------------------------------------------------------------ //

  /**
   * Legt ein neues Turnier an. Gibt die neue ID zurück.
   */
  public function create(array $values): int {
    $now = \Drupal::time()->getRequestTime();
    $tournament_id = (int) $this->db->insert('soccerbet_tournament')
      ->fields([
        'tournament_desc' => (string) $values['tournament_desc'],
        'start_date'      => (string) $values['start_date'],
        'end_date'        => (string) $values['end_date'],
        'group_count'     => (int) ($values['group_count'] ?? 0),
        'is_active'       => (int) ($values['is_active'] ?? 0),
        'uid'             => $this->currentUser->id(),
        'created'         => $now,
        'changed'         => $now,
      ])
      ->execute();

    // Tippergruppen-Zuordnung speichern
    if (!empty($values['tipper_grp_ids'])) {
      $this->setTipperGroups($tournament_id, $values['tipper_grp_ids']);
    }

    return $tournament_id;
  }

  /**
   * Aktualisiert ein bestehendes Turnier.
   */
  public function update(int $tournament_id, array $values): void {
    $this->db->update('soccerbet_tournament')
      ->fields([
        'tournament_desc' => (string) $values['tournament_desc'],
        'start_date'      => (string) $values['start_date'],
        'end_date'        => (string) $values['end_date'],
        'group_count'     => (int) ($values['group_count'] ?? 0),
        'is_active'       => (int) ($values['is_active'] ?? 0),
        'changed'         => \Drupal::time()->getRequestTime(),
      ])
      ->condition('tournament_id', $tournament_id)
      ->execute();

    // Tippergruppen-Zuordnung aktualisieren
    if (isset($values['tipper_grp_ids'])) {
      $this->setTipperGroups($tournament_id, $values['tipper_grp_ids']);
    }
  }

  /**
   * Löscht ein Turnier und alle zugehörigen Daten (Cascade).
   */
  /**
   * Löscht alle Turnierdaten (Teams, Spiele, Tipps, Teilnehmer),
   * behält aber das Turnier und seine Gruppen-Zuordnungen.
   */
  public function resetData(int $tournament_id): void {
    $game_ids = $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['game_id'])
      ->condition('g.tournament_id', $tournament_id)
      ->execute()->fetchCol();

    if (!empty($game_ids)) {
      $this->db->delete('soccerbet_tipps')
        ->condition('game_id', $game_ids, 'IN')
        ->execute();
    }

    $this->db->delete('soccerbet_games')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_teams')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_tournament_tippers')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_tournament_groups')
      ->condition('tournament_id', $tournament_id)->execute();

    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_standings:' . $tournament_id]);
  }

  public function delete(int $tournament_id): void {
    // Tipps der zugehörigen Spiele löschen
    $game_ids = $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['game_id'])
      ->condition('g.tournament_id', $tournament_id)
      ->execute()->fetchCol();

    if (!empty($game_ids)) {
      $this->db->delete('soccerbet_tipps')
        ->condition('game_id', $game_ids, 'IN')
        ->execute();
    }

    // Spiele, Teams, Turnier-Tipper-Zuordnungen und Turnier selbst löschen
    $this->db->delete('soccerbet_games')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_teams')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_tournament_tippers')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_tournament_groups')
      ->condition('tournament_id', $tournament_id)->execute();
    $this->db->delete('soccerbet_tournament')
      ->condition('tournament_id', $tournament_id)->execute();

    // Cache invalidieren
    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_standings:' . $tournament_id]);
  }

  // ------------------------------------------------------------------ //
  // Teilnehmer-Verwaltung                                                //
  // ------------------------------------------------------------------ //

  /**
   * Fügt einen Tipper zu einem Turnier hinzu.
   */
  public function addTipper(int $tournament_id, int $tipper_id): void {
    $exists = $this->db->select('soccerbet_tournament_tippers', 'stt')
      ->condition('stt.tournament_id', $tournament_id)
      ->condition('stt.tipper_id', $tipper_id)
      ->countQuery()->execute()->fetchField();

    if (!$exists) {
      $this->db->insert('soccerbet_tournament_tippers')
        ->fields([
          'tournament_id'   => $tournament_id,
          'tipper_id'       => $tipper_id,
          'tipper_has_paid' => 0,
          'created'         => \Drupal::time()->getRequestTime(),
        ])
        ->execute();
    }
  }

  /**
   * Entfernt einen Tipper aus einem Turnier.
   */
  public function removeTipper(int $tournament_id, int $tipper_id): void {
    $this->db->delete('soccerbet_tournament_tippers')
      ->condition('tournament_id', $tournament_id)
      ->condition('tipper_id', $tipper_id)
      ->execute();
  }

  /**
   * Setzt den Zahlungsstatus eines Tippers für ein Turnier.
   */
  public function setPaymentStatus(
    int $tournament_id,
    int $tipper_id,
    bool $paid,
    ?string $note = NULL,
  ): void {
    $fields = [
      'tipper_has_paid' => (int) $paid,
    ];
    if ($paid) {
      $fields['confirmed_by_uid'] = $this->currentUser->id();
      $fields['confirmed_date']   = \Drupal::time()->getRequestTime();
    }
    if ($note !== NULL) {
      $fields['payment_note'] = $note;
    }

    $this->db->update('soccerbet_tournament_tippers')
      ->fields($fields)
      ->condition('tournament_id', $tournament_id)
      ->condition('tipper_id', $tipper_id)
      ->execute();

    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_standings:' . $tournament_id]);
  }
}
