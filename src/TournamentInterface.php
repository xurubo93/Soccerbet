<?php
/**
 * @file
 * Contains \Drupal\soccerbet\TournamentInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\soccerbet\Entity\Tip;

/**
 * Provides an interface defining a Tournament entity.
 * @ingroup content_entity_example
 */
interface TournamentInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Returns the name of the tournament.
   *
   * @return string
   *   The name of the tournament.
   */
  public function getName();

  /**
   * Sets the name of the tournament.
   *
   * @param string $subject
   *   The name of the tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setName($name);

  /**
   * Returns the tournaments logo.
   *
   * @return
   *   The image object of the tournaments logo
   **/
  //public function getLogo();

  /**
   * Sets the Logo of this tournament.
   *
   * @param $image
   *   Image Object of the logo og this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  //public function setLogo(ImageItem $image);

  /**
   * Returns the tournament's start date.
   *
   * @return integer
   *   The start date of the tournament.
   */
  public function getStartDate();

  /**
   * Sets the StartDate of tournament.
   *
   * @param int $start_date
   *   Timestamp of the start date of this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setStartDate($start_date);

  /**
   * Returns the tournament's end date.
   *
   * @return int
   *   The end date of this tournament.
   */
  public function getEndDate();

  /**
   * Sets the EndDate of tournament.
   *
   * @param int $end_date
   *   Timestamp of the end date of this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setEndDate($end_date);

  /**
   * Checks if the tournament is active.
   *
   * @return bool
   *   TRUE if the tournament is active.
   */
  public function isActive();

  /**
   * Returns the tournaments status.
   *
   * @return int
   *   One of TournamentInterface::ACTIVE or TournamentInterface::NOT_ACTIVE
   */
  public function getStatus();

  /**
   * Sets the status of the tournament entity.
   *
   * @param bool $status
   *   Set to TRUE to activate the tournament, FALSE to deactivate.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setActive($status);

  /**
   * Returns the tournament's group count.
   *
   * @return int
   *   The number of preliminary rounds of this tournament.
   */
  public function getGroupCount();

  /**
   * Sets the group count of this tournament.
   *
   * @param int $group_count
   *   The number of preliminary rounds of this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setGroupCount($group_count);

  /**
   * Returns the tournament's winner.
   *
   * @return int
   *   The tipper id the winner of this tournament.
   */
  public function getTipperInFirstPlace();

  /**
   * Sets the tournament's winner.
   *
   * @param int $tipper_id
   *   The tipper id of the tipper who won this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipperInFirstPlace($tipper_id);

  /**
   * Returns the tournament's second place tipper.
   *
   * @return int
   *   The tipper id of the tipper who came second in this tournament.
   */
  public function getTipperInSecondPlace();

  /**
   * Sets the tournament's second place tipper.
   *
   * @param int $tipper_id
   *   The tipper id of the tipper who came second in this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipperInScondPlace($tipper_id);

  /**
   * Returns the tournament's third place tipper.
   *
   * @return int
   *   The tipper id of the tipper who came third in this tournament.
   */
  public function getTipperInThirdPlace();

  /**
   * Sets the tournament's third place tipper.
   *
   * @param int $tipper_id
   *   The tipper id of the tipper who came third in this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipperInThirdPlace($tipper_id);


  /**
   * Returns the tournament group of this tournament
   *
   * @return Tip
   */
  public function getTournamentGroup();

  /**
   * Sets the tournament group of this tournament
   *
   * @param $tournamentgroup
   * @return $this
   */
  public function setTournamentGroup(TournamentGroupInterface $tournamentgroup);

  /**
   * Returns the tournament group of this tournament
   *
   * @return Tip
   */
  public function getUserGroup();

  /**
   * Sets the tournament group of this tournament
   *
   * @param $usergroup
   * @return $this
   */
  public function setUserGroup(UserGroupInterface $usergroup);



  /**
   * Returns the time that the tournament was created.
   *
   * @return int
   *   The timestamp of when the tournament was created.
   */
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
