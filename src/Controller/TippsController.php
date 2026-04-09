<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tipps-Übersicht: alle Tipps aller Tipper, transponiert (Spiele = Zeilen).
 */
final class TippsController extends ControllerBase {

  public function __construct(
    private readonly TournamentManager $tournamentManager,
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.tipper_manager'),
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

    // Alle Tipps laden: [tipper_id][game_id] => tipp-Objekt
    $all_tipps = [];
    foreach ($tippers as $tipper) {
      $all_tipps[(int) $tipper->tipper_id] = $this->tipperManager
        ->loadTippsByTipper((int) $tipper->tipper_id, $tournament_id);
    }

    // Header: erste Spalte leer (Spielbezeichnung), dann ein Tipper pro Spalte
    $header = [['data' => $this->t('Match'), 'class' => ['col-game']]];
    foreach ($tippers as $tipper) {
      $header[] = ['data' => $tipper->tipper_name, 'class' => ['col-tipper']];
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

      $game_label = ($date ? '<span class="tipps-ov__date">' . $date . '</span>' : '')
        . '<span class="tipps-ov__teams">'
        . htmlspecialchars((string) $this->t($game->team1_name))
        . ' – '
        . htmlspecialchars((string) $this->t($game->team2_name))
        . '</span>';

      $row = [['data' => ['#markup' => $game_label], 'class' => ['col-game']]];

      foreach ($tippers as $tipper) {
        $tipp = $all_tipps[(int) $tipper->tipper_id][(int) $game->game_id] ?? NULL;
        $row[] = ['data' => $tipp ? $tipp->team1_tipp . ':' . $tipp->team2_tipp : '—', 'class' => ['col-tipp']];
      }

      $rows[] = $row;
    }

    $back_url = Url::fromRoute('soccerbet.standings', ['tournament_id' => $tournament_id])->toString();

    return [
      '#type'     => 'container',
      '#attributes' => ['class' => ['soccerbet-tipps-overview-wrap']],
      'heading'   => ['#markup' => '<h2>' . $this->t('Bets overview: @name', ['@name' => $tournament->tournament_desc]) . '</h2>'],
      'back'      => ['#markup' => '<div class="soccerbet-standings__links"><a href="' . $back_url . '">← ' . $this->t('Back to standings') . '</a></div>'],
      'scroll'    => [
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
    ];
  }
}
