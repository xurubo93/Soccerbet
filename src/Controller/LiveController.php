<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\soccerbet\Service\ScoringService;
use Drupal\soccerbet\Service\TournamentManager;
use Drupal\soccerbet\Service\WinnerBetService;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Live-Rangliste: zeigt laufende Spiele und Tipper-Tipps in Echtzeit.
 */
final class LiveController extends ControllerBase {

  public function __construct(
    private readonly Connection $db,
    private readonly TournamentManager $tournamentManager,
    private readonly ScoringService $scoring,
    private readonly WinnerBetService $winnerBet,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.scoring'),
      $container->get('soccerbet.winner_bet'),
    );
  }

  /**
   * Hauptseite der Live-Ansicht.
   */
  public function live(int $tournament_id = 0): array {
    $tournament_id = $this->resolveTournamentId($tournament_id);
    if ($tournament_id === 0) {
      return ['#markup' => '<p>' . $this->t('Kein aktives Turnier konfiguriert.') . '</p>'];
    }

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return ['#markup' => '<p>' . $this->t('Turnier nicht gefunden.') . '</p>'];
    }

    $live_games  = $this->loadLiveGames($tournament_id);
    $live_data   = $this->buildLiveData($tournament_id, $live_games);

    return [
      '#theme'          => 'soccerbet_live',
      '#tournament'     => $tournament,
      '#live_games'     => $live_data['games'],
      '#ranking'        => $live_data['ranking'],
      '#is_live'        => !empty($live_games),
      '#final_started'  => $live_data['final_started'],
      '#tournament_id'  => $tournament_id,
      '#attached'       => [
        'library' => ['soccerbet/live'],
      ],
      '#cache'          => ['max-age' => 0],
    ];
  }

  /**
   * AJAX-Endpunkt: gibt aktuelle Live-Daten als JSON zurück.
   */
  public function liveJson(Request $request, int $tournament_id = 0): JsonResponse {
    $tournament_id = $this->resolveTournamentId($tournament_id);
    if ($tournament_id === 0) {
      return new JsonResponse(['error' => 'no tournament'], 404);
    }

    $live_games = $this->loadLiveGames($tournament_id);
    $live_data  = $this->buildLiveData($tournament_id, $live_games);

    return new JsonResponse([
      'is_live'       => !empty($live_games),
      'games'         => $live_data['games'],
      'ranking'       => $live_data['ranking'],
      'final_started' => $live_data['final_started'],
      'updated'       => date('H:i:s'),
    ]);
  }

  /**
   * Lädt gerade laufende Spiele (Anpfiff vor ≤ 120 min, noch kein Ergebnis
   * oder Ergebnis aber noch innerhalb des Zeitfensters).
   */
  private function loadLiveGames(int $tournament_id): array {
    $now        = \Drupal::time()->getRequestTime();
    $window_ago = gmdate('Y-m-d\TH:i:s', $now - 120 * 60); // 2h zurück
    $window_now = gmdate('Y-m-d\TH:i:s', $now);

    $q = $this->db->select('soccerbet_games', 'g');
    $q->fields('g');
    $q->addField('t1', 'team_name', 'team1_name');
    $q->addField('t1', 'team_flag', 'team1_flag');
    $q->addField('t2', 'team_name', 'team2_name');
    $q->addField('t2', 'team_flag', 'team2_flag');
    $q->join('soccerbet_teams', 't1', 'g.team_id_1 = t1.team_id');
    $q->join('soccerbet_teams', 't2', 'g.team_id_2 = t2.team_id');
    $q->condition('g.tournament_id', $tournament_id);
    $q->condition('g.game_date', $window_ago, '>=');
    $q->condition('g.game_date', $window_now, '<=');
    $q->condition('g.published', 1);
    $q->orderBy('g.game_date', 'ASC');

    return $q->execute()->fetchAll();
  }

  /**
   * Baut die vollständige Live-Datestruktur auf:
   * - Spiele mit aktuellem Stand
   * - Rangliste mit Tipper-Tipps für laufende Spiele und Tipp-Bewertung
   */
  private function buildLiveData(int $tournament_id, array $live_games): array {
    $game_ids = array_map(fn($g) => (int) $g->game_id, $live_games);

    // Alle Tipper des Turniers laden
    $tippers_q = $this->db->select('soccerbet_tippers', 'st');
    $tippers_q->fields('st', ['tipper_id', 'tipper_name']);
    $tippers_q->join('soccerbet_tournament_tippers', 'stt',
      'stt.tipper_id = st.tipper_id AND stt.tournament_id = :tid',
      [':tid' => $tournament_id]);
    $tippers_q->orderBy('st.tipper_name');
    $tippers = $tippers_q->execute()->fetchAllAssoc('tipper_id');

    // Tipps für laufende Spiele laden
    $tipps_by_tipper = [];
    if (!empty($game_ids)) {
      $tipps = $this->db->select('soccerbet_tipps', 'stp')
        ->fields('stp', ['tipper_id', 'game_id', 'team1_tipp', 'team2_tipp'])
        ->condition('stp.game_id', $game_ids, 'IN')
        ->execute()->fetchAll();

      foreach ($tipps as $tipp) {
        $tipps_by_tipper[(int) $tipp->tipper_id][(int) $tipp->game_id] = $tipp;
      }
    }

    // Gesamtpunkte aus ScoringService – inkl. Bonus- und Sonderpunkten.
    $tipper_points_full = $this->scoring->getTipperPoints($tournament_id);
    $scoring_total = [];
    foreach ($tipper_points_full as $tid => $data) {
      $scoring_total[(int) $tid] = (int) $data['total'];
    }

    // Sieger-Sterne: Anzahl Turniersiege pro Tipper
    $stars = [];
    foreach ($this->db->select('soccerbet_tournament_groups', 'tg')
      ->fields('tg', ['winner_tipper_id'])
      ->isNotNull('winner_tipper_id')
      ->execute()->fetchCol() as $winner_id) {
      $stars[(int) $winner_id] = ($stars[(int) $winner_id] ?? 0) + 1;
    }

    // Turniersieger-Tipp: ab Finalanpfiff anzeigen
    $final_started       = $this->winnerBet->isFinalStarted($tournament_id);
    $winner_bets_by_tipper = $final_started
      ? $this->winnerBet->loadBetsKeyedByTipper($tournament_id)
      : [];

    // Spiele aufbereiten
    $games_out = [];
    foreach ($live_games as $game) {
      $games_out[] = [
        'game_id'    => (string) $game->game_id,
        'team1_name' => $game->team1_name,
        'team1_flag' => $game->team1_flag,
        'team2_name' => $game->team2_name,
        'team2_flag' => $game->team2_flag,
        'score1'     => $game->team1_score,
        'score2'     => $game->team2_score,
        'game_date'  => $game->game_date,
      ];
    }

    // Bonus pro Live-Spiel vorberechnen:
    // Für jedes Spiel: wer hat exakt/tendenz/falsch getippt?
    $bonus_map = []; // [game_id][tipper_id] => bonus_points
    foreach ($live_games as $game) {
      $gid = (int) $game->game_id;
      if ($game->team1_score === NULL) {
        continue; // noch kein Ergebnis
      }
      $s1 = (int) $game->team1_score;
      $s2 = (int) $game->team2_score;

      $exakt = $tendenz = $falsch = [];
      foreach ($tippers as $tid => $unused) {
        $tid  = (int) $tid;
        $tipp = $tipps_by_tipper[$tid][$gid] ?? NULL;
        if (!$tipp) { $falsch[] = $tid; continue; }
        $t1 = (int) $tipp->team1_tipp;
        $t2 = (int) $tipp->team2_tipp;
        if ($t1 === $s1 && $t2 === $s2) {
          $exakt[] = $tid;
        }
        elseif (($s1 - $s2 <=> 0) === ($t1 - $t2 <=> 0)) {
          $tendenz[] = $tid;
        }
        else {
          $falsch[] = $tid;
        }
      }
      $cnt_t = count($tendenz);
      $cnt_f = count($falsch);
      foreach ($exakt   as $tid) { $bonus_map[$gid][$tid] = $cnt_t * 1 + $cnt_f * 2; }
      foreach ($tendenz as $tid) { $bonus_map[$gid][$tid] = $cnt_f * 1; }
      foreach ($falsch  as $tid) { $bonus_map[$gid][$tid] = 0; }
    }

    // Rangliste aufbauen
    $ranking = [];
    foreach ($tippers as $tipper_id => $tipper) {
      $tipper_id   = (int) $tipper_id;
      $live_tipps  = [];
      $live_points = 0;

      foreach ($live_games as $game) {
        $gid  = (int) $game->game_id;
        $tipp = $tipps_by_tipper[$tipper_id][$gid] ?? NULL;

        $status      = 'none';
        $tipp_str    = '—';
        $basis       = 0;
        $bonus       = 0;

        if ($tipp) {
          $t1 = (int) $tipp->team1_tipp;
          $t2 = (int) $tipp->team2_tipp;
          $tipp_str = $t1 . ':' . $t2;

          if ($game->team1_score !== NULL) {
            $s1 = (int) $game->team1_score;
            $s2 = (int) $game->team2_score;

            if ($t1 === $s1 && $t2 === $s2) {
              $status = 'exact';
              $basis  = 3;
            }
            else {
              $gleich = ($s1 - $s2 <=> 0) === ($t1 - $t2 <=> 0);
              if ($gleich) {
                $status = 'tendency';
                $basis  = 1;
              }
              else {
                $status = 'wrong';
              }
            }
            $bonus = $bonus_map[$gid][$tipper_id] ?? 0;
          }
          else {
            $status = 'pending';
          }
        }

        $tipp_points = $basis + $bonus;
        $live_tipps[(string) $gid] = [
          'tipp'   => $tipp_str,
          'status' => $status,
          'points' => $tipp_points,
        ];
        $live_points += $tipp_points;
      }

      // Sind die Live-Spiele bereits im ScoringService berücksichtigt?
      // Nur wenn sie einen eingetragenen Score haben UND published=1.
      // In diesem Fall ist full_total bereits korrekt.
      // Haben sie noch keinen Score, addieren wir live_points manuell.
      $score_in_service = TRUE;
      foreach ($live_games as $game) {
        if ($game->team1_score === NULL) {
          $score_in_service = FALSE;
          break;
        }
      }

      $full_total = $scoring_total[$tipper_id] ?? 0;
      $base_total = $score_in_service
        ? $full_total - $live_points   // ScoringService hat Live bereits drin
        : $full_total;                 // ScoringService hat Live noch nicht

      $total = $score_in_service
        ? $full_total                  // korrekt, nicht nochmals addieren
        : $full_total + $live_points;  // manuell addieren

      // Turniersieger-Bonus ab Finalanpfiff
      $winner_bet        = $winner_bets_by_tipper[$tipper_id] ?? NULL;
      $winner_bet_pts    = $winner_bet ? (int) ($winner_bet->display_points ?? 0) : 0;
      $winner_bet_entry  = $winner_bet ? [
        'team_name'  => $winner_bet->team_name,
        'pts'        => $winner_bet_pts,
        'is_correct' => $winner_bet->is_correct,
        'is_pending' => $winner_bet->is_pending,
      ] : NULL;
      $total += $winner_bet_pts;
      $base_total += $winner_bet_pts; // Bonus in Vorher-Rang einbeziehen

      $ranking[] = [
        'tipper_id'   => $tipper_id,
        'name'        => $tipper->tipper_name,
        'stars'       => $stars[$tipper_id] ?? 0,
        'detail_url'  => \Drupal\Core\Url::fromRoute('soccerbet.standings_tipper', [
          'tournament_id' => $tournament_id,
          'tipper_id'     => $tipper_id,
        ])->toString(),
        'live_tipps'  => $live_tipps,
        'live_points' => $live_points,
        'base_total'  => $base_total,
        'total'       => $total,
        'winner_bet'  => $winner_bet_entry,
      ];
    }

    // Rang VOR Live-Punkten berechnen (für Veränderungsanzeige)
    $ranking_before = $ranking;
    usort($ranking_before, static fn($a, $b) =>
      $b['base_total'] <=> $a['base_total']
      ?: strcmp($a['name'], $b['name'])
    );
    $rank_before_map = [];
    foreach ($ranking_before as $rank => $row) {
      $rank_before_map[$row['tipper_id']] = $rank + 1;
    }

    // Sortierung mit Live-Punkten
    usort($ranking, static fn($a, $b) =>
      $b['total'] <=> $a['total']
      ?: $b['live_points'] <=> $a['live_points']
      ?: strcmp($a['name'], $b['name'])
    );

    // Rang und Veränderung vergeben
    foreach ($ranking as $rank => &$row) {
      $row['rank']        = $rank + 1;
      $rank_before        = $rank_before_map[$row['tipper_id']] ?? ($rank + 1);
      $row['rank_diff']   = $rank_before - ($rank + 1); // positiv = aufgestiegen
    }
    unset($row);

    return ['games' => $games_out, 'ranking' => $ranking, 'final_started' => $final_started];
  }

  private function resolveTournamentId(int $tournament_id): int {
    if ($tournament_id > 0) {
      return $tournament_id;
    }
    return (int) $this->config('soccerbet.settings')->get('default_tournament');
  }
}
