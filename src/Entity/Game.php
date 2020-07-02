<?php
/**
 * @file
 * Contains \Drupal\content_entity_example\Entity\ContentEntityExample.
 */

namespace Drupal\soccerbet\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\soccerbet\GameInterface;
use Drupal\soccerbet\TeamInterface;
use Drupal\soccerbet\TipInterface;

/**
 * Defines the Game entity.
 *
 * @ingroup soccerbet_game
 *
 * @ContentEntityType(
 *   id = "soccerbet_game",
 *   label = @Translation("Game"),
 *   label_collection = @Translation("Games"),
 *   label_singular = @Translation("Game"),
 *   label_plural = @Translation("Games"),
 *   label_count = @PluralTranslation(
 *     singular = "@count game",
 *     plural = "@count games",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\GameViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\GameListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\GameForm",
 *       "edit" = "Drupal\soccerbet\Form\GameForm",
 *       "delete" = "Drupal\soccerbet\Form\GameDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\GameAccessControlHandler",
 *   },
 *   base_table = "soccerbet_game",
 *   data_table = "soccerbet_game_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "game_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/game/{soccerbet_game}",
 *     "edit-form" = "/soccerbet/game/{soccerbet_game}/edit",
 *     "delete-form" = "/soccerbet/game/{soccerbet_game}/delete",
 *     "collection" = "/soccerbet/games/list"
 *   }
 * )
 *
 */
class Game extends ContentEntityBase implements GameInterface {

  /**
   *{@inheritdoc}
   */
  public function getStartTime() {
    return $this->get('start_time')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setStartTime($start_time) {
    $this->set('start_time', $start_time);
    return $this;
  }

  /**
   * Generates a form option array to choose the number of a goals a team has made
   *
   * @return array $options
   */
  public function getScoreFirstTeamOptions() {
    $score_first_team = $this->getScoreFirstTeam();
    $options = array();

    for ($i = 0; $i < $score_first_team; $i++) {
      //The Capital A starts with the ASCII char 65
      $options[chr(65+$i)] = chr(65+$i);
    }
    return $options;
  }

  /**
   *{@inheritdoc}
   */
  public function getScoreFirstTeam() {
    return $this->get('score_first_team')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setScoreFirstTeam($score_first_team) {
    $this->set('score_first_team', $score_first_team);
    return $this;
  }


  /**
   *{@inheritdoc}
   */
  public function getScoreSecondTeam() {
    return $this->get('score_second_team')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setScoreSecondTeam($score_second_team) {
    $this->set('score_second_team', $score_second_team);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getGameLocation() {
    return $this->get('game_location')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setGameLocation($game_location) {
    $this->set('game_location', $game_location);
    return $this;
  }
  /**
   *{@inheritdoc}
   */
  public function getGameType() {
    return $this->get('game_type')->label;
  }

  public function setGameType($game_type) {
    $this->set('game_type', $game_type);
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
   * @param int $changed
   * @return $this|Game
   */
  public function setChangedTime($changed) {
    $this->set('changed', $changed);
    return $this;
  }

  /**
   * @return int
   */
  public function getChangedTimeAcrossTranslations() {
    return $this->getChangedTime();
  }

  /**
   *
   * build the connection between the entities
   *
   */

  /**
   *{@inheritdoc}
   */
  public function getFirstTeam() {
    return $this->get('game_first_team')->entity;
  }

  /**
   * @param TeamInterface $game_first_team
   * @return $this|Game
   */
  public function setFirstTeam(TeamInterface $game_first_team) {
    $this->set('game_first_team', $game_first_team);
    return $this;
  }

  /**
   * @return Team
   */
  public function getSecondTeam() {
    return $this->get('game_second_team')->entity;
  }

  /**
   * @param TeamInterface $game_second_team
   * @return $this|mixed
   */
  public function setSecondTeam(TeamInterface $game_second_team) {
    $this->set('game_second_team', $game_second_team);
    return $this;
  }


  /**
   *{@inheritdoc}
   */
  public function getTipA() {
    return $this->get('tipA')->entity;
  }

  /**
   * @param TipInterface $tipA
   * @return $this|Participant
   */
  public function setTipA(TipInterface $tipA) {
    $this->set('tipA', $tipA);
    return $this;
  }

  /**
   * @return Tip
   */
  public function getTipB() {
    return $this->get('tipB')->entity;
  }
  /**
   * @param TipInterface $tipB
   * @return $this|mixed
   */
  public function setTipB(TipInterface $tipB) {
    $this->set('game_second_team', $tipB);
    return $this;
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

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['game_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the game.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['score_first_team'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('score first team'))
      ->setDescription(t('The number of goals made by the first team.'))
      ->setDefaultValue(0)
      ->setSettings(array(
        'allowed_values' => array(
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
          9 => 9,
          10 => 10,
          11 => 11,
          12 => 12,
          13 => 13,
          14 => 14,
          15 => 15,
          16 => 16,
        )
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'hidden',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['score_second_team'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('score second team'))
      ->setDescription(t('The number of goals made by the first team.'))
      ->setDefaultValue(0)
      ->setSettings(array(
        'allowed_values' => array(
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
          9 => 9,
          10 => 10,
          11 => 11,
          12 => 12,
          13 => 13,
          14 => 14,
          15 => 15,
          16 => 16,
        )
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'hidden',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);


    $fields['start_time'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Starttime'))
      ->setDescription(t('The starttime of this game.'))
      ->setSettings(array(
        'datetime_type' => 'date'
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'date',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'date',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['game_location'] = BaseFieldDefinition::create('string')
      ->setLabel(t('game location'))
      ->setDescription(t('The location of this game.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 64,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'date',
        'weight' => -4,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'date',
        'weight' => -4,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['game_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Game type'))
      ->setDescription(t('The type of this game. (KO-Game, Group-Game,...)'))
      ->setDefaultValue(8)
      ->setSettings(array(
        'allowed_values' => array(
          'GR' => 'Group',
          'KO' => 'KO-Game',
        )
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'hidden',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

      //This has to be activated, when the group and tipper implementation has finished

     $fields['game_first_team'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('First team'))
      ->setDescription(t('The first team in this game, ususally called the home team.'))
      ->setSetting('target_type', 'soccerbet_team')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'entity_reference',
        'weight' => 10,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ),
        'weight' => 10,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['game_second_team'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Second team'))
      ->setDescription(t('The second team in this match, usually called the foreign team.'))
      ->setSetting('target_type', 'soccerbet_team')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'entity_reference',
        'weight' => 11,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ),
        'weight' => 11,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of the game creator.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the game was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the game was last edited.'));

    return $fields;
  }
}
