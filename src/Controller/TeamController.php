<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Team-Verwaltung Controller.
 */
final class TeamController extends ControllerBase {

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

  public function list(int $tournament_id = 0): array {
    $tournament_id = (int) $tournament_id;
    if ($tournament_id === 0) {
      $tournament_id = (int) $this->config('soccerbet.settings')->get('default_tournament');
    }

    $teams      = $this->tipperManager->loadTeamsByTournament($tournament_id);
    $rows       = [];

    foreach ($teams as $team) {
      $flag_code = trim((string) ($team->team_flag ?? ''));
      $flag_html  = '';
      if ($flag_code) {
        $svg = '/modules/custom/soccerbet/images/flags/svg/' . $flag_code . '.svg';
        $flag_html = '<img src="' . $svg . '"'
          . ' alt="' . htmlspecialchars($flag_code) . '"'
          . ' width="24" height="24"'
          . ' class="soccerbet-flag"'
          . '>';
      }

      $rows[] = [
        ['data' => ['#markup' => $flag_html . htmlspecialchars($team->team_name)]],
        $team->team_group ?: '—',
        $team->games_played,
        $team->games_won . '/' . $team->games_drawn . '/' . $team->games_lost,
        $team->goals_shot . ':' . $team->goals_got,
        $team->points,
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'edit'   => ['title' => $this->t('Edit'),   'url' => Url::fromRoute('soccerbet.admin.teams.edit', ['team_id' => $team->team_id])],
              'delete' => ['title' => $this->t('Delete'), 'url' => Url::fromRoute('soccerbet.admin.teams.delete', ['team_id' => $team->team_id])],
            ],
          ],
        ],
      ];
    }

    return [
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ New team'),
        '#url'        => Url::fromRoute('soccerbet.admin.teams.create', ['tournament_id' => $tournament_id]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#theme'  => 'table',
        '#header' => [
          $this->t('Team'), $this->t('Group'),
          $this->t('Matches'), $this->t('W/D/L'),
          $this->t('Goals'), $this->t('Points'),
          $this->t('Actions'),
        ],
        '#rows'  => $rows,
        '#empty' => $this->t('No teams for this tournament.'),
      ],
    ];
  }
}
