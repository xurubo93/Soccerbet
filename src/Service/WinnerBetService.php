<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Service für Turniersieger-Tipps.
 *
 * Punktelogik: Je weniger Phasen bereits abgeschlossen sind, desto mehr Punkte.
 * phase_index = Anzahl abgeschlossener Phasen zum Zeitpunkt der Tipp-Abgabe.
 * Punkte werden aus winner_bet_points[phase_index] gelesen (konfigurierbar).
 */
final class WinnerBetService {

  public function __construct(
    private readonly Connection $db,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  // ------------------------------------------------------------------ //
  // Phase-Erkennung                                                      //
  // ------------------------------------------------------------------ //

  /**
   * Gibt die Anzahl bereits abgeschlossener Phasen zurück.
   * 0 = vor Turnierstart, 1 = Gruppenphase beendet, usw.
   */
  public function getCurrentPhaseIndex(int $tournament_id): int {
    $phases = $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['phase'])
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.team1_score', NULL, 'IS NOT')
      ->groupBy('g.phase')
      ->execute()
      ->fetchCol();
    return count($phases);
  }

  /**
   * Gibt die Punkte für einen phase_index zurück.
   */
  public function getPointsForPhaseIndex(int $phase_index): int {
    $points = $this->configFactory->get('soccerbet.settings')->get('winner_bet_points') ?? [10, 7, 5, 3, 1];
    $index  = min($phase_index, count($points) - 1);
    return (int) ($points[$index] ?? 0);
  }

  /**
   * Gibt TRUE zurück wenn das Finale bereits angepfiffen wurde.
   */
  public function isFinalStarted(int $tournament_id): bool {
    $game = $this->loadFinalGame($tournament_id);
    if (!$game) {
      return FALSE;
    }
    $now = gmdate('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime());
    return $game->game_date <= $now;
  }

  /**
   * Gibt TRUE zurück wenn das Finale ein eingetragenes Ergebnis hat.
   */
  public function isFinalFinished(int $tournament_id): bool {
    $game = $this->loadFinalGame($tournament_id);
    return $game !== NULL && $game->team1_score !== NULL;
  }

  // ------------------------------------------------------------------ //
  // CRUD                                                                 //
  // ------------------------------------------------------------------ //

  /**
   * Lädt den Turniersieger-Tipp eines Tippers.
   */
  public function loadBet(int $tournament_id, int $tipper_id): ?object {
    return $this->db->select('soccerbet_winner_tipp', 'wt')
      ->fields('wt')
      ->condition('wt.tournament_id', $tournament_id)
      ->condition('wt.tipper_id', $tipper_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  /**
   * Speichert/aktualisiert den Turniersieger-Tipp. Bleibt der Tipp identisch
   * zum bestehenden, wird nichts geschrieben — sonst würde phase_index auf
   * die kleinere Punktzahl der aktuellen Phase fallen.
   */
  public function saveBet(int $tournament_id, int $tipper_id, int $team_id): void {
    $existing = $this->loadBet($tournament_id, $tipper_id);
    if ($existing && (int) $existing->team_id === $team_id) {
      return;
    }
    $phase_index = $this->getCurrentPhaseIndex($tournament_id);
    $this->db->merge('soccerbet_winner_tipp')
      ->keys(['tournament_id' => $tournament_id, 'tipper_id' => $tipper_id])
      ->fields([
        'team_id'     => $team_id,
        'phase_index' => $phase_index,
        'changed_at'  => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Gibt alle Tipps eines Turniers zurück, angereichert mit Tipper- und Team-Name.
   * Rückgabe: array of objects mit tipper_name, team_name, phase_index, possible_points, actual_points
   */
  public function loadBetsForTournament(int $tournament_id): array {
    $rows = $this->db->select('soccerbet_winner_tipp', 'wt')
      ->fields('wt', ['tipper_id', 'team_id', 'phase_index'])
      ->condition('wt.tournament_id', $tournament_id)
      ->execute()
      ->fetchAll();

    if (empty($rows)) {
      return [];
    }

    // Tipper-Namen
    $tipper_ids  = array_column($rows, 'tipper_id');
    $tipper_rows = $this->db->select('soccerbet_tippers', 't')
      ->fields('t', ['tipper_id', 'tipper_name'])
      ->condition('t.tipper_id', $tipper_ids, 'IN')
      ->execute()->fetchAllKeyed();

    // Team-Namen
    $team_ids  = array_unique(array_column($rows, 'team_id'));
    $team_rows = $this->db->select('soccerbet_teams', 't')
      ->fields('t', ['team_id', 'team_name'])
      ->condition('t.team_id', $team_ids, 'IN')
      ->execute()->fetchAllKeyed();

    // Gewinner-Team aus finalem Spielergebnis ermitteln
    $winner_team_id = $this->resolveWinnerTeamId($tournament_id);

    $final_started  = $this->isFinalStarted($tournament_id);
    $final_finished = $this->isFinalFinished($tournament_id);
    $eliminated     = $this->loadEliminatedTeams($tournament_id);

    $result = [];
    foreach ($rows as $row) {
      $team_id       = (int) $row->team_id;
      $is_eliminated = isset($eliminated[$team_id]);

      if ($is_eliminated) {
        // Team ist im KO ausgeschieden → kein Bonus mehr möglich.
        $possible_points = 0;
        $actual_points   = 0;
        $display_points  = 0;
        $is_correct      = FALSE;
        $is_pending      = FALSE;
      }
      else {
        $possible_points = $this->getPointsForPhaseIndex((int) $row->phase_index);
        $is_correct      = $winner_team_id && ($team_id === $winner_team_id);
        $actual_points   = $final_finished ? ($is_correct ? $possible_points : 0) : NULL;

        // display_points: ab Finalanpfiff sichtbar
        // – Finale läuft/gestartet, kein Ergebnis: possible_points (ausstehend)
        // – Finale beendet: actual_points (0 oder possible_points)
        // – Vor Finalanpfiff: NULL
        $display_points = NULL;
        if ($final_finished) {
          $display_points = $actual_points;
        }
        elseif ($final_started) {
          $display_points = $possible_points; // ausstehend
        }
        $is_pending = $final_started && !$final_finished;
      }

      $result[] = (object) [
        'tipper_id'       => (int) $row->tipper_id,
        'tipper_name'     => $tipper_rows[$row->tipper_id] ?? '?',
        'team_id'         => $team_id,
        'team_name'       => $team_rows[$team_id] ?? '?',
        'phase_index'     => (int) $row->phase_index,
        'possible_points' => $possible_points,
        'actual_points'   => $actual_points,
        'display_points'  => $display_points, // ab Finalanpfiff != NULL
        'is_correct'      => $is_correct,
        'is_pending'      => $is_pending,
        'is_eliminated'   => $is_eliminated,
      ];
    }

    // Sortierung: korrekte zuerst, dann nach möglichen Punkten
    usort($result, function ($a, $b) {
      if ($a->is_correct !== $b->is_correct) {
        return $a->is_correct ? -1 : 1;
      }
      return $b->possible_points - $a->possible_points;
    });

    return $result;
  }

  /**
   * Wie loadBetsForTournament(), aber als array<tipper_id, object>.
   */
  public function loadBetsKeyedByTipper(int $tournament_id): array {
    $bets = $this->loadBetsForTournament($tournament_id);
    $result = [];
    foreach ($bets as $bet) {
      $result[$bet->tipper_id] = $bet;
    }
    return $result;
  }

  // ------------------------------------------------------------------ //
  // Hilfsmethoden                                                        //
  // ------------------------------------------------------------------ //

  /**
   * Liefert die IDs aller Teams, die im KO als Verlierer feststehen und
   * damit als Weltmeister-Kandidat ausgeschieden sind. Static-Cache pro
   * Request, damit mehrfaches loadBetsForTournament() nur einmal abfragt.
   *
   * @return array<int, TRUE> team_id => TRUE
   */
  private function loadEliminatedTeams(int $tournament_id): array {
    static $cache = [];
    if (array_key_exists($tournament_id, $cache)) {
      return $cache[$tournament_id];
    }

    $rows = $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['team_id_1', 'team_id_2', 'team1_score', 'team2_score', 'winner_team_id'])
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.phase', ['round_of_32', 'round_of_16', 'quarter', 'semi', 'final'], 'IN')
      ->isNotNull('g.team1_score')
      ->execute()->fetchAll();

    $eliminated = [];
    foreach ($rows as $row) {
      $t1     = (int) $row->team_id_1;
      $t2     = (int) $row->team_id_2;
      $s1     = (int) $row->team1_score;
      $s2     = (int) $row->team2_score;
      $winner = (int) ($row->winner_team_id ?? 0);

      if ($winner === 0) {
        if ($s1 > $s2)      { $winner = $t1; }
        elseif ($s2 > $s1)  { $winner = $t2; }
        else                { continue; }  // Draw ohne winner → noch offen
      }

      $loser = ($winner === $t1) ? $t2 : $t1;
      $eliminated[$loser] = TRUE;
    }
    return $cache[$tournament_id] = $eliminated;
  }

  /**
   * Ermittelt die Team-ID des Turniersiegers anhand des Finalspiels.
   */
  private function resolveWinnerTeamId(int $tournament_id): ?int {
    $final = $this->loadFinalGame($tournament_id);
    if (!$final || $final->team1_score === NULL) {
      return NULL;
    }
    // KO-Entscheidung nach Verlängerung/Elfmeterschießen: expliziter Sieger.
    // Bei einem 120-Min-Remis steht der Weltmeister nur hier, nicht im Score.
    if (!empty($final->winner_team_id)) {
      return (int) $final->winner_team_id;
    }
    if ((int) $final->team1_score > (int) $final->team2_score) {
      return (int) $final->team_id_1;
    }
    if ((int) $final->team2_score > (int) $final->team1_score) {
      return (int) $final->team_id_2;
    }
    return NULL; // Remis ohne eingetragenen Sieger → noch offen
  }

  /**
   * Lädt das Finalspiel einmalig pro Request (static-Cache).
   */
  private function loadFinalGame(int $tournament_id): ?object {
    static $cache = [];
    if (!array_key_exists($tournament_id, $cache)) {
      $cache[$tournament_id] = $this->db->select('soccerbet_games', 'g')
        ->fields('g', ['game_date', 'team1_score', 'team2_score', 'team_id_1', 'team_id_2', 'winner_team_id'])
        ->condition('g.tournament_id', $tournament_id)
        ->condition('g.phase', 'final')
        ->execute()->fetchObject() ?: NULL;
    }
    return $cache[$tournament_id];
  }

}
