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
use Drupal\soccerbet\TeamInterface;
use Drupal\soccerbet\TipperInterface;
use Drupal\soccerbet\TournamentInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Participant-Group entity Relation class. This is actually an associative relation between the tournament,
 * tournamentgroup and teams
 *
 * @ContentEntityType(
 *   id = "soccerbet_tournament_group_team",
 *   label = @Translation("Tournament, Tournamentgroup and Team Relation"),
 *   handlers = {
 *     "storage_schema" = "Drupal\soccerbet\TournamentTournamentgroupTeamRelationStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TournamentTournamentgroupTeamListBuilder",
 *     "access" = "Drupal\soccerbet\Entity\Access\TournamentTournamentgroupTeamAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TournamentTournamentgroupTeamForm",
 *       "edit" = "Drupal\soccerbet\Form\TournamentTournamentgroupTeamForm",
 *       "delete" = "Drupal\soccerbet\Form\TournamentTournamentgroupTeamDeleteForm",
 *     },
 *   },
 *   base_table = "soccerbet_tournament_group_team",
 *   admin_permission = "administer soccerbet",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "description"
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/TournamentTournamentgroupTeam/group/{soccerbet_tournament_group_team}",
 *     "edit-form" = "/soccerbet/TournamentTournamentgroupTeam/{soccerbet_tournament_group_team}/group/{soccerbet_tournament_group_team}/edit",
 *     "delete-form" = "/soccerbet/TournamentTournamentgroupTeam/{soccerbet_tournament_group_team}/group/{soccerbet_tournament_group_team}delete",
 *     "collection" = "/soccerbet/TournamentTournamentgroupTeam/group/list"
 *   }
 * )
 */

class TournamentTournamentgroupTeam extends ContentEntityBase {

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
  public function getTeamID() {
    return $this->get('team_id')->team_id;
  }

  /**
   *{@inheritdoc}
   */
  public function setTeamID($team_id) {
    $this->set('team_id', $team_id);
    return $this;
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
  public function getTournamentGroupID() {
    return $this->get('tournamentgroup_id')->target_id;
  }

  /**
   *{@inheritdoc}
   */
  public function setTournamentGroupID($tournamentgroup_id) {
    $this->set('tournamentgroup_id', $tournamentgroup_id);
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
  public function setTournamentID($tournament_id) {
    $this->set('tournament_id', $tournament_id);
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

    $fields['team_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Team ID'))
      ->setDescription(t('The ID of the Team.'))
      ->setSetting('target_type', 'soccerbet_team')
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

    $fields['tournamentgroup_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tournament Group ID'))
      ->setDescription(t('The tournament group the tournament belongs to'))
      ->setSetting('target_type', 'soccerbet_tournamentgroup')
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
