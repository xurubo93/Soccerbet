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
use Drupal\soccerbet\ParticipantGroupInterface;
use Drupal\soccerbet\ParticipantGroupRelationInterface;
use Drupal\soccerbet\TipperInterface;
use Drupal\soccerbet\TournamentInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Participant-Group entity Relation class. This is actually an associative relation between the participant
 * and a group
 *
 * @ContentEntityType(
 *   id = "soccerbet_participant_group_relation",
 *   label = @Translation("Participant Group Relation"),
 *   handlers = {
 *     "storage_schema" = "Drupal\soccerbet\TournamentTeamRelationStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\ParticipantGroupRelationListBuilder",
 *     "access" = "Drupal\soccerbet\ParticipantGroupRelationAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\ParticipantGroupRelationForm",
 *       "edit" = "Drupal\soccerbet\Form\ParticipantGroupRelationForm",
 *       "delete" = "Drupal\soccerbet\Form\ParticipantGroupRelationDeleteForm",
 *     },
 *   },
 *   base_table = "soccerbet_participant_group_relation",
 *   admin_permission = "administer soccerbet",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "description"
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/participant/group/{soccerbet_participant_group_relation}",
 *     "edit-form" = "/soccerbet/participant/{soccerbet_participant}/group/{soccerbet_participant_group_relation}/edit",
 *     "delete-form" = "/soccerbet/participant/{soccerbet_participant}/group/{soccerbet_participant_group_relation}delete",
 *     "collection" = "/soccerbet/participant/group/list"
 *   }
 * )
 */

class ParticipantGroupRelation extends ContentEntityBase implements ParticipantGroupRelationInterface {

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
  public function getParticipantID() {
    return $this->get('participant_id')->participant_id;
  }

  /**
   *{@inheritdoc}
   */
  public function setParticipantID($participant_id) {
    $this->set('participant_id', $participant_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getParticipant() {
    return $this->get('participant_id')->entity;
  }


  /**
   *{@inheritdoc}
   */
  public function setParticipant(ParticipantInterface $participant) {
    $this->set('participant_id', $participant->id());
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getUserGroupID() {
    return $this->get('usergroup_id')->target_id;
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
  public function getParticipantgroup() {
    return $this->get('participantgroup_id')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setParticipantgroupID($participantgroup_id) {
    $this->set('participantgroup_id', $participantgroup_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function setParticipantgroup(ParticipantgroupInterface $participantgroup) {
    $this->set('participantgroup_id', $participantgroup->id());
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
  public function setOwner(ParticipantInterface $account) {
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
    return (bool) $this->get('participant_has_payed')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setPaid($paid) {
    $this->set('participant_has_payed', $paid ? PARTICIPANT_HAS_PAID : PARTICIPANT_HAS_NOT_PAID);
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
      ->setDescription(t('The entity_id of the participant group relation.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of this participant.'))
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
      ->setDescription(t('The tournament this participant and group belongs to'))
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
      ->setLabel(t('End Result of the participant'))
      ->setDescription(t('The end result of this participant in this tournament'))
      ->setDefaultValueCallback('Drupal\soccerbet\ParticipantGroupRelation::getParticipantCount')
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
      ->setDescription(t('A boolean indicating whether the participant has payed his stakes.'))
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
      ->setDescription(t('The time that the participant was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the participant was last edited.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * Default value callback for 'end_result' base field definition
   *
   * @see ::baseFieldDefitions()
   *
   * @return integer
   *   An integer of all participants in a specific group of a tournament
   */
  public static function getParticipantCount() {
    //return SqlContentEntityStorage->database->query('SELECT COUNT(*) FROM {soccerbet_participant_group} WHERE participantgroup_id = :participantgroup_id AND tournament_id = :tournament_id', array(':participantgroup_id' => $this->getParticipantgroupID(), ':tournament_id' => $this->getTournamentID()));
  }
}
?>
