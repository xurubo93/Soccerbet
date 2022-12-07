<?php

namespace Drupal\soccerbet_team\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the soccerbet team entity edit forms.
 */
class SoccerbetTeamForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New soccerbet team %label has been created.', $message_arguments));
      $this->logger('soccerbet_team')->notice('Created new soccerbet team %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The soccerbet team %label has been updated.', $message_arguments));
      $this->logger('soccerbet_team')->notice('Updated new soccerbet team %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.soccerbet_team.canonical', ['soccerbet_team' => $entity->id()]);
  }

}
