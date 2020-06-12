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
 * Provides a list controller for Tournamentgroup entity.
 *
 * @ingroup soccerbet
 */
class TournamentgroupListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = array(
      '#markup' => $this->t('Soccerbet tournamentgroup implements a Tournamentgroup model. You can manage the fields on the <a href="@adminlink">Soccerbet admin page</a>.', array(
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
    $header['tournamentgroup_id'] = $this->t('Tournamentgroup ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\soccerbet\Entity\Tournamentgroup */
    $row['tournamentgroup_id'] = $entity->id();
    $row['name'] = $entity->link();
    return $row + parent::buildRow($entity);
  }
}
