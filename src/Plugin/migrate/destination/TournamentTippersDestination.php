<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

use Drupal\migrate\Row;

/**
 * Destination: soccerbet_tournament_tippers (Composite Primary Key).
 *
 * @MigrateDestination(id = "soccerbet_tournament_tippers_destination")
 */
final class TournamentTippersDestination extends SoccerbetDestinationBase {

  protected string $table = 'soccerbet_tournament_tippers';
  protected array $primaryKey = ['tournament_id', 'tipper_id'];

  public function getIds(): array {
    return [
      'tournament_id' => ['type' => 'integer'],
      'tipper_id'     => ['type' => 'integer'],
    ];
  }

  public function import(Row $row, array $old_destination_id_values = []): array|bool {
    $record = $row->getDestination();

    // _-Präfix-Felder (Lookup-Zwischenvariablen) herausfiltern
    foreach (array_keys($record) as $key) {
      if (str_starts_with((string) $key, '_')) {
        unset($record[$key]);
      }
    }

    // NULL in Pflichtfeldern → Row überspringen
    if (empty($record['tournament_id']) || empty($record['tipper_id'])) {
      return FALSE;
    }

    $exists = (bool) $this->db->select($this->table, 't')
      ->condition('t.tournament_id', $record['tournament_id'])
      ->condition('t.tipper_id',     $record['tipper_id'])
      ->countQuery()->execute()->fetchField();

    if ($exists) {
      $this->db->update($this->table)
        ->fields($record)
        ->condition('tournament_id', $record['tournament_id'])
        ->condition('tipper_id',     $record['tipper_id'])
        ->execute();
    }
    else {
      $this->db->insert($this->table)->fields($record)->execute();
    }

    return [$record['tournament_id'], $record['tipper_id']];
  }

  public function rollback(array $destination_identifier): void {
    $this->db->delete($this->table)
      ->condition('tournament_id', $destination_identifier['tournament_id'])
      ->condition('tipper_id',     $destination_identifier['tipper_id'])
      ->execute();
  }
}
