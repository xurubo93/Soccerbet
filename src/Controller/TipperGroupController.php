<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tippergruppen-Admin-Controller.
 */
final class TipperGroupController extends ControllerBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tipper_manager'));
  }

  public function list(): array {
    $groups = $this->tipperManager->loadAllGroups();
    $rows   = [];
    foreach ($groups as $g) {
      $admin_user = \Drupal::service('entity_type.manager')
        ->getStorage('user')->load($g->tipper_admin_id);
      $members = $this->tipperManager->loadTippersByGroup((int) $g->tipper_grp_id);

      $rows[] = [
        $g->tipper_grp_name,
        $admin_user?->getDisplayName() ?? '—',
        count($members),
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'edit'   => ['title' => $this->t('Bearbeiten'), 'url' => Url::fromRoute('soccerbet.admin.tippergroups.edit', ['tipper_grp_id' => $g->tipper_grp_id])],
              'delete' => ['title' => $this->t('Löschen'),    'url' => Url::fromRoute('soccerbet.admin.tippergroups.edit', ['tipper_grp_id' => $g->tipper_grp_id])],
            ],
          ],
        ],
      ];
    }

    return [
      'create_link' => [
        '#type'       => 'link',
        '#title'      => $this->t('+ Neue Tippergruppe'),
        '#url'        => Url::fromRoute('soccerbet.admin.tippergroups.create'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#theme'  => 'table',
        '#header' => [$this->t('Name'), $this->t('Administrator'), $this->t('Mitglieder'), $this->t('Aktionen')],
        '#rows'   => $rows,
        '#empty'  => $this->t('Keine Tippergruppen angelegt.'),
      ],
    ];
  }
}
