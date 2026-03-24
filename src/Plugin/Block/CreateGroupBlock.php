<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;

/**
 * Block mit Button zum Erstellen eines Gratis-Tippspiels.
 *
 * @Block(
 *   id = "soccerbet_create_group",
 *   admin_label = @Translation("Soccerbet: Gratis-Tippspiel erstellen"),
 *   category = @Translation("Soccerbet"),
 * )
 */
final class CreateGroupBlock extends BlockBase {

  public function build(): array {
    $url = Url::fromRoute('soccerbet.register')->toString();

    return [
      '#markup' => '<a href="' . $url . '" class="button button--primary soccerbet-create-group-btn">'
        . $this->t('Gratis-Tippspiel erstellen')
        . '</a>',
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  public function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'create soccerbet group')
      ->cachePerPermissions();
  }

}
