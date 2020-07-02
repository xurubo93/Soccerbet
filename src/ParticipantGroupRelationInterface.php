<?php
/**
 * @file
 * Contains \Drupal\soccerbet\gameInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\soccerbet\Entity\Team;

/**
 * Provides an interface defining a game entity.
 * @ingroup content_entity_example
 */
interface ParticipantGroupRelationInterface extends ContentEntityInterface, EntityChangedInterface {

   /**
   * Returns the game's start time.
   *
   * @return integer
   */
  public function getParticipantID();

  /**
   * Sets the StartTime of game.
   *
   * @param int $participant_id
   *
   * @return $this
   */
  public function setParticipantID($participant_id);

  /**
   * Returns the first team of the game
   *
   * @return $this
   */

  public function getParticipant();

  /**
   * Sets the first team of this game
   *
   * @param $participant
   * @return $this
   */
  public function setParticipant(ParticipantInterface $participant);


  /**
   * Returns the first team of the game
   *
   * @return $this
   */

  public function getUserGroupID();


  /**
   * Sets the creation date of the game.
   *
   * @param int $timestamp
   *   The timestamp of when the game was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setChangedTime($timestamp);


  /**
   * Returns the first team of the game
   *
   * @return $this
   */

  public function getChangedTimeAcrossTranslations();


  /**
   * Returns the first team of the game
   *
   * @return $this
   */

  public function getParticipantgroup();



  /**
   * Sets the creation date of the game.
   *
   * @param int $participantgroup_id
   *   The timestamp of when the game was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setParticipantgroupID($participantgroup_id);

  /**
   * Sets the first team of this game
   *
   * @param $participantgroup
   * @return $this
   */
  public function setParticipantgroup(ParticipantgroupInterface $participantgroup);

  /**
   * Returns the time that the game was created.
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getTournamentID();

  /**
   * Returns the time that the game was created.
   *
   * @return string
   *   The timestamp of when the game was created.
   */
  public function getTournament();

  /**
   * Sets the creation date of the game.
   *
   * @param int $tournament_id
   *   The timestamp of when the game was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTournamentID($tournament_id);

  /**
   * Sets the first team of this game
   *
   * @param $tournament
   * @return $this
   */
  public function setTournament(TournamentInterface $tournament);

  /**
   * Returns the time that the game was created.
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getOwner();

  /**
   * Sets the first team of this game
   *
   * @param $account
   * @return $this
   */
  public function setOwner(ParticipantInterface $account);

  /**
   * Returns the time that the game was created.
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getOwnerID();

  /**
   * Sets the first team of this game
   *
   * @param $user_id
   * @return $this
   */
  public function setOwnerID($user_id);

  /**
   * Returns the time that the game was created.
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getEndResult();

  /**
   * Sets the first team of this game
   *
   * @param $result
   * @return $this
   */
  public function setEndResult($result);

  /**
   * Checks if the tournament is active.
   *
   * @return bool
   *   TRUE if the tournament is active.
   */
  public function isPaid();

  /**
   * Sets the status of the tournament entity.
   *
   * @param bool $paid
   *   Set to TRUE to activate the tournament, FALSE to deactivate.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setPaid($paid);


  /**
   * Returns the time that the game was created.
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the game.
   *
   * @param int $created
   *   The timestamp of when the game was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the game was changed.
   *
   * @return int
   *   The timestamp of when the game was changed.
   */
  public function getChangedTime();
}
