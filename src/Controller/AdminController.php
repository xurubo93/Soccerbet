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
      ['title' => $this->t('Turniere verwalten'),          'route' => 'soccerbet.admin.tournament.list'],
      ['title' => $this->t('Teams verwalten'),             'route' => 'soccerbet.admin.teams.list'],
      ['title' => $this->t('Spiele verwalten'),            'route' => 'soccerbet.admin.games.list'],
      ['title' => $this->t('Tippergruppen'),               'route' => 'soccerbet.admin.tippergroups.list'],
      ['title' => $this->t('Zahlungen verwalten'),         'route' => 'soccerbet.admin.payments'],
      ['title' => $this->t('Tipps-Übersicht'),             'route' => 'soccerbet.admin.tipps.overview'],
      ['title' => $this->t('Score-Update (OpenLigaDB)'),   'route' => 'soccerbet.admin.score_update'],
      ['title' => $this->t('Einstellungen'),               'route' => 'soccerbet.settings'],
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
      '#title' => $this->t('Tippspiel-Administration'),
    ];
  }
}
