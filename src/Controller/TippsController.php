<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\ScoringService;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Drupal\soccerbet\Service\WinnerBetService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tipps-Übersicht: alle Tipps aller Tipper, transponiert (Spiele = Zeilen).
 */
final class TippsController extends ControllerBase {

  public function __construct(
    private readonly TournamentManager $tournamentManager,
    private readonly TipperManager $tipperManager,
    private readonly ScoringService $scoringService,
    private readonly WinnerBetService $winnerBet,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.scoring'),
      $container->get('soccerbet.winner_bet'),
    );
  }

  public function overview(int $tournament_id = 0): array {
    $tournament_id = (int) $tournament_id;
    if ($tournament_id === 0) {
      $tournament_id = (int) $this->config('soccerbet.settings')->get('default_tournament');
    }

    if ($tournament_id === 0) {
      return ['#markup' => '<p>' . $this->t('No active tournament configured.') . '</p>'];
    }

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return ['#markup' => '<p>' . $this->t('Tournament not found.') . '</p>'];
    }

    $tippers = $this->tournamentManager->loadTippers($tournament_id);
    $games   = $this->tipperManager->loadGamesByTournament($tournament_id);

    // Nur bereits angepfiffene UND gewertete Spiele anzeigen. Von der Wertung
    // ausgenommene Spiele (published = 0) dürfen hier nicht erscheinen, sonst
    // wären die Tipps aller Spieler für diese Partie einsehbar.
    $now_utc = gmdate('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime());
    $games   = array_filter(
      $games,
      fn($g) => !empty($g->game_date) && $g->game_date <= $now_utc && (int) $g->published === 1
    );
    // Letztes Spiel oben – sonst muss man immer nach unten scrollen.
    $games = array_reverse($games);

    // Flag-Codes pro Team-ID für Aufsteiger-Prefix.
    $team_flags = \Drupal::database()->select('soccerbet_teams', 't')
      ->fields('t', ['team_id', 'team_flag'])
      ->condition('t.tournament_id', $tournament_id)
      ->execute()->fetchAllKeyed();

    // Alle Tipps laden: [tipper_id][game_id] => tipp-Objekt
    $all_tipps = [];
    foreach ($tippers as $tipper) {
      $all_tipps[(int) $tipper->tipper_id] = $this->tipperManager
        ->loadTippsByTipper((int) $tipper->tipper_id, $tournament_id);
    }

    // Punkte pro Tipper pro Spiel (nur für bereits gespielte Spiele)
    $scored = $this->scoringService->getTipperPoints($tournament_id);
    // Indiziert als $points[$tipper_id][$game_id]
    $points = [];
    foreach ($scored as $tipper_id => $data) {
      $points[(int) $tipper_id] = $data['totalpergame'] ?? [];
    }

    // Header: erste Spalte "Spiel", dann Tipper-Namen mit Zeilenumbruch am ersten Leerzeichen
    $header = [['data' => ['#markup' => $this->t('Match')], 'class' => ['col-game']]];
    foreach ($tippers as $tipper) {
      $name = htmlspecialchars($tipper->tipper_name);
      // Ersten Leerzeichen durch <br> ersetzen (Vor-/Nachname untereinander)
      $name_wrapped = preg_replace('/ /', '<br>', $name, 1);
      $header[] = ['data' => ['#markup' => $name_wrapped], 'class' => ['col-tipper']];
    }

    // Eine Zeile pro Spiel
    $rows = [];
    foreach ($games as $game) {
      $date = $game->game_date
        ? \Drupal::service('date.formatter')->format(
            (new \DateTimeImmutable($game->game_date, new \DateTimeZone('UTC')))->getTimestamp(),
            'custom', 'd.m.'
          )
        : '';

      $has_result  = ($game->team1_score !== NULL && $game->team2_score !== NULL);
      $has_penalty = ($game->penalty_score_1 ?? NULL) !== NULL
        && ($game->penalty_score_2 ?? NULL) !== NULL;
      $penalty_suffix = $has_penalty
        ? ' <span class="tipps-ov__penalty">(' . (int) $game->penalty_score_1 . ':' . (int) $game->penalty_score_2 . ' ' . $this->t('i.E.') . ')</span>'
        : '';
      $score_label = $has_result
        ? '<span class="tipps-ov__score">' . $game->team1_score . ':' . $game->team2_score . $penalty_suffix . '</span>'
        : '<span class="tipps-ov__score tipps-ov__score--pending">—</span>';

      // Aufsteiger in KO-Spielen fett markieren.
      $is_ko = !in_array($game->phase, ['group', ''], TRUE);
      $winner_id = (int) ($game->winner_team_id ?? 0);
      $team1_class = 'tipps-ov__team';
      $team2_class = 'tipps-ov__team';
      if ($is_ko && $winner_id > 0) {
        if ($winner_id === (int) $game->team_id_1) {
          $team1_class .= ' tipps-ov__team--qualifier';
        }
        elseif ($winner_id === (int) $game->team_id_2) {
          $team2_class .= ' tipps-ov__team--qualifier';
        }
      }

      $game_label = ($date ? '<span class="tipps-ov__date">' . $date . '</span>' : '')
        . '<span class="' . $team1_class . '">' . htmlspecialchars((string) $this->t($game->team1_name)) . '</span>'
        . '<span class="' . $team2_class . '">' . htmlspecialchars((string) $this->t($game->team2_name)) . '</span>'
        . $score_label;

      $row = [['data' => ['#markup' => $game_label], 'class' => ['col-game']]];

      foreach ($tippers as $tipper) {
        $tipp = $all_tipps[(int) $tipper->tipper_id][(int) $game->game_id] ?? NULL;

        if (!$tipp) {
          $row[] = ['data' => '—', 'class' => ['col-tipp', 'tipps-ov__cell--none']];
          continue;
        }

        $label = $tipp->team1_tipp . ':' . $tipp->team2_tipp;
        $winner_id = (int) ($tipp->winner_team_id ?? 0);
        if ($winner_id > 0 && !empty($team_flags[$winner_id])) {
          $label = htmlspecialchars($team_flags[$winner_id]) . ' ' . $label;
        }

        if (!$has_result) {
          $row[] = ['data' => ['#markup' => $label], 'class' => ['col-tipp']];
          continue;
        }

        $css = $this->tippClass(
          (int) $tipp->team1_tipp,   (int) $tipp->team2_tipp,
          (int) $game->team1_score,  (int) $game->team2_score,
        );
        $tipper_id = (int) $tipper->tipper_id;
        $game_id   = (int) $game->game_id;
        $pts = $points[$tipper_id][$game_id] ?? NULL;
        $pts_html = $pts !== NULL
          ? '<br><span class="tipps-ov__pts">(' . $pts . ')</span>'
          : '';
        $row[] = ['data' => ['#markup' => $label . $pts_html], 'class' => ['col-tipp', $css]];
      }

      $rows[] = $row;
    }

    // Zusätzliche Zeile für den Turniersieger-Tipp (ab Finalanpfiff sichtbar,
    // vorher bleibt der getippte Weltmeister geheim). Ganz oben, passend zur
    // umgekehrten Reihenfolge (letztes Spiel zuerst).
    $winner_row = $this->buildWinnerBetRow($tournament_id, $tippers);
    if ($winner_row !== NULL) {
      array_unshift($rows, $winner_row);
    }

    $back_url = Url::fromRoute('soccerbet.standings', ['tournament_id' => $tournament_id])->toString();

    return [
      '#type'       => 'container',
      '#attributes' => ['class' => ['soccerbet-tipps-overview-wrap']],
      'heading'     => ['#markup' => '<h2>' . $this->t('Bets overview: @name', ['@name' => $tournament->tournament_desc]) . '</h2>'],
      'back'        => ['#markup' => '<div class="soccerbet-standings__links"><a href="' . $back_url . '">← ' . $this->t('Back to standings') . '</a></div>'],
      'scroll'      => [
        '#type'       => 'container',
        '#attributes' => ['class' => ['soccerbet-tipps-scroll']],
        'table'       => [
          '#theme'      => 'table',
          '#header'     => $header,
          '#rows'       => $rows,
          '#empty'      => $this->t('No bets available.'),
          '#sticky'     => FALSE,
          '#attributes' => ['class' => ['soccerbet-tipps-overview']],
        ],
      ],
      '#cache'      => ['max-age' => 0],
    ];
  }

  /**
   * Baut die Tabellenzeile für den Turniersieger-Tipp, spaltengleich zu den
   * Spielzeilen. Gibt NULL zurück, solange kein Tipp gewertet wird
   * (display_points === NULL, d.h. vor Finalanpfiff).
   *
   * @param object[] $tippers  Tipper in Spaltenreihenfolge des Headers.
   */
  private function buildWinnerBetRow(int $tournament_id, array $tippers): ?array {
    $bets = $this->winnerBet->loadBetsKeyedByTipper($tournament_id);
    $display = [];
    foreach ($bets as $tid => $bet) {
      if ($bet->display_points !== NULL) {
        $display[(int) $tid] = $bet;
      }
    }
    if (empty($display)) {
      return NULL;
    }

    $row = [[
      'data'  => ['#markup' => '🏆 ' . $this->t('Tournament winner')],
      'class' => ['col-game', 'tipps-ov__winner-cell'],
    ]];

    foreach ($tippers as $tipper) {
      $bet = $display[(int) $tipper->tipper_id] ?? NULL;
      if ($bet === NULL) {
        $row[] = ['data' => '—', 'class' => ['col-tipp', 'tipps-ov__cell--none']];
        continue;
      }
      $team = htmlspecialchars((string) $this->t($bet->team_name));
      $pts  = (int) $bet->display_points;

      if ($bet->is_pending) {
        $css      = 'tipps-ov__cell--tendency';
        $pts_html = '<br><span class="tipps-ov__pts">(' . $pts . '?)</span>';
      }
      elseif ($bet->is_correct) {
        $css      = 'tipps-ov__cell--exact';
        $pts_html = '<br><span class="tipps-ov__pts">(+' . $pts . ')</span>';
      }
      else {
        // Falsch getippt oder ausgeschieden → 0 Punkte.
        $css      = 'tipps-ov__cell--wrong';
        $pts_html = '<br><span class="tipps-ov__pts">(0)</span>';
      }
      $row[] = ['data' => ['#markup' => $team . $pts_html], 'class' => ['col-tipp', $css]];
    }

    return $row;
  }

  /**
   * CSS-Klasse für eine Tipp-Zelle basierend auf dem Spielergebnis.
   */
  private function tippClass(int $t1, int $t2, int $s1, int $s2): string {
    if ($t1 === $s1 && $t2 === $s2) {
      return 'tipps-ov__cell--exact';
    }
    $tipp_tend   = $t1 <=> $t2;   // -1, 0, +1
    $result_tend = $s1 <=> $s2;
    return $tipp_tend === $result_tend ? 'tipps-ov__cell--tendency' : 'tipps-ov__cell--wrong';
  }
}
