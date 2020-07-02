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


class ParticipantGroupRelationViewBuilder extends EntityViewBuilder {

  /**
   * This hook is used to display the TournamentTeamRelations and the participant beneath the tournament.
   * jQuery Accordion is used for the groups.
   * jQuery Tabs is used for separating the standings from the participant.
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

    /*$participant = entity_load_multiple_by_properties('soccerbet_participant', array('tournament_id' => $entity->id()));
    $participants_build = \Drupal::entityManager()->getViewBuilder('soccerbet_participant')->viewMultiple($participants);

    $build['#soccerbet_tournament_participants'] = array(
      '#theme' => 'soccerbet_tournament_participants',
      '#table' => $participant_build,
      '#attached' => array(
        'library' => array(
          'soccerbet/soccerbet.tournament_tabs',
        )
      )
    );*/

    $build['#member_has_payed'] = $build['member_has_payed'];
    $build['#attached']['library'][] = 'soccerbet/soccerbet.participant';

    //kint($build);
  }
}
