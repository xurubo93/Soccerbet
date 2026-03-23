<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Tipper (Teilnehmer) aus D6/D7.
 *
 * @MigrateSource(
 *   id = "soccerbet_tippers",
 *   source_module = "soccerbet"
 * )
 */
final class TipperSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->select('soccerbet_tippers', 't')
      ->fields('t', [
        'tipper_id',
        'uid',
        'tipper_name',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ]);

    // tipper_grp_id defensiv: nur abfragen wenn Spalte in Quell-DB vorhanden
    if ($this->getDatabase()->schema()->fieldExists('soccerbet_tippers', 'tipper_grp_id')) {
      $q->addField('t', 'tipper_grp_id');
    }
    else {
      $q->addExpression('0', 'tipper_grp_id');
    }

    return $q;
  }

  public function fields(): array {
    return [
      'tipper_id'     => 'Primärschlüssel',
      'uid'           => 'Drupal User ID',
      'tipper_name'   => 'Anzeigename',
      'tipper_grp_id' => 'Zugehörige Gruppe',
      'c_uid'         => 'Ersteller',
      'c_date'        => 'Erstellt am',
      'mod_uid'       => 'Geändert von',
      'mod_date'      => 'Geändert am',
    ];
  }

  public function getIds(): array {
    return [
      'tipper_id' => ['type' => 'integer', 'alias' => 't'],
    ];
  }

  public function prepareRow(Row $row): bool {
    $row->setSourceProperty('created', $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed', $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    return parent::prepareRow($row);
  }
}
