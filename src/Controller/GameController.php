<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Spiele-Admin-Controller.
 */
final class GameController extends ControllerBase {

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

  private const PHASE_ORDER = [
    'group', 'round_of_32', 'round_of_16', 'quarter', 'semi', 'third_place', 'final',
  ];

  public function list(int $tournament_id = 0): array {
    $tournament_id = (int) $tournament_id;
    if ($tournament_id === 0) {
      $tournament_id = (int) $this->config('soccerbet.settings')->get('default_tournament');
    }

    $tournament_options = $this->tournamentManager->getOptions();
    $tournament_name = $tournament_options[$tournament_id] ?? $this->t('Unknown');

    $phase_labels = [
      'group'       => $this->t('Group stage'),
      'round_of_32' => $this->t('Round of 32'),
      'round_of_16' => $this->t('Round of 16'),
      'quarter'     => $this->t('Quarter-final'),
      'semi'        => $this->t('Semi-final'),
      'third_place' => $this->t('Third-place match'),
      'final'       => $this->t('Final'),
    ];

    $header = [
      $this->t('Matchup'), $this->t('Actions'),
    ];

    // Spiele nach Phase gruppieren
    $by_phase = [];
    foreach ($this->tipperManager->loadGamesByTournament($tournament_id) as $g) {
      $by_phase[$g->phase][] = $g;
    }

    $build = [
      'info' => [
        '#markup' => '<p class="soccerbet-admin-hint">' . $this->t('Tournament: <strong>@name</strong>', ['@name' => $tournament_name]) . '</p>',
      ],
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ New match'),
        '#url'        => Url::fromRoute('soccerbet.admin.games.create', ['tournament_id' => $tournament_id]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];

    foreach (self::PHASE_ORDER as $phase) {
      if (empty($by_phase[$phase])) {
        continue;
      }

      $rows = [];
      foreach ($by_phase[$phase] as $g) {
        $score = ($g->team1_score !== NULL)
          ? $g->team1_score . ':' . $g->team2_score
          : '—';

        $date = $g->game_date
          ? \Drupal::service('date.formatter')->format(
              (new \DateTimeImmutable($g->game_date, new \DateTimeZone('UTC')))->getTimestamp(),
              'short'
            )
          : '—';

        $matchup = $this->flagImg($g->team1_flag ?? '')
          . htmlspecialchars((string) $this->t($g->team1_name))
          . ' : '
          . htmlspecialchars((string) $this->t($g->team2_name))
          . ' ' . $this->flagImg($g->team2_flag ?? '');

        $cell = '<div class="soccerbet-admin-game__date">' . $date . '</div>'
          . '<div class="soccerbet-admin-game__matchup">' . $matchup . '</div>'
          . '<div class="soccerbet-admin-game__score">' . $score . '</div>';

        $rows[] = [
          ['data' => ['#markup' => $cell]],
          [
            'data' => [
              '#type'  => 'operations',
              '#links' => [
                'score'  => ['title' => $this->t('Result'), 'url' => Url::fromRoute('soccerbet.admin.games.score',  ['game_id' => $g->game_id])],
                'edit'   => ['title' => $this->t('Edit'),   'url' => Url::fromRoute('soccerbet.admin.games.edit',   ['game_id' => $g->game_id])],
                'delete' => ['title' => $this->t('Delete'), 'url' => Url::fromRoute('soccerbet.admin.games.delete', ['game_id' => $g->game_id])],
              ],
            ],
          ],
        ];
      }

      $build['phase_' . $phase] = [
        '#type'  => 'details',
        '#title' => $phase_labels[$phase] ?? $phase,
        '#open'  => $phase === 'group',
        'table'  => [
          '#theme'      => 'table',
          '#header'     => $header,
          '#rows'       => $rows,
          '#attributes' => ['class' => ['soccerbet-admin-games']],
        ],
      ];
    }

    return $build;
  }

  private function flagImg(string $code): string {
    if ($code === '') {
      return '';
    }
    $module_path = \Drupal::service('extension.list.module')->getPath('soccerbet');
    $src = htmlspecialchars('/' . $module_path . '/images/flags/svg/' . $code . '.svg');
    return '<img src="' . $src . '" alt="' . htmlspecialchars($code) . '" width="20" height="20" class="soccerbet-flag">';
  }
}
