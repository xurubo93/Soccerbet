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

  public function list(int $tournament_id = 0): array {
    $tournament_id = (int) $tournament_id;
    if ($tournament_id === 0) {
      $tournament_id = (int) $this->config('soccerbet.settings')->get('default_tournament');
    }

    $tournament_options = $this->tournamentManager->getOptions();
    $tournament_name = $tournament_options[$tournament_id] ?? $this->t('Unbekannt');
    $select = [
      '#markup' => '<p class="soccerbet-admin-hint">' . $this->t('Turnier: <strong>@name</strong>', ['@name' => $tournament_name]) . '</p>',
    ];

    $games = $this->tipperManager->loadGamesByTournament($tournament_id);
    $rows  = [];
    foreach ($games as $g) {
      $score = ($g->team1_score !== NULL)
        ? $g->team1_score . ':' . $g->team2_score
        : $this->t('—');

      $rows[] = [
        ($g->game_date
          ? \Drupal::service('date.formatter')->format(
              (new \DateTimeImmutable($g->game_date, new \DateTimeZone('UTC')))->getTimestamp(),
              'short'
            )
          : '—'),
        $g->team1_name . ' vs. ' . $g->team2_name,
        $g->game_stadium ?: '—',
        $g->phase,
        $score,
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'score'  => ['title' => $this->t('Ergebnis'),    'url' => Url::fromRoute('soccerbet.admin.games.score',  ['game_id' => $g->game_id])],
              'edit'   => ['title' => $this->t('Bearbeiten'),  'url' => Url::fromRoute('soccerbet.admin.games.edit',   ['game_id' => $g->game_id])],
              'delete' => ['title' => $this->t('Löschen'),     'url' => Url::fromRoute('soccerbet.admin.games.delete', ['game_id' => $g->game_id])],
            ],
          ],
        ],
      ];
    }

    return [
      'select'      => $select,
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ Neues Spiel'),
        '#url'        => Url::fromRoute('soccerbet.admin.games.create', ['tournament_id' => $tournament_id]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#theme'  => 'table',
        '#header' => [$this->t('Datum'), $this->t('Paarung'), $this->t('Stadion'), $this->t('Phase'), $this->t('Ergebnis'), $this->t('Aktionen')],
        '#rows'   => $rows,
        '#empty'  => $this->t('Keine Spiele in diesem Turnier.'),
      ],
    ];
  }
}
