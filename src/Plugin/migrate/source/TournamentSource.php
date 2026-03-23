<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Turniere aus D6/D7.
 *
 * @MigrateSource(
 *   id = "soccerbet_tournaments",
 *   source_module = "soccerbet"
 * )
 */
final class TournamentSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->select('soccerbet_tournament', 't')
      ->fields('t', [
        'tournament_id',
        'tournament_desc',
        'start_date',
        'end_date',
        'group_count',
        'is_active',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ]);

    // tipper_grp_id defensiv: nur abfragen wenn Spalte in Quell-DB vorhanden
    if ($this->getDatabase()->schema()->fieldExists('soccerbet_tournament', 'tipper_grp_id')) {
      $q->addField('t', 'tipper_grp_id');
    }
    else {
      $q->addExpression('0', 'tipper_grp_id');
    }

    return $q;
  }

  public function fields(): array {
    return [
      'tournament_id'   => 'Primärschlüssel',
      'tipper_grp_id'   => 'Zugehörige Tippergruppe (aus D6 ermittelt)',
      'tournament_desc' => 'Turnierbezeichnung',
      'start_date'      => 'Startdatum',
      'end_date'        => 'Enddatum',
      'group_count'     => 'Anzahl Gruppen',
      'is_active'       => 'Aktiv-Flag',
      'c_uid'           => 'Ersteller',
      'c_date'          => 'Erstellt am',
      'mod_uid'         => 'Geändert von',
      'mod_date'        => 'Geändert am',
    ];
  }

  public function getIds(): array {
    return [
      'tournament_id' => ['type' => 'integer', 'alias' => 't'],
    ];
  }

  public function prepareRow(Row $row): bool {
    // Datums-Felder: DATETIME → ISO-8601-String
    $row->setSourceProperty('start_date_iso',
      $this->datetimeToIso($row->getSourceProperty('start_date')));
    $row->setSourceProperty('end_date_iso',
      $this->datetimeToIso($row->getSourceProperty('end_date')));
    $row->setSourceProperty('created',
      $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed',
      $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    $row->setSourceProperty('uid', (int) $row->getSourceProperty('c_uid'));

    // tipper_grp_id: Falls 0 → aus soccerbet_tipper_groups_tournament (D6)
    // oder erste verfügbare Gruppe als Fallback
    if ((int) $row->getSourceProperty('tipper_grp_id') === 0) {
      $resolved = 0;

      // Strategie 1: soccerbet_tournament_tippers Tabelle prüfen
      // (D6 speicherte die Gruppe manchmal dort)
      try {
        $resolved = (int) $this->getDatabase()
          ->select('soccerbet_tipper_groups', 'g')
          ->fields('g', ['tipper_grp_id'])
          ->orderBy('g.tipper_grp_id', 'ASC')
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }
      catch (\Exception) {
        $resolved = 1;
      }

      $row->setSourceProperty('tipper_grp_id', $resolved ?: 1);
    }

    // is_active: D6 speicherte das Standardturnier in variable_get().
    // Das Feld is_active in der Tabelle kann 0 sein, obwohl es das Standardturnier ist.
    $tournament_id = (int) $row->getSourceProperty('tournament_id');
    $is_active     = (int) $row->getSourceProperty('is_active');

    if ($is_active === 0) {
      try {
        // D6 serialisiert variable-Werte: s:1:"1" oder i:1
        $raw = $this->getDatabase()
          ->select('variable', 'v')
          ->fields('v', ['value'])
          ->condition('v.name', 'soccerbet_default_tournament')
          ->execute()
          ->fetchField();

        if ($raw !== FALSE && $raw !== NULL) {
          $unserialized = @unserialize($raw, ['allowed_classes' => FALSE]);
          $default_tid  = (int) ($unserialized !== FALSE ? $unserialized : $raw);
          if ($default_tid === $tournament_id) {
            $row->setSourceProperty('is_active', 1);
          }
        }
      }
      catch (\Exception) {
        // variable-Tabelle nicht vorhanden → ignorieren
      }
    }

    return parent::prepareRow($row);
  }
}
