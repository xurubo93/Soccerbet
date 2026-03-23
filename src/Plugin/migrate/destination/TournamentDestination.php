<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

use Drupal\migrate\Row;

/**
 * @MigrateDestination(id = "soccerbet_tournaments_destination")
 */
final class TournamentDestination extends SoccerbetDestinationBase {

  protected string $table = 'soccerbet_tournament';
  protected array $primaryKey = ['tournament_id'];

  public function import(Row $row, array $old_destination_id_values = []): array|bool {
    $result = parent::import($row, $old_destination_id_values);

    // tipper_grp_id aus D6 in die neue N:M-Tabelle übernehmen
    if ($result !== FALSE) {
      $tournament_id = $result[0] ?? NULL;
      $tipper_grp_id = (int) $row->getDestinationProperty('tipper_grp_id');
      if ($tournament_id && $tipper_grp_id > 0) {
        $this->db->merge('soccerbet_tournament_groups')
          ->keys([
            'tournament_id' => (int) $tournament_id,
            'tipper_grp_id' => $tipper_grp_id,
          ])
          ->execute();
      }
    }

    return $result;
  }
}
