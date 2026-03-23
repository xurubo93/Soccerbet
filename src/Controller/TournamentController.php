<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Turnier-Admin-Controller.
 */
final class TournamentController extends ControllerBase {

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

  public function list(): array {
    $tournaments = $this->tournamentManager->loadAll();
    $formatter   = \Drupal::service('date.formatter');
    $rows = [];

    foreach ($tournaments as $t) {
      // Datumsfelder sind reine Daten (kein Uhrzeitanteil) → UTC-Mitternacht
      $start_ts = $t->start_date
        ? (new \DateTimeImmutable($t->start_date, new \DateTimeZone('UTC')))->getTimestamp()
        : NULL;
      $end_ts = $t->end_date
        ? (new \DateTimeImmutable($t->end_date, new \DateTimeZone('UTC')))->getTimestamp()
        : NULL;

      $rows[] = [
        $t->tournament_desc,
        $start_ts ? $formatter->format($start_ts, 'custom', 'd.m.Y') : '—',
        $end_ts   ? $formatter->format($end_ts,   'custom', 'd.m.Y') : '—',
        $t->is_active ? $this->t('✓ Aktiv') : '—',
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'edit'    => ['title' => $this->t('Bearbeiten'), 'url' => Url::fromRoute('soccerbet.admin.tournament.edit',    ['tournament_id' => $t->tournament_id])],
              'import'  => ['title' => $this->t('⬇ API-Import'), 'url' => Url::fromRoute('soccerbet.admin.tournament.import', ['tournament_id' => $t->tournament_id])],
              'members' => ['title' => $this->t('Teilnehmer'), 'url' => Url::fromRoute('soccerbet.admin.tournament.members', ['tournament_id' => $t->tournament_id])],
              'games'   => ['title' => $this->t('Spiele'),     'url' => Url::fromRoute('soccerbet.admin.games.list',         ['tournament_id' => $t->tournament_id])],
              'delete'  => ['title' => $this->t('Löschen'),    'url' => Url::fromRoute('soccerbet.admin.tournament.delete',  ['tournament_id' => $t->tournament_id])],
            ],
          ],
        ],
      ];
    }

    return [
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ Neues Turnier'),
        '#url'        => Url::fromRoute('soccerbet.admin.tournament.create'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#theme'      => 'table',
        '#header'     => [
          $this->t('Name'),
          ['data' => $this->t('Start'),   'class' => ['priority-medium']],
          ['data' => $this->t('Ende'),    'class' => ['priority-medium']],
          ['data' => $this->t('Status'),  'class' => ['priority-low']],
          $this->t('Aktionen'),
        ],
        '#rows'       => $rows,
        '#empty'      => $this->t('Noch keine Turniere angelegt.'),
        '#attributes' => ['class' => ['responsive-enabled']],
        '#attached'   => ['library' => ['core/drupal.tableresponsive']],
      ],
    ];
  }
}
