<?php
/**
 * @file
 * Contains \Drupal\soccerbet\Controller\SoccerbetController
 **/

namespace Drupal\soccerbet\Controller;
use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the soccerbet module
 **/
class SoccerbetController extends ControllerBase {
  /**
   * Returns a simple page
   *
   * @return array
   * A simple renderable array.
   **/
  public function myPage() {
    $config = \Drupal::config('soccerbet');

    $element = array(
      '#markup' => 'Hy Bro',
    );
    return $element;
  }
}
