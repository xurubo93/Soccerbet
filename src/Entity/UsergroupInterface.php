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

  /**
   * Returns the name of the tournament.
   *
   * @return string
   *   The name of the tournament.
   */
  public function getUsergroupName();

  /**
   * Sets the name of the tournament.
   *
   * @param string $usergroup_name
   *   The name of the tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setUsergroupName($usergroup_name);


  public function getCreatedTime();

  /**
   * Sets the creation date of the tournament.
   *
   * @param int $created
   *   The timestamp of when the tournament was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the tournament was changed.
   *
   * @return int
   *   The timestamp of when the tournament was changed.
   */
  public function getChangedTime();
}
