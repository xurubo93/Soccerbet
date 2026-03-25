<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gruppentabellen-Seite.
 */
final class TablesController extends ControllerBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function tables(int $tournament_id = 0): array {
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
    $teams      = $this->tipperManager->loadTeamsByTournament($tournament_id);

    $groups = [];
    foreach ($teams as $team) {
      $group_key = $team->team_group ?: '_';
      $groups[$group_key][] = $team;
    }

    foreach ($groups as &$group_teams) {
      usort($group_teams, static function (object $a, object $b): int {
        $diff_a = $a->goals_shot - $a->goals_got;
        $diff_b = $b->goals_shot - $b->goals_got;
        return $b->points   <=> $a->points
          ?: $diff_b        <=> $diff_a
          ?: $b->goals_shot <=> $a->goals_shot;
      });
    }
    ksort($groups);

    return [
      '#theme'      => 'soccerbet_tables',
      '#groups'     => $groups,
      '#tournament' => $tournament,
      '#cache'      => [
        'tags'    => ['soccerbet_standings:' . $tournament_id],
        'max-age' => 120,
      ],
    ];
  }
}
