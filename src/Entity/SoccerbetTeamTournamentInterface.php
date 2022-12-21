<?php

namespace Drupal\soccerbet\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\soccerbet\Entity\SoccerbetTeam;
use Drupal\soccerbet\Entity\SoccerbetTournament;

/**
 * Provides an interface defining a soccerbet team entity type.
 */
interface SoccerbetTeamTournamentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the soccerbet team.
   *
   * @return SoccerbetTeam
   *   The Team related to a Tournament.
   */
  public function getTeam(): SoccerbetTeamInterface;

  /**
   * Sets the Soccerbet Team Entity.
   *
   * @param \Drupal\soccerbet\Entity\SoccerbetTeam $soccerbetTeam
   *
   * @return $this
   *   The called soccerbet team tournament relation.
   */
  public function setTeam(SoccerbetTeamInterface $soccerbetTeam): SoccerbetTeamTournamentInterface;

  /**
   * Gets the soccerbet Tournament.
   *
   * @return SoccerbetTournament
   *   The Tournament for this Relation
   */
  public function getTournament(): SoccerbetTournamentInterface;


  /**
   * Sets the Tournament of this relation.
   *
   * @param \Drupal\soccerbet\Entity\SoccerbetTournament $soccerbetTournament
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTournament(SoccerbetTournament $soccerbetTournament): SoccerbetTeamTournamentInterface;


  /**
   * Returns the Group of this team in this tournament
   *
   * @return string
   */
  public function getGroup(): string;

  /**
   * Sets the Group of this Team in this Tournament
   *
   * @param string $group
   *   The group in which this team is drawn.
   *
   * @return $this
   */
  public function setGroup(string $group): SoccerbetTeamTournamentInterface;

  /**
   * @return int
   */
  public function getGamesPlayed(): int;

  /**
   * @param int $games_played
   *
   * @return $this
   */
  public function setGamesPlayed(int $games_played): SoccerbetTeamTournamentInterface;

  /**
   * @return int
   */
  public function getGamesWon(): int;

  /**
   * @param int $games_won
   *
   * @return $this
   */
  public function setGamesWon(int $games_won): SoccerbetTeamTournamentInterface;

  /**
   * @return int
   */
  public function getGamesDrawn(): int;

  /**
   * @param int $games_drawn
   *
   * @return $this
   */
  public function setGamesDrawn(int $games_drawn): SoccerbetTeamTournamentInterface;

  /**
   * @return int
   */
  public function getGamesLost(): int;

  /**
   * @param int $games_lost
   *
   * @return $this
   */
  public function setGamesLost(int $games_lost): SoccerbetTeamTournamentInterface;

  /**
   * @return string
   */
  public function getGoalDifference(): string;

  /**
   * @param string $goal_difference
   *
   * @return $this
   */
  public function setGoalDifference(string $goal_difference): SoccerbetTeamTournamentInterface;

  /**
   * @return int
   */
  public function getPoints(): int;

  /**
   * @param int $points
   *
   * @return $this
   */
  public function setPoints(int $points): SoccerbetTeamTournamentInterface;

  /**
   * Gets the creation timestamp of this relation.
   *
   * @return int
   *   Creation timestamp of the soccerbet team tournament relation.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the soccerbet team tournament creation timestamp.
   *
   * @param int $timestamp
   *   The soccerbet team creation timestamp.
   *
   * @return $this
   *   The called soccerbet team entity.
   */
  public function setCreatedTime(int $timestamp): SoccerbetTeamTournamentInterface;
}