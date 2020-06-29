<?php

/**
 * @file
 * Contains \Drupal\content_entity_example\Entity\Controller\ContentEntityExampleController.
 */

namespace Drupal\soccerbet\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Provides a list controller for Game entity.
 *
 * @ingroup soccerbet
 */
class GameListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = array(
      '#markup' => $this->t('Soccerbet Game implements a Game model. You can manage the fields on the <a href="@adminlink">Soccerbet admin page</a>.', array(
        '@adminlink' => \Drupal::urlGenerator()->generateFromRoute('soccerbet.soccerbet_settings'),
      )),
    );
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['game_id'] = $this->t('Game ID');
    $header['start_time'] = $this->t('Start Time');
    $header['game_location'] = $this->t('Game location');
    $header['game_first_team'] = $this->t('First Team');
    $header['game_second_team'] = $this->t('Second Team');
    $header['score_first_team'] = $this->t('Score first team');
    $header['score_second_team'] = $this->t('Score second team');
    $header['game_type'] = $this->t('Game Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\soccerbet\Entity\Game */
    $row['game_id'] = $entity->id();
    $row['start_time'] = $entity->start_time->value;
    $row['game_location'] = $entity->game_location->value;
    $row['game_first_team'] = $entity->getFirstTeam()->getTeamName();
    $row['game_second_team'] = $entity->getSecondTeam()->getTeamName();
    $row['score_first_team'] = $entity->score_first_team->value;
    $row['score_second_team'] = $entity->score_second_team->value;
    $row['game_type'] = $entity->getGameType();
    //kint($entity->getGameType());
    return $row + parent::buildRow($entity);
  }
}
