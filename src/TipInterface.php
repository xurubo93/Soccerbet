<?php
/**
 * @file
 * Contains \Drupal\soccerbet\TipInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Provides an interface defining a Tip entity.
 * @ingroup content_entity_example
 */
interface TipInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Returns the value of the tip.
   *
   * @return string
   *   The name of the tip.
   */
  public function getTipTeamA();

  /**
   * Sets the value of the tip.
   *
   * @param integer $tip_team_A
   *   The name of the tip.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipTeamA($tip_team_A);

    /**
     * Returns the value of the tip.
     *
     * @return integer
     *   The name of the tip.
     */
    public function getTipTeamB();

    /**
     * Sets the value of the tip.
     *
     * @param string $tip_team_B
     *   The name of the tip.
     *
     * @return $this
     *   The class instance that this method is called on.
     */
    public function setTipTeamB($tip_team_B);



    public function getCreatedTime();

  /**
   * Sets the creation date of the tip.
   *
   * @param int $created
   *   The timestamp of when the tip was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the tip was changed.
   *
   * @return int
   *   The timestamp of when the tip was changed.
   */
  public function getChangedTime();
}
