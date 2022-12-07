<?php

namespace Drupal\soccerbet_team;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the soccerbet team entity type.
 */
class SoccerbetTeamAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view soccerbet team');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit soccerbet team', 'administer soccerbet team'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete soccerbet team', 'administer soccerbet team'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create soccerbet team', 'administer soccerbet team'], 'OR');
  }

}
