<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Turnier-Tipper-Zuordnung aus D6/D7.
 *
 * In D6 wurden die Zuordnungen in soccerbet_tournament_tippers gespeichert.
 * Falls die Tabelle nicht existiert (ältere D6-Versionen), wird sie aus
 * vorhandenen Tipps rekonstruiert.
 *
 * @MigrateSource(
 *   id = "soccerbet_tournament_tippers",
 *   source_module = "soccerbet"
 * )
 */
final class TournamentTippersSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    // Prüfen ob die Tabelle existiert
    try {
      $q = $this->select('soccerbet_tournament_tippers', 'stt')
        ->fields('stt', ['tournament_id', 'tipper_id']);

      // tipper_has_paid nur wenn vorhanden
      try {
        $q->addField('stt', 'tipper_has_paid');
      }
      catch (\Exception) {
        // Feld existiert nicht in sehr alten D6-Versionen
      }

      return $q;
    }
    catch (\Exception) {
      // Tabelle existiert nicht → aus Tipps rekonstruieren
      return $this->reconstructFromTipps();
    }
  }

  /**
   * Rekonstruiert Turnier-Tipper-Zuordnungen aus der Tipps-Tabelle.
   * Jeder Tipper, der mindestens einen Tipp in einem Turnier hat,
   * wird als Teilnehmer gewertet.
   */
  private function reconstructFromTipps(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->select('soccerbet_tipps', 'st');
    $q->addField('sg', 'tournament_id');
    $q->addField('st', 'tipper_id');
    $q->addExpression('0', 'tipper_has_paid');
    $q->join('soccerbet_games', 'sg', 'sg.game_id = st.game_id');
    $q->groupBy('sg.tournament_id');
    $q->groupBy('st.tipper_id');
    return $q;
  }

  public function fields(): array {
    return [
      'tournament_id'   => 'Turnier-ID',
      'tipper_id'       => 'Tipper-ID',
      'tipper_has_paid' => 'Einsatz bezahlt',
    ];
  }

  public function getIds(): array {
    return [
      'tournament_id' => ['type' => 'integer'],
      'tipper_id'     => ['type' => 'integer'],
    ];
  }

  public function prepareRow(Row $row): bool {
    // Fehlende Felder mit Defaults füllen
    if ($row->getSourceProperty('tipper_has_paid') === NULL) {
      $row->setSourceProperty('tipper_has_paid', 0);
    }
    $row->setSourceProperty('created', \Drupal::time()->getRequestTime());
    return parent::prepareRow($row);
  }
}
