<?php

namespace Drupal\soccerbet\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the soccerbet tournament entity edit forms.
 */
class SoccerbetTournamentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $entity->label()];
    //$message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New soccerbet tournament %label has been created.', $message_arguments));
      $this->logger('soccerbet_tournament')->notice('Created new soccerbet tournament %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The soccerbet tournament %label has been updated.', $message_arguments));
      $this->logger('soccerbet_tournament')->notice('Updated new soccerbet tournament %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.soccerbet_tournament.canonical', ['soccerbet_tournament' => $entity->id()]);
  }

}
