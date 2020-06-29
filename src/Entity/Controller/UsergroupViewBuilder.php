<?php
/**
 * Created by PhpStorm.
 * User: peterwindholz
 * Date: 10.03.15
 * Time: 13:32
 */

namespace Drupal\soccerbet\Entity\Controller;


use Drupal;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;


class UsergroupViewBuilder extends EntityViewBuilder {

  /**
   * This hook is used to display the UsergroupTeamRelations and the games beneath the usergroup.
   * jQuery Accordion is used for the groups.
   * jQuery Tabs is used for separating the standings from the games.
   *
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    parent::alterBuild($build, $entity, $display, $view_mode);
    //$relations = entity_load_multiple_by_properties('soccerbet_usergroup_team', array('usergroup_id' => $entity->id()));
    //$team_build = \Drupal::entityManager()->getViewBuilder('soccerbet_usergroup_team')->viewMultiple($relations);

    /*$build['#soccerbet_usergroup_tables'] = array(
      '#theme' => 'soccerbet_usergroup_tables',
      '#title' => t('Group Standings'),
      '#tables' => $team_build,
      '#attached' => array(
        'library' => array(
          'soccerbet/soccerbet.usergroup_table_accordion',
        )
      )
    );*/

    /*$games = entity_load_multiple_by_properties('soccerbet_game', array('usergroup_id' => $entity->id()));
    $game_build = \Drupal::entityManager()->getViewBuilder('soccerbet_game')->viewMultiple($games);

    $build['#soccerbet_usergroup_games'] = array(
      '#theme' => 'soccerbet_usergroup_games',
      '#table' => $game_build,
      '#attached' => array(
        'library' => array(
          'soccerbet/soccerbet.usergroup_tabs',
        )
      )
    );*/
    $build['#attached']['library'][] = 'soccerbet/soccerbet.usergroup';
    //kint($build);
  }
}
