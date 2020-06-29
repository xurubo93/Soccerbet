<?php
/**
 * @file
 * Contains \Drupal\soccerbet\UsergroupInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Provides an interface defining a Usergroup entity.
 * @ingroup content_entity_example
 */
interface UsergroupInterface extends ContentEntityInterface, EntityChangedInterface {

  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the tournament was changed.
   *
   * @return int
   *   The timestamp of when the tournament was changed.
   */
  public function getChangedTime();
}
