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


class TournamentViewBuilder extends EntityViewBuilder {

  /**
   * This hook is used to display the TournamentTeamRelations and the games beneath the tournament.
   * jQuery Accordion is used for the groups.
   * jQuery Tabs is used for separating the standings from the games.
   *
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    parent::alterBuild($build, $entity, $display, $view_mode);
    //$relations = entity_load_multiple_by_properties('soccerbet_tournament_team', array('tournament_id' => $entity->id()));
    //$team_build = \Drupal::entityManager()->getViewBuilder('soccerbet_tournament_team')->viewMultiple($relations);

    /*$build['#soccerbet_tournament_tables'] = array(
      '#theme' => 'soccerbet_tournament_tables',
      '#title' => t('Group Standings'),
      '#tables' => $team_build,
      '#attached' => array(
        'library' => array(
          'soccerbet/soccerbet.tournament_table_accordion',
        )
      )
    );*/

    /*$games = entity_load_multiple_by_properties('soccerbet_game', array('tournament_id' => $entity->id()));
    $game_build = \Drupal::entityManager()->getViewBuilder('soccerbet_game')->viewMultiple($games);

    $build['#soccerbet_tournament_games'] = array(
      '#theme' => 'soccerbet_tournament_games',
      '#table' => $game_build,
      '#attached' => array(
        'library' => array(
          'soccerbet/soccerbet.tournament_tabs',
        )
      )
    );*/
    $build['#logo'] = $build['logo'];
    $build['#name'] = $build['name'];
    $build['#start_date'] = Drupal::service('date.formatter')->format(strtotime($entity->start_date->value), 'custom', 'D, j. M Y', 0) ;
    $build['#end_date'] = Drupal::service('date.formatter')->format(strtotime($entity->end_date->value), 'custom', 'D, j. M Y', 0);
    $build['#attached']['library'][] = 'soccerbet/soccerbet.tournament';
    //kint($build);
  }
}
