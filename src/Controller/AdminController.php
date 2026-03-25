<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Admin-Übersichtsseite.
 */
final class AdminController extends ControllerBase {

  public function overview(): array {
    $links = [
      ['title' => $this->t('Manage tournaments'),           'route' => 'soccerbet.admin.tournament.list'],
      ['title' => $this->t('Manage teams'),                'route' => 'soccerbet.admin.teams.list'],
      ['title' => $this->t('Manage matches'),              'route' => 'soccerbet.admin.games.list'],
      ['title' => $this->t('Betting groups'),              'route' => 'soccerbet.admin.tippergroups.list'],
      ['title' => $this->t('Manage payments'),             'route' => 'soccerbet.admin.payments'],
      ['title' => $this->t('Bets overview'),               'route' => 'soccerbet.admin.tipps.overview'],
      ['title' => $this->t('Score update (OpenLigaDB)'),   'route' => 'soccerbet.admin.score_update'],
      ['title' => $this->t('Settings'),                    'route' => 'soccerbet.settings'],
    ];

    $items = [];
    foreach ($links as $link) {
      $items[] = [
        '#type'  => 'link',
        '#title' => $link['title'],
        '#url'   => Url::fromRoute($link['route']),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Betting game administration'),
    ];
  }
}
