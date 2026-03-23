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
      $flag_code  = strtoupper(trim((string) ($team->team_flag ?? '')));
      $flag_lower = strtolower($flag_code);
      $flag_html  = '';
      if ($flag_code) {
        $svg = '/modules/custom/soccerbet/images/flags/svg/' . $flag_lower . '.svg';
        $png = '/modules/custom/soccerbet/images/flags/PNG/2x/' . $flag_code . '@2x.png';
        $flag_html = '<img src="' . $svg . '"'
          . ' onerror="this.onerror=null;this.src=\'' . $png . '\'"'
          . ' alt="' . htmlspecialchars($flag_code) . '"'
          . ' width="28" height="19" style="vertical-align:middle;border:1px solid #eee;margin-right:6px;">';
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
              'edit'   => ['title' => $this->t('Bearbeiten'), 'url' => Url::fromRoute('soccerbet.admin.teams.edit', ['team_id' => $team->team_id])],
              'delete' => ['title' => $this->t('Löschen'),    'url' => Url::fromRoute('soccerbet.admin.teams.delete', ['team_id' => $team->team_id])],
            ],
          ],
        ],
      ];
    }

    return [
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ Neues Team'),
        '#url'        => Url::fromRoute('soccerbet.admin.teams.create', ['tournament_id' => $tournament_id]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#theme'  => 'table',
        '#header' => [
          $this->t('Team'), $this->t('Gruppe'),
          $this->t('Spiele'), $this->t('S/U/N'),
          $this->t('Tore'), $this->t('Punkte'),
          $this->t('Aktionen'),
        ],
        '#rows'  => $rows,
        '#empty' => $this->t('Keine Teams für dieses Turnier.'),
      ],
    ];
  }
}
