<?php
/**
 * Created by PhpStorm.
 * User: peterwindholz
 * Date: 16.02.15
 * Time: 12:26
 */

namespace Drupal\soccerbet;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

interface TipperGroupRelationInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {


  /**
   * Returns the ID of the tipper.
   *
   * @return int
   *   The ID of the tipper, if provided
   */
  public function getTipperID();

  /**
   * Returns the tipper entity of this Relation.
   *
   * @return TipperInterface
   *   The tipper entity of this relation.
   */
  public function getTipper();

  /**
   * Sets the tipper ID to this Relation.
   *
   * @param integer $tipper_id
   *   The tipper ID of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipperID($tipper_id);

  /**
   * Sets the tipper entity to this Relation.
   *
   * @param TipperInterface $tipper
   *   The tipper entity of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTipper(ParticipantInterface $participant);

  /**
   * Returns the ID of the tippergroup.
   *
   * @return int
   *   The ID of the tippergroup
   */
  public function getTippergroupID();

  /**
   * Returns the tippergroup entity of this Relation.
   *
   * @return TipperGroupInterface
   *   The tippergroup entity of this relation.
   */
  public function getTippergroup();

  /**
   * Sets the tippergroup ID to this Relation.
   *
   * @param integer $tippergroup_id
   *   The tippergroup ID of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTippergroupID($tippergroup_id);

  /**
   * Sets the tippergroup entity to this Relation.
   *
   * @param TipperGroupInterface $tippergroup
   *   The tippergroup entity of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTippergroup(TippergroupInterface $tippergroup);

  /**
   * Returns the tournament entity of this Relation.
   *
   * @return TournamentInterface
   *   The tournament entity of this relation.
   */
  public function getTournament();

  /**
   * Returns the tournamentID of this relation.
   *
   * @return int
   *   The entity ID of the tournament
   **/
  public function getTournamentID();

  /**
   * Sets the tournament ID to this relation.
   *
   * @param integer $tournament_id
   *   The tournament ID of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTournamentID($tournament_id);

  /**
   * Sets the tournament entity to this relation.
   *
   * @param TournamentInterface $tournament
   *   The tournament entity of this relation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTournament(TournamentInterface $tournament);

  /**
   * Checks if the tipper has paid for this tournament inside this group.
   *
   * @return bool
   *   TRUE if the tipper has paid his stakes for this tournament inside this group.
   */
  public function isPaid();

  /**
   * Sets the payment status of a tipper inside this group for this tournament
   *
   * @param bool $paid
   *   TRUE to set this tipper as paid, FALSE to set it to not payed.
   *
   * @return $this
   *   The called Relation entity.
   */
  public function setPaid($paid);

  /**
   * Returns the result of the tipper inside this group for this tournament
   *
   * @return int
   *   The result
   */
  public function getEndResult();

  /**
   * Sets the result of the tipper inside this group for this tournament
   *
   * @param $result
   *   The result
   *
   * @return mixed
   *   The class instance that this method is called on
   */
  public function setEndResult($result);

  /**
   * Returns the relations owner.
   *
   * @return UserInterface
   *   The entity object of the Owner.
   */
  public function getOwner();

  /**
   * Returns the relation's owner ID.
   *
   * @return int
   *   The entity ID of the Owner.
   */
  public function getOwnerID();

  /**
   * Sets the Owner of this relation.
   *
   * @param UserInterface $account
   *   Account Object of the owner.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setOwner(UserInterface $account);

  /**
   * Sets the Owner ID of this relation.
   *
   * @param int $user_id
   *   Account ID of the owner.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setOwnerID($user_id);


  /**
   * Returns the time that the relation was created.
   *
   * @return int
   *   The timestamp of when the relation was created.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the relation.
   *
   * @param int $created
   *   The timestamp of when the relation was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the timestamp of when the relation was changed.
   *
   * @return int
   *   The timestamp of when the relation was changed.
   */
  public function getChangedTime();
}
