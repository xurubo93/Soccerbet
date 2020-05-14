<?php
/**
 * @file
 * Contains Drupal\soccerbet_tipper\Form\TipperSettingsForm.
 */

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SoccerbetSettingsForm.
 * @package Drupal\soccerbet\Form
 * @ingroup soccerbet
 */
class SoccerbetSettingsForm extends FormBase {
  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'soccerbet_settings';
  }

  /**
   * Form submission handler.
   *
   * @param FormStateInterface $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
  }


  /**
   * Define the form used for Soccerbet settings.
   * @return array
   *   Form definition array.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['soccerbet_settings']['#markup'] = 'Settings form for Soccerbet. Manage field settings here.';
    return $form;
  }
}