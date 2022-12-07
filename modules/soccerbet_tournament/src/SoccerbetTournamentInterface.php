<?php

namespace Drupal\soccerbet_tournament;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a soccerbet tournament entity type.
 */
interface SoccerbetTournamentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the soccerbet tournament title.
   *
   * @return string
   *   Title of the soccerbet tournament.
   */
  public function getName();

  /**
   * Sets the soccerbet tournament title.
   *
   * @param string $title
   *   The soccerbet tournament title.
   *
   * @return \Drupal\soccerbet_tournament\SoccerbetTeamInterface
   *   The called soccerbet tournament entity.
   */
  public function setName($name);

  /**
   * Returns the tournament's start and end date.
   *
   * @return array
   *   The start and end date of this tournament.
   */
  public function getStartAndEndDate();

  /**
   * Sets the StartDate of tournament.
   *
   * @param string $start_date
   *   Timestamp of the start date of this tournament.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setStartAndEndDate($start_date);

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
   * Gets the soccerbet tournament creation timestamp.
   *
   * @return int
   *   Creation timestamp of the soccerbet tournament.
   */
  public function getCreatedTime();

  /**
   * Sets the soccerbet tournament creation timestamp.
   *
   * @param int $timestamp
   *   The soccerbet tournament creation timestamp.
   *
   * @return \Drupal\soccerbet_tournament\SoccerbetTeamInterface
   *   The called soccerbet tournament entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the soccerbet tournament status.
   *
   * @return bool
   *   TRUE if the soccerbet tournament is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the soccerbet tournament status.
   *
   * @param bool $status
   *   TRUE to enable this soccerbet tournament, FALSE to disable.
   *
   * @return \Drupal\soccerbet_tournament\SoccerbetTeamInterface
   *   The called soccerbet tournament entity.
   */
  public function setStatus($status);

}
