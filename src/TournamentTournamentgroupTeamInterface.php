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
interface TournamentTournamentgroupTeamInterface extends ContentEntityInterface, EntityChangedInterface {

   /**
   * Returns the Team ID
   *
   * @return integer
   */
  public function getTeamID();

  /**
   * Sets the Team ID
   *
   * @param int $team_id
   *
   * @return $this
   */
  public function setTeamID($team_id);

  /**
   * Returns the Participant
   *
   * @return $this
   */


  /**
   * Returns the Tournament ID
   *
   * @return integer
   */
  public function getTournamentID();

  /**
   * Sets the Tournament ID
   *
   * @param int $tournament_id
   *
   * @return $this
   */
  public function setTournamentID($tournament_id);

  /**
   * Returns the tournament ID
   *
   * @return $this
   */

  /**
   * Returns the Tournament group ID
   *
   * @return integer
   */
  public function getTournamentgroupID();

  /**
   * Sets the Tournament group ID
   *
   * @param int $tournamentgroup_id
   *
   * @return $this
   */
  public function setTournamentgroupID($tournamentgroup_id);

  /**
   * Returns the tournament group ID
   *
   * @return $this
   */



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
