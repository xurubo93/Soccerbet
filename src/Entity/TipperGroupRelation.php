<?php
/**
 * Created by PhpStorm.
 * User: peterwindholz
 * Date: 16.02.15
 * Time: 12:23
 *
 * TODO:
 */

namespace Drupal\soccerbet\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\soccerbet\ParticipantInterface;
use Drupal\soccerbet\TippergroupInterface;
use Drupal\soccerbet\TipperGroupRelationInterface;
use Drupal\soccerbet\TipperInterface;
use Drupal\soccerbet\TournamentInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Tipper-Group entity Relation class. This is actually an associative relation between the tipper
 * and a group
 *
 * @ContentEntityType(
 *   id = "soccerbet_tipper_group",
 *   label = @Translation("Tipper Group Relation"),
 *   handlers = {
 *     "storage_schema" = "Drupal\soccerbet\TournamentTeamRelationStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TipperGroupRelationListBuilder",
 *     "access" = "Drupal\soccerbet\TipperGroupRelationAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TipperGroupRelationForm",
 *       "edit" = "Drupal\soccerbet\Form\TipperGroupRelationForm",
 *       "delete" = "Drupal\soccerbet\Form\TipperGroupRelationDeleteForm",
 *     },
 *   },
 *   base_table = "soccerbet_tipper_group",
 *   admin_permission = "administer soccerbet",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "description"
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/tipper/group/{soccerbet_tipper_group}",
 *     "edit-form" = "/soccerbet/tipper/{soccerbet_tipper}/group/{soccerbet_tipper_group}/edit",
 *     "delete-form" = "/soccerbet/tipper/{soccerbet_tipper}/group/{soccerbet_tipper_group}delete",
 *     "collection" = "/soccerbet/tipper/group/list"
 *   }
 * )
 */

class TipperGroupRelation extends ContentEntityBase implements TipperGroupRelationInterface {

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'uid' => \Drupal::currentUser()->id(),
    );
  }

  /**
   *{@inheritdoc}
   */
  public function getTipperID() {
    return $this->get('tipper_id')->target_id;
  }

  /**
   *{@inheritdoc}
   */
  public function getTipper() {
    return $this->get('tipper_id')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setTipperID($tipper_id) {
    $this->set('tipper_id', $tipper_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function setTipper(ParticipantInterface $participant) {
    $this->set('tipper_id', $participant->id());
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getTippergroupID() {
    return $this->get('tippergroup_id')->target_id;
  }

  public function setChangedTime($timestamp)
  {
    // TODO: Implement setChangedTime() method.
  }

  public function getChangedTimeAcrossTranslations()
  {
    // TODO: Implement getChangedTimeAcrossTranslations() method.
  }

  /**
   *{@inheritdoc}
   */
  public function getTippergroup() {
    return $this->get('tippergroup_id')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setTippergroupID($tippergroup_id) {
    $this->set('tippergroup_id', $tippergroup_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function setTippergroup(TippergroupInterface $tippergroup) {
    $this->set('tippergroup_id', $tippergroup->id());
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getTournamentID() {
    return $this->get('tournament_id')->target_id;
  }

  /**
   *{@inheritdoc}
   */
  public function getTournament() {
    return $this->get('tournament_id')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setTournamentID($tournament_id) {
    $this->set('tournament_id', $tournament_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function setTournament(TournamentInterface $tournament) {
    $this->set('tournament_id', $tournament->id());
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function getOwnerID() {
    return $this->get('uid')->target_id;
  }

  /**
   *{@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function setOwnerID($user_id) {
    $this->set('uid', $user_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getEndResult() {
    return $this->get('end_result')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setEndResult($result) {
    $this->set('end_result', $result);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function isPaid() {
    return (bool) $this->get('tipper_has_payed')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setPaid($paid) {
    $this->set('tipper_has_payed', $paid ? TIPPER_HAS_PAID : TIPPER_HAS_NOT_PAID);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setCreatedTime($created) {
    $this->set('created', $created);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the tipper group relation.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of this tipper.'))
      ->setSetting('target_type', 'soccerbet_participant')
      ->setDefaultValue(0)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'entity_reference',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'above',
        'type' => 'options_select',
        'disabled' => TRUE,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['membergroup_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Membergroup ID'))
      ->setDescription(t('The ID of the Membership Group.'))
      ->setSetting('target_type', 'soccerbet_usergroup')
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'entity_reference',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'hidden',
        'type' => 'options_select',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tournament_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tournament ID'))
      ->setDescription(t('The tournament this tipper and group belongs to'))
      ->setSetting('target_type', 'soccerbet_tournament')
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'hidden',
      ))
      ->setDisplayOptions('form', array(
        'label' => 'hidden',
        'type' => 'options_select',
        'weight' => -20,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['end_result'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('End Result of the tipper'))
      ->setDescription(t('The end result of this tipper in this tournament'))
      ->setDefaultValueCallback('Drupal\soccerbet\TipperGroupRelation::getTipperCount')
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'hidden',
      ))
      ->setDisplayOptions('form', array(
        'label' => 'hidden',
        'type' => 'integer',
      ))
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['member_has_payed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Member has payed'))
      ->setDescription(t('A boolean indicating whether the tipper has payed his stakes.'))
      ->setDefaultValue(FALSE)
      ->setSettings(array(
        'on_label' => t('Paid'),
        'off_label' => t('Not Paid'),
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'radios',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'inline',
        'type' => 'radios',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the tipper was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the tipper was last edited.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * Default value callback for 'end_result' base field definition
   *
   * @see ::baseFieldDefitions()
   *
   * @return integer
   *   An integer of all tippers in a specific group of a tournament
   */
  public static function getTipperCount() {
    //return SqlContentEntityStorage->database->query('SELECT COUNT(*) FROM {soccerbet_tipper_group} WHERE tippergroup_id = :tippergroup_id AND tournament_id = :tournament_id', array(':tippergroup_id' => $this->getTippergroupID(), ':tournament_id' => $this->getTournamentID()));
  }
}
?>
