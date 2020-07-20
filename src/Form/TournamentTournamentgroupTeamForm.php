<?php
/**
 * @file
 * Contains Drupal\content_entity_example\Form\ContactForm.
 */

namespace Drupal\soccerbet\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the soccerbet_tournament_group_team entity edit forms.
 *
 * @ingroup soccerbet
 */
class TournamentTournamentgroupTeamForm extends ContentEntityForm {

  protected $entityStorage;

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    $form_state->setRedirect('entity.soccerbet_tournament_group_team.collection');

    return $status;
  }
}
