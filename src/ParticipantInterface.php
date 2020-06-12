<?php
/**
 * @file
 * Contains \Drupal\soccerbet\ParticipantInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Provides an interface defining a Participant entity.
 * @ingroup content_entity_example
 */
interface ParticipantInterface extends ContentEntityInterface, EntityChangedInterface {


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
