<?php

namespace Drupal\soccerbet\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a soccerbet team entity type.
 */
interface SoccerbetTeamInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the soccerbet team title.
   *
   * @return string
   *   Title of the soccerbet team.
   */
  public function getTitle(): string;

  /**
   * Sets the soccerbet team title.
   *
   * @param string $title
   *   The soccerbet team title.
   *
   * @return \Drupal\soccerbet\Entity\SoccerbetTeamInterface
   *   The called soccerbet team entity.
   */
  public function setTitle(string $title): SoccerbetTeamInterface;

  /**
   * Returns the team's abbreviation.
   *
   * @return string
   *   The name code of this Team. This is used for building the path to the logo of this team
   */
  public function getTeamNameCode(): string;

  /**
   * Sets the TeamNameCode of the team.
   *
   * @param string $team_name_code
   *   The namecode of this team.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTeamNameCode(string $team_name_code): SoccerbetTeamInterface;

  /**
   * Gets the soccerbet team creation timestamp.
   *
   * @return int
   *   Creation timestamp of the soccerbet team.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the soccerbet team creation timestamp.
   *
   * @param int $timestamp
   *   The soccerbet team creation timestamp.
   *
   * @return \Drupal\soccerbet\Entity\SoccerbetTeamInterface
   *   The called soccerbet team entity.
   */
  public function setCreatedTime(int $timestamp): SoccerbetTeamInterface;

  /**
   * Returns the soccerbet team status.
   *
   * @return bool
   *   TRUE if the soccerbet team is enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Sets the soccerbet team status.
   *
   * @param bool $status
   *   TRUE to enable this soccerbet team, FALSE to disable.
   *
   * @return \Drupal\soccerbet\Entity\SoccerbetTeamInterface
   *   The called soccerbet team entity.
   */
  public function setStatus(bool $status): SoccerbetTeamInterface;

}
