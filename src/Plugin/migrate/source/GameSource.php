<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Spiele aus D6/D7.
 *
 * Erkennt die Spielphase automatisch aus der Anzahl der Spiele pro Turnier
 * (Gruppenphase vs. KO-Runden) da das D6-Modul keine explizite Phase-Spalte
 * hatte – stattdessen wurden KO-Spiele über phases.admin.inc verwaltet.
 *
 * @MigrateSource(
 *   id = "soccerbet_games",
 *   source_module = "soccerbet"
 * )
 */
final class GameSource extends SoccerbetSourceBase {

  /**
   * Cache: Spielanzahl pro Turnier für Phase-Erkennung.
   * @var array<int, int>
   */
  private array $gameCounts = [];

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    $q = $this->select('soccerbet_games', 'g')
      ->fields('g', [
        'game_id',
        'tournament_id',
        'team_id_1',
        'team_id_2',
        'game_date',
        'game_location',
        'game_stadium',
        'team1_score',
        'team2_score',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ])
      ->orderBy('g.tournament_id')
      ->orderBy('g.game_date');

    // winner_team_id nur abfragen wenn die Spalte in der Quell-DB existiert
    $columns = $this->getDatabase()->query('DESCRIBE {soccerbet_games}')
      ->fetchAllAssoc('Field');
    if (isset($columns['winner_team_id'])) {
      $q->addField('g', 'winner_team_id');
    }
    else {
      $q->addExpression('NULL', 'winner_team_id');
    }

    return $q;
  }

  public function fields(): array {
    return [
      'game_id'       => 'Primärschlüssel',
      'tournament_id' => 'Turnier-ID',
      'team_id_1'     => 'Heimteam-ID',
      'team_id_2'     => 'Auswärtsteam-ID',
      'game_date'     => 'Anpfiff (DATETIME)',
      'game_location' => 'Stadt',
      'game_stadium'  => 'Stadion',
      'team1_score'   => 'Tore Heimteam',
      'team2_score'   => 'Tore Auswärtsteam',
      'phase'         => 'Berechnete Phase (group/ko)',
      'published'     => 'Veröffentlicht (immer 1 bei Migration)',
    ];
  }

  public function getIds(): array {
    return [
      'game_id' => ['type' => 'integer', 'alias' => 'g'],
    ];
  }

  public function prepareRow(Row $row): bool {
    $tournament_id = (int) $row->getSourceProperty('tournament_id');

    // Spielanzahl pro Turnier cachen
    if (!isset($this->gameCounts[$tournament_id])) {
      $this->gameCounts[$tournament_id] = (int) $this->select('soccerbet_games', 'g')
        ->condition('g.tournament_id', $tournament_id)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    // Einfache Phase-Heuristik:
    // D6 hatte keine Phase-Spalte. Wir schauen ob eine phases-Tabelle existiert.
    $phase = $this->detectPhase($row, $tournament_id);
    $row->setSourceProperty('phase', $phase);

    // winner_team_id kommt direkt aus dem Query (NULL wenn Spalte fehlt)
    // Leeren String auf NULL normalisieren
    if ($row->getSourceProperty('winner_team_id') === '') {
      $row->setSourceProperty('winner_team_id', NULL);
    }

    // published: In D6 gab es kein published-Feld → alle importierten Spiele als published markieren
    $row->setSourceProperty('published', 1);

    // Datum-Konvertierung
    $row->setSourceProperty('game_date_iso', $this->datetimeToIso($row->getSourceProperty('game_date')));
    $row->setSourceProperty('created', $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed', $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    $row->setSourceProperty('uid', (int) $row->getSourceProperty('c_uid'));

    return parent::prepareRow($row);
  }

  /**
   * Erkennt die Phase eines Spiels.
   *
   * Strategie: Falls eine soccerbet_phases Tabelle existiert, diese auslesen.
   * Sonst: Gruppenspiele haben typisch N×(N-1)/2 Spiele pro Gruppe (z.B. 6 Spiele
   * für 4 Teams). KO-Spiele kommen danach. Wir verwenden Spielreihenfolge.
   */
  private function detectPhase(Row $row, int $tournament_id): string {
    $game_id = (int) $row->getSourceProperty('game_id');

    // Prüfen ob phases-Tabelle existiert (optionales D6-Feature)
    try {
      $phase_row = $this->select('soccerbet_phases', 'p')
        ->fields('p', ['phase_type'])
        ->condition('p.game_id', $game_id)
        ->execute()
        ->fetchObject();
      if ($phase_row) {
        return $this->mapLegacyPhase($phase_row->phase_type);
      }
    }
    catch (\Exception) {
      // Tabelle existiert nicht → Heuristik verwenden
    }

    // Heuristik: Die ersten X% der Spiele eines Turniers = Gruppenphase
    // Typischer EM/WM-Aufbau: 24 Teams → 36 Gruppenspiele, dann 16 KO-Spiele
    $total = $this->gameCounts[$tournament_id];
    $game_number = (int) $this->select('soccerbet_games', 'g')
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.game_date', $row->getSourceProperty('game_date'), '<=')
      ->condition('g.game_id', $game_id, '<=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Typische Aufteilung: ~70% Gruppenphase, Rest KO
    if ($total <= 3 || $game_number <= intval($total * 0.7)) {
      return 'group';
    }

    $remaining = $total - $game_number;
    return match(true) {
      $remaining >= 15 => 'round_of_16',
      $remaining >= 7  => 'quarter',
      $remaining >= 3  => 'semi',
      $remaining === 1 => 'third_place',
      default          => 'final',
    };
  }

  /**
   * Mapt D6-Phasennamen auf D11-Konstanten.
   */
  private function mapLegacyPhase(string $legacy): string {
    return match(strtolower($legacy)) {
      'vorrunde', 'group', 'preliminary' => 'group',
      'achtelfinale', 'round_of_16'      => 'round_of_16',
      'viertelfinale', 'quarter'         => 'quarter',
      'halbfinale', 'semi'               => 'semi',
      'platz3', 'third', 'third_place'   => 'third_place',
      'finale', 'final'                  => 'final',
      default                            => 'group',
    };
  }
}
