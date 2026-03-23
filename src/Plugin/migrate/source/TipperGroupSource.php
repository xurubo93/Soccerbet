<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Tippergruppen aus D6/D7.
 *
 * @MigrateSource(
 *   id = "soccerbet_tipper_groups",
 *   source_module = "soccerbet"
 * )
 */
final class TipperGroupSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    return $this->select('soccerbet_tipper_groups', 'g')
      ->fields('g', [
        'tipper_grp_id',
        'tipper_grp_name',
        'tipper_admin_id',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ]);
  }

  public function fields(): array {
    return [
      'tipper_grp_id'   => 'Primärschlüssel der Gruppe',
      'tipper_grp_name' => 'Name der Gruppe',
      'tipper_admin_id' => 'Admin-UID',
      'c_uid'           => 'Ersteller-UID',
      'c_date'          => 'Erstellungsdatum',
      'mod_uid'         => 'Letzter Bearbeiter',
      'mod_date'        => 'Letztes Bearbeitungsdatum',
    ];
  }

  public function getIds(): array {
    return [
      'tipper_grp_id' => ['type' => 'integer', 'alias' => 'g'],
    ];
  }

  public function prepareRow(Row $row): bool {
    $row->setSourceProperty('created', $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed', $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    return parent::prepareRow($row);
  }
}
