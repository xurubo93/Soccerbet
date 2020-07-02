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
   * Returns the Participant ID
   *
   * @return integer
   */
  public function getParticipantID();

  /**
   * Sets the Participant ID
   *
   * @param int $participant_id
   *
   * @return $this
   */
  public function setParticipantID($participant_id);

  /**
   * Returns the Participant
   *
   * @return $this
   */

  public function getParticipant();

  /**
   * Sets the Participant
   *
   * @param $participant
   * @return $this
   */
  public function setParticipant(ParticipantInterface $participant);


  /**
   * Returns the user group ID
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
   * Returns the changed time across translations
   *
   * @return $this
   */

  public function getChangedTimeAcrossTranslations();


  /**
   * Returns the group of the participant
   *
   * @return $this
   */

  public function getParticipantgroup();



  /**
   * Sets the participant ID
   *
   * @param int $participantgroup_id
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setParticipantgroupID($participantgroup_id);

  /**
   * Sets the participant group
   *
   * @param $participantgroup
   * @return $this
   */
  public function setParticipantgroup(ParticipantgroupInterface $participantgroup);

  /**
   * Returns the Tournament ID
   *
   * @return int
   */
  public function getTournamentID();

  /**
   * Returns the time that the game was created.
   *
   * @return string
   */
  public function getTournament();

  /**
   * Sets the Tournament ID
   *
   * @param int $tournament_id
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTournamentID($tournament_id);

  /**
   * Sets the tournament
   *
   * @param $tournament
   * @return $this
   */
  public function setTournament(TournamentInterface $tournament);

  /**
   * Returns the owner
   *
   * @return int
   */
  public function getOwner();

  /**
   * Sets the owner
   *
   * @param $account
   * @return $this
   */
  public function setOwner(ParticipantInterface $account);

  /**
   * Returns the ownder ID
   *
   * @return int
   */
  public function getOwnerID();

  /**
   * Sets the owner ID
   *
   * @param $user_id
   * @return $this
   */
  public function setOwnerID($user_id);

  /**
   * Returns the End result of the game
   *
   * @return int
   *   The timestamp of when the game was created.
   */
  public function getEndResult();

  /**
   * Sets the end result of the game
   *
   * @param $result
   * @return $this
   */
  public function setEndResult($result);

  /**
   * Checks if the tip has been paid
   *
   * @return bool
   *   TRUE if the tip was paid
   */
  public function isPaid();

  /**
   * Sets the status of the payment
   *
   * @param bool $paid
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
