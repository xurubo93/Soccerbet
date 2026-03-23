<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Tipps aus D6/D7.
 *
 * @MigrateSource(
 *   id = "soccerbet_tipps",
 *   source_module = "soccerbet"
 * )
 */
final class TippSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->select('soccerbet_tipps', 't')
      ->fields('t', [
        'tipp_id',
        'tipper_id',
        'game_id',
        'team1_tipp',
        'team2_tipp',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ]);

    // winner_team_id nur abfragen wenn die Spalte existiert
    $columns = $this->getDatabase()->query('DESCRIBE {soccerbet_tipps}')
      ->fetchAllAssoc('Field');
    if (isset($columns['winner_team_id'])) {
      $q->addField('t', 'winner_team_id');
    }
    else {
      $q->addExpression('NULL', 'winner_team_id');
    }

    return $q;
  }

  public function fields(): array {
    return [
      'tipp_id'    => 'Primärschlüssel',
      'tipper_id'  => 'Tipper-ID',
      'game_id'    => 'Spiel-ID',
      'team1_tipp' => 'Tipp Team 1',
      'team2_tipp' => 'Tipp Team 2',
    ];
  }

  public function getIds(): array {
    return [
      'tipp_id' => ['type' => 'integer', 'alias' => 't'],
    ];
  }

  public function prepareRow(Row $row): bool {
    $row->setSourceProperty('created', $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed', $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    $row->setSourceProperty('uid', (int) $row->getSourceProperty('c_uid'));

    // Leeren String bei winner_team_id → NULL
    if ($row->getSourceProperty('winner_team_id') === '') {
      $row->setSourceProperty('winner_team_id', NULL);
    }

    return parent::prepareRow($row);
  }
}
