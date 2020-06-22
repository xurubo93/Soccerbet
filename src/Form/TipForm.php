<?php
/**
 * @file
 * Contains Drupal\content_entity_example\Form\ContactForm.
 */

namespace Drupal\soccerbet\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the soccerbet_tip entity edit forms.
 *
 * @ingroup soccerbet
 */
class TipForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\soccerbet\Entity\Tip */
    $form = parent::buildForm($form, $form_state);
    /*$entity = $this->entity;

    $form['langcode'] = array(
      '#title' => $this->t('Language'),
      '#type' => 'language_select',
      '#default_value' => $entity->getUntranslated()->language()->getId(),
      '#languages' => Language::STATE_ALL,
    );*/
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.soccerbet_tip.collection');
    $entity = $this->getEntity();
    $entity->save();
  }
}
