<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tipps-Admin-Übersicht.
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

  public function adminOverview(int $tournament_id = 0): array {
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
    $tippers    = $this->tournamentManager->loadTippers($tournament_id);
    $games      = $this->tipperManager->loadGamesByTournament($tournament_id);

    $all_tipps = [];
    foreach ($tippers as $tipper) {
      $all_tipps[(int) $tipper->tipper_id] = $this->tipperManager
        ->loadTippsByTipper((int) $tipper->tipper_id, $tournament_id);
    }

    $header = [$this->t('Bettor')];
    foreach ($games as $game) {
      $header[] = $game->team1_name . ' – ' . $game->team2_name;
    }

    $rows = [];
    foreach ($tippers as $tipper) {
      $row = [$tipper->tipper_name];
      foreach ($games as $game) {
        $tipp  = $all_tipps[(int) $tipper->tipper_id][(int) $game->game_id] ?? NULL;
        $row[] = $tipp ? $tipp->team1_tipp . ':' . $tipp->team2_tipp : '—';
      }
      $rows[] = $row;
    }

    return [
      '#type'   => 'container',
      'heading' => ['#markup' => '<h2>' . $this->t('Bets overview: @name', ['@name' => $tournament->tournament_desc]) . '</h2>'],
      'table'   => [
        '#theme'      => 'table',
        '#header'     => $header,
        '#rows'       => $rows,
        '#empty'      => $this->t('No bets available.'),
        '#sticky'     => TRUE,
        '#attributes' => ['class' => ['soccerbet-tipps-overview']],
      ],
    ];
  }
}
