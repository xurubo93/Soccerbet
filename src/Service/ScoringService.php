<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Berechnet Punkte, Rangliste und Bonus für alle Tipper eines Turniers.
 *
 * Ersetzt die prozedurale soccerbet_games_get_tipperpoints()-Funktion aus D6.
 * Statt N×M-Datenbankabfragen (1 Query pro Tipper × 1 Query pro Spiel) werden
 * alle benötigten Daten in 3 gezielten JOINs geladen.
 */
final class ScoringService {

  public function __construct(
    private readonly Connection $db,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Liefert vollständige Punkte-Daten für alle Tipper eines Turniers.
   *
   * @param int $tournament_id  Turnier-ID.
   * @param int $limit          Nur die ersten N gespielten Spiele berücksichtigen (0 = alle).
   *
   * @return array<int, array{
   *   name: string,
   *   tipper_has_paid: bool,
   *   basispunkte: array<int, int>,
   *   sonderpunkte: array<int, int>,
   *   bonuspunkte: array<int, int>,
   *   totalpergame: array<int, int>,
   *   tipp: array<int, string>,
   *   game_desc: array<int, string>,
   *   game_score: array<int, string>,
   *   ergebnisse: int,
   *   tendenzen: int,
   *   total: int,
   * }>
   */
  public function getTipperPoints(int $tournament_id, int $limit = 0): array {
    $tournament_id = (int) $tournament_id;
    $limit         = (int) $limit;
    $config = $this->configFactory->get('soccerbet.settings');
    $pts_exact     = (int) $config->get('points_exact');
    $pts_tendency  = (int) $config->get('points_tendency');

    // ------------------------------------------------------------------ //
    // Query 1: Alle Tipper, die am Turnier teilnehmen                     //
    // ------------------------------------------------------------------ //
    $tippers = $this->db->select('soccerbet_tippers', 'st');
    $tippers->fields('st', ['tipper_id', 'tipper_name', 'uid']);
    // MAX() um bei eventuellen Duplikaten einen definierten Wert zu bekommen
    $tippers->addExpression('MAX(stt.tipper_has_paid)', 'tipper_has_paid');
    $tippers->join('soccerbet_tournament_tippers', 'stt',
      'stt.tipper_id = st.tipper_id AND stt.tournament_id = :tid',
      [':tid' => $tournament_id]);
    $tippers->groupBy('st.tipper_id');
    $tippers->groupBy('st.tipper_name');
    $tippers->groupBy('st.uid');
    $tippers->orderBy('st.tipper_id', 'ASC');

    $tipper_rows = [];
    foreach ($tippers->execute()->fetchAll() as $row) {
      $tipper_rows[(int) $row->tipper_id] = $row;
    }
    $tipper_count = count($tipper_rows);

    if ($tipper_count === 0) {
      return [];
    }

    // ------------------------------------------------------------------ //
    // Query 2: Alle gespielten Spiele des Turniers (mit Limit)            //
    // ------------------------------------------------------------------ //
    $games_q = $this->db->select('soccerbet_games', 'sg');
    $games_q->fields('sg', ['game_id', 'team1_score', 'team2_score', 'winner_team_id', 'game_date']);
    $games_q->addField('t1', 'team_name', 'team1_name');
    $games_q->addField('t2', 'team_name', 'team2_name');
    $games_q->join('soccerbet_teams', 't1', 'sg.team_id_1 = t1.team_id');
    $games_q->join('soccerbet_teams', 't2', 'sg.team_id_2 = t2.team_id');
    $games_q->condition('sg.tournament_id', $tournament_id);
    $games_q->condition('sg.game_date', gmdate('Y-m-d\TH:i:s'), '<');
    $games_q->condition('sg.published', 1);
    $games_q->orderBy('sg.game_date', 'ASC');
    if ($limit > 0) {
      $games_q->range(0, $limit);
    }
    $games_raw = $games_q->execute()->fetchAll();
    $games = [];
    foreach ($games_raw as $game) {
      $games[(int) $game->game_id] = $game;
    }

    if (empty($games)) {
      // Tipper initialisieren, aber alle Punkte = 0
      return $this->initializeTippers($tipper_rows);
    }

    $game_ids = array_keys($games);

    // ------------------------------------------------------------------ //
    // Query 3: Alle Tipps aller Tipper für diese Spiele (ein einziger     //
    // Query statt N × M Einzelabfragen!)                                   //
    // ------------------------------------------------------------------ //
    $tipper_ids = array_keys((array) $tipper_rows);
    $tipps_q = $this->db->select('soccerbet_tipps', 'stp');
    $tipps_q->fields('stp', ['tipper_id', 'game_id', 'team1_tipp', 'team2_tipp', 'winner_team_id']);
    $tipps_q->condition('stp.game_id', $game_ids, 'IN');
    $tipps_q->condition('stp.tipper_id', $tipper_ids, 'IN');
    // Indiziert als tipps[$tipper_id][$game_id]
    $tipps_flat = $tipps_q->execute()->fetchAll();
    $tipps = [];
    foreach ($tipps_flat as $row) {
      $tipps[(int) $row->tipper_id][(int) $row->game_id] = $row;
    }

    // ------------------------------------------------------------------ //
    // Punkte berechnen                                                     //
    // ------------------------------------------------------------------ //
    $tipper_points = $this->initializeTippers($tipper_rows);

    foreach ($tipper_rows as $tipper_id => $tipper) {
      foreach ($games as $game_id => $game) {
        $tipper_points[$tipper_id]['game_desc'][$game_id]  = $game->team1_name . ' : ' . $game->team2_name;
        $tipper_points[$tipper_id]['game_score'][$game_id] = $game->team1_score . ' : ' . $game->team2_score;

        if (!isset($tipps[$tipper_id][$game_id])) {
          // Kein Tipp abgegeben
          $tipper_points[$tipper_id]['tipp'][$game_id]         = 'N.A.';
          $tipper_points[$tipper_id]['basispunkte'][$game_id]  = 0;
          $tipper_points[$tipper_id]['sonderpunkte'][$game_id] = 0;
          continue;
        }

        $tipp = $tipps[$tipper_id][$game_id];
        $tipper_points[$tipper_id]['tipp'][$game_id] = $tipp->team1_tipp . ' : ' . $tipp->team2_tipp;

        // Basispunkte: exaktes Ergebnis oder richtige Tendenz
        $basispunkte = 0;
        if ((int) $game->team1_score === (int) $tipp->team1_tipp
          && (int) $game->team2_score === (int) $tipp->team2_tipp) {
          $basispunkte = $pts_exact;
        }
        else {
          $tendenz_spiel = (int) $game->team1_score   - (int) $game->team2_score;
          $tendenz_tipp  = (int) $tipp->team1_tipp    - (int) $tipp->team2_tipp;
          $gleiche_tendenz =
            ($tendenz_spiel < 0 && $tendenz_tipp < 0) ||
            ($tendenz_spiel === 0 && $tendenz_tipp === 0) ||
            ($tendenz_spiel > 0 && $tendenz_tipp > 0);
          if ($gleiche_tendenz) {
            $basispunkte = $pts_tendency;
          }
        }
        $tipper_points[$tipper_id]['basispunkte'][$game_id] = $basispunkte;

        // Sonderpunkte: Aufsteiger in KO-Runden richtig getippt
        $sonderpunkte = 0;
        if ($tipp->winner_team_id && $game->winner_team_id
          && (int) $game->winner_team_id === (int) $tipp->winner_team_id) {
          $sonderpunkte = $tipper_count; // Anzahl Teilnehmer als Multiplikator
        }
        $tipper_points[$tipper_id]['sonderpunkte'][$game_id] = $sonderpunkte;
      }
    }

    // Bonuspunkte und Totalsummen berechnen
    $this->calculateBonusPoints($tipper_points, $games);
    $this->calculateTotals($tipper_points);

    return $tipper_points;
  }

  /**
   * Liefert die Rangliste sortiert nach Total → Ergebnisse → Tendenzen.
   *
   * @return array Sortierte Einträge mit 'rank', 'rank_before', 'diff'.
   */
  public function getRanking(int $tournament_id, int $limit = 0): array {
    $tournament_id = (int) $tournament_id;
    $limit         = (int) $limit;
    $current = $this->getTipperPoints($tournament_id, $limit);
    if (empty($current)) {
      return [];
    }

    // Sieger-Sterne: für jeden Tipper zählen wie oft er 1. Platz wurde
    // (über ALLE abgeschlossenen Turniere in derselben Tippergruppe)
    $stars = [];
    $wins_q = $this->db->select('soccerbet_tournament_groups', 'tg')
      ->fields('tg', ['winner_tipper_id'])
      ->isNotNull('winner_tipper_id')
      ->execute()->fetchCol();
    foreach ($wins_q as $winner_id) {
      $winner_id = (int) $winner_id;
      $stars[$winner_id] = ($stars[$winner_id] ?? 0) + 1;
    }

    // Vorherigen Stand (für Rang-Verlauf ↑↓)
    $played     = $this->getPlayedGamesCount($tournament_id);
    $prev_limit = ($limit === 0) ? max(0, $played - 1) : max(0, $limit - 1);
    $previous   = ($prev_limit > 0) ? $this->getTipperPoints($tournament_id, $prev_limit) : [];

    $rows = [];
    foreach ($current as $tipper_id => $data) {
      $rows[] = [
        'tipper_id'  => $tipper_id,
        'uid'        => $data['uid'],
        'name'       => $data['name'],
        'stars'      => $stars[$tipper_id] ?? 0,
        'paid'       => $data['tipper_has_paid'],
        'ergebnisse' => $data['ergebnisse'],
        'tendenzen'  => $data['tendenzen'],
        'total'      => $data['total'],
      ];
    }

    // Mehrstufige Sortierung identisch zum D6-Original
    usort($rows, static function (array $a, array $b): int {
      return $b['total']      <=> $a['total']
        ?: $b['ergebnisse']   <=> $a['ergebnisse']
        ?: $b['tendenzen']    <=> $a['tendenzen'];
    });

    // Rang-Verlauf berechnen
    $prev_ranks = $this->buildRankMap($previous);
    foreach ($rows as $rank => &$row) {
      $row['rank']        = $rank + 1;
      $row['rank_before'] = $prev_ranks[$row['tipper_id']] ?? 0;
      $row['diff']        = $row['rank_before'] > 0
        ? $row['rank_before'] - $row['rank']
        : 0;
    }

    return $rows;
  }

  /**
   * Anzahl bereits gespielter (und veröffentlichter) Spiele.
   */
  public function getPlayedGamesCount(int $tournament_id): int {
    $tournament_id = (int) $tournament_id;
    return (int) $this->db->select('soccerbet_games', 'sg')
      ->condition('sg.tournament_id', $tournament_id)
      ->condition('sg.game_date', gmdate('Y-m-d\TH:i:s'), '<')
      ->condition('sg.published', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  // -------------------------------------------------------------------- //
  // Private Hilfsmethoden                                                  //
  // -------------------------------------------------------------------- //

  /**
   * Initialisiert das Tipper-Points-Array mit Nullwerten.
   */
  private function initializeTippers(object|array $tipper_rows): array {
    $result = [];
    foreach ($tipper_rows as $tipper_id => $tipper) {
      // clone sicherstellt dass jeder Eintrag ein eigenes Objekt ist
      $t = clone $tipper;
      $result[(int) $tipper_id] = [
        'name'            => $t->tipper_name,
        'uid'             => (int) $t->uid,
        'tipper_has_paid' => (bool) $t->tipper_has_paid,
        'basispunkte'     => [],
        'bonuspunkte'     => [],
        'sonderpunkte'    => [],
        'totalpergame'    => [],
        'tipp'            => [],
        'game_desc'       => [],
        'game_score'      => [],
        'ergebnisse'      => 0,
        'tendenzen'       => 0,
        'total'           => 0,
      ];
    }
    return $result;
  }

  /**
   * Berechnet Bonuspunkte pro Spiel nach folgendem System:
   *
   * Pro Spiel gibt es drei Klassen von Tippern:
   *   EXAKT     (Basispunkte = 3): richtiges Ergebnis
   *   TENDENZ   (Basispunkte = 1): richtige Tendenz
   *   FALSCH    (Basispunkte = 0): falscher Tipp oder kein Tipp
   *
   * Bonuspunkte-Fluss (jeder "schlechtere" zahlt an jeden "besseren"):
   *   EXAKT    erhält +2 von jedem FALSCH-Tipper
   *   EXAKT    erhält +1 von jedem TENDENZ-Tipper
   *   TENDENZ  erhält +1 von jedem FALSCH-Tipper
   *   FALSCH   erhält nichts
   *
   * Beispiel mit 10 Tippern (3 exakt, 4 Tendenz, 3 falsch):
   *   Jeder EXAKT-Tipper bekommt:  4×1 + 3×2 = 10 Bonuspunkte
   *   Jeder TENDENZ-Tipper bekommt: 3×1 = 3 Bonuspunkte
   */
  private function calculateBonusPoints(array &$tipper_points, array $games): void {
    foreach (array_keys($games) as $game_id) {

      // Tipper nach Klasse gruppieren
      $exakt   = [];
      $tendenz = [];
      $falsch  = [];

      foreach ($tipper_points as $tipper_id => $data) {
        $bp = $data['basispunkte'][$game_id] ?? 0;
        if ($bp === 3) {
          $exakt[]   = $tipper_id;
        }
        elseif ($bp === 1) {
          $tendenz[] = $tipper_id;
        }
        else {
          $falsch[]  = $tipper_id;
        }
      }

      $count_tendenz = count($tendenz);
      $count_falsch  = count($falsch);

      foreach ($tipper_points as $tipper_id => &$data) {
        if (in_array($tipper_id, $exakt, TRUE)) {
          $data['bonuspunkte'][$game_id] = ($count_tendenz * 1) + ($count_falsch * 2);
        }
        elseif (in_array($tipper_id, $tendenz, TRUE)) {
          $data['bonuspunkte'][$game_id] = $count_falsch * 1;
        }
        else {
          $data['bonuspunkte'][$game_id] = 0;
        }
      }
      unset($data); // Referenz explizit lösen!
    }
  }

  private function calculateTotals(array &$tipper_points): void {
    foreach ($tipper_points as $tipper_id => &$data) {
      $data['ergebnisse'] = count(array_filter($data['basispunkte'], fn($p) => $p === 3));
      $data['tendenzen']  = count(array_filter($data['basispunkte'], fn($p) => $p === 1));

      foreach (array_keys($data['basispunkte']) as $game_id) {
        $data['totalpergame'][$game_id] =
          ($data['basispunkte'][$game_id]  ?? 0) +
          ($data['bonuspunkte'][$game_id]  ?? 0) +
          ($data['sonderpunkte'][$game_id] ?? 0);
      }

      $data['total'] = array_sum($data['totalpergame']);
    }
    unset($data); // Referenz explizit lösen!
  }

  /**
   * Erstellt eine tipper_id → Rang-Map aus einem Tipper-Points-Array.
   *
   * @return array<int, int>
   */
  private function buildRankMap(array $tipper_points): array {
    if (empty($tipper_points)) {
      return [];
    }
    $rows = [];
    foreach ($tipper_points as $tipper_id => $data) {
      $rows[] = ['tipper_id' => $tipper_id, 'total' => $data['total'], 'ergebnisse' => $data['ergebnisse'], 'tendenzen' => $data['tendenzen']];
    }
    usort($rows, static fn($a, $b) => $b['total'] <=> $a['total'] ?: $b['ergebnisse'] <=> $a['ergebnisse'] ?: $b['tendenzen'] <=> $a['tendenzen']);
    $map = [];
    foreach ($rows as $rank => $row) {
      $map[$row['tipper_id']] = $rank + 1;
    }
    return $map;
  }

}
