<?php
/**
 * @file
 * Contains \Drupal\soccerbet\ParticipantInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\soccerbet\Entity\Team;
use Drupal\soccerbet\Entity\Tip;

/**
 * Provides an interface defining a Participant entity.
 * @ingroup content_entity_example
 */
interface ParticipantInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Returns the tip of the Participant
   *
   * @return Tip
   */
  public function getTipA();

  /**
   * Sets the first tip of the Participant
   *
   * @param $tipA
   * @return $this
   */
  public function setTipA(TipInterface $tipA);

  /**
   * Returns the first tip of the Participant
   *
   * @return Tip
   */
  public function getTipB();

  /**
   * Sets the tip of the Participant
   *
   * @param $tipB
   * @return mixed
   */
  public function setTipB(TipInterface $tipB);


  /**
   * Returns the time that the participant was created.
   *
   * @return int
   *   The timestamp of when the participant was created.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the participant.
   *
   * @param int $created
   *   The timestamp of when the participant was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the participant was changed.
   *
   * @return int
   *   The timestamp of when the participant was changed.
   */
  public function getChangedTime();
}
