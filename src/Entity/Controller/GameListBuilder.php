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
    $header['name'] = $this->t('Name');
    $header['start_time'] = $this->t('Start Time');
    $header['game_location'] = $this->t('Game location');
    $header['score_first_team'] = $this->t('scorefirst team');
    $header['score_second_team'] = $this->t('score second team');
    $header['KO_game'] = $this->t('K.O. game');
    $header['group_game'] = $this->t('group game');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\soccerbet\Entity\Game */
    $row['game_id'] = $entity->id();
    $row['name'] = $entity->link();
    $row['start_time'] = $entity->start_time->value;
    $row['game_location'] = $entity->game_location>value;
    $row['score_first_team'] = $entity->score_first_team->value;
    $row['score_second_team'] = $entity->score_second_team->value;
    $row['KO_game'] = $entity->score_second_team->value;
    $row['group_game'] = $entity->score_second_team->value;
    return $row + parent::buildRow($entity);
  }
}
