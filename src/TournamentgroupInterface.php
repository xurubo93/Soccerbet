<?php
/**
 * @file
 * Contains \Drupal\soccerbet\TournamentgroupInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Provides an interface defining a Tournamentgroup entity.
 * @ingroup content_entity_example
 */
interface TournamentgroupInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Returns the name of the tournamentgroup.
   *
   * @return string
   *   The name of the tournamentgroup.
   */
  public function getName();

  /**
   * Sets the name of the tournamentgroup.
   *
   * @param string $tournamentgroupname
   *   The name of the tournamentgroup.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setName($tournamentgroupname);


  /**
   * Returns the time that the tournamentgroup was created.
   *
   * @return int
   *   The timestamp of when the tournamentgroup was created.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the tournamentgroup.
   *
   * @param int $created
   *   The timestamp of when the tournamentgroup was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the tournamentgroup was changed.
   *
   * @return int
   *   The timestamp of when the tournamentgroup was changed.
   */
  public function getChangedTime();
}
