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
    $now = gmdate('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime());
    return (bool) $this->db->select('soccerbet_games', 'g')
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.phase', 'final')
      ->condition('g.game_date', $now, '<=')
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Gibt TRUE zurück wenn das Finale ein eingetragenes Ergebnis hat.
   */
  public function isFinalFinished(int $tournament_id): bool {
    return (bool) $this->db->select('soccerbet_games', 'g')
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.phase', 'final')
      ->condition('g.team1_score', NULL, 'IS NOT')
      ->countQuery()->execute()->fetchField();
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
   * Speichert/aktualisiert den Turniersieger-Tipp.
   */
  public function saveBet(int $tournament_id, int $tipper_id, int $team_id): void {
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

    // Turniersieger-Team (falls bereits bekannt)
    $tournament = $this->db->select('soccerbet_tournament', 't')
      ->fields('t', ['winner_tipper_id'])
      ->condition('t.tournament_id', $tournament_id)
      ->execute()->fetchObject();

    // Gewinner-Team aus finalem Spielergebnis ermitteln
    $winner_team_id = $this->resolveWinnerTeamId($tournament_id);

    $final_started  = $this->isFinalStarted($tournament_id);
    $final_finished = $this->isFinalFinished($tournament_id);

    $result = [];
    foreach ($rows as $row) {
      $possible_points = $this->getPointsForPhaseIndex((int) $row->phase_index);
      $is_correct      = $winner_team_id && ((int) $row->team_id === $winner_team_id);
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

      $result[] = (object) [
        'tipper_id'       => (int) $row->tipper_id,
        'tipper_name'     => $tipper_rows[$row->tipper_id] ?? '?',
        'team_id'         => (int) $row->team_id,
        'team_name'       => $team_rows[$row->team_id] ?? '?',
        'phase_index'     => (int) $row->phase_index,
        'possible_points' => $possible_points,
        'actual_points'   => $actual_points,
        'display_points'  => $display_points, // ab Finalanpfiff != NULL
        'is_correct'      => $is_correct,
        'is_pending'      => $final_started && !$final_finished,
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
   * Ermittelt die Team-ID des Turniersiegers anhand des Finalspiels.
   */
  private function resolveWinnerTeamId(int $tournament_id): ?int {
    $final = $this->db->select('soccerbet_games', 'g')
      ->fields('g', ['team_id_1', 'team_id_2', 'team1_score', 'team2_score'])
      ->condition('g.tournament_id', $tournament_id)
      ->condition('g.phase', 'final')
      ->condition('g.team1_score', NULL, 'IS NOT')
      ->execute()
      ->fetchObject();

    if (!$final) {
      return NULL;
    }
    if ((int) $final->team1_score > (int) $final->team2_score) {
      return (int) $final->team_id_1;
    }
    if ((int) $final->team2_score > (int) $final->team1_score) {
      return (int) $final->team_id_2;
    }
    return NULL; // Unentschieden (sollte im Finale nicht vorkommen)
  }

}
