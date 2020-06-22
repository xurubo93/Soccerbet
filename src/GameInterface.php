<?php
/**
 * @file
 * Contains \Drupal\soccerbet\gameInterface.
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Provides an interface defining a game entity.
 * @ingroup content_entity_example
 */
interface GameInterface extends ContentEntityInterface, EntityChangedInterface {


    /**
     * Returns the game's start time.
     *
     * @return integer
     */
    public function getName();
    /**
     * Sets the StartTime of game.
     *
     * @param int $name
     *
     * @return $this
     */
    public function setName($name);


    /**
   * Returns the game's start time.
   *
   * @return integer
   */
  public function getStartTime();
  /**
   * Sets the StartTime of game.
   *
   * @param int $start_time
   *
   * @return $this
   */
  public function setStartTime($start_time);



  /**
   * Returns the score of the first team.
   *
   * @return integer
   */
  public function getScoreFirstTeam();
  /**
   * Sets the score of the first team.
   *
   * @param int $score_first_team
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setScoreFirstTeam($score_first_team);



  /**
   * Returns the score of the second team.
   *
   * @return integer
   */
  public function getScoreSecondTeam();
  /**
   *
   * @param int $score_second_team
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setScoreSecondTeam($score_second_team);



  /**
   * Returns the location of the game.
   *
   * @return string
   */
  public function getGameLocation();
  /**
   *
   * @param string $game_location
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setGameLocation($game_location);

  /**
   * Returns the type of the game.
   *
   * @return string
   */
  public function getKOGame();
  /**
   *
   * @param string $KO_game
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setGroupGame($group_game);

  /**
   * Returns the type of the game.
   *
   * @return string
   */
  public function getGroupGame();
  /**
   *
   * @param string $group_game
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setKOGame($group_game);




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
