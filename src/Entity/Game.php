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
 *     "label" = "name",
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
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

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
   * Generates a form option array to choose the number of a goals a team has made
   *
   * @return array $options
   */
  public function getScoreSecondTeamOptions() {
    $score_second_team = $this->getScoreSecondTeam();
    $options = array();

    for ($i = 0; $i < $score_second_team; $i++) {
      //The Capital A starts with the ASCII char 65
      $options[chr(65+$i)] = chr(65+$i);
    }
    return $options;
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
  public function getKOGame() {
    return $this->get('KO_game')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setKOGame($KO_game) {
    $this->set('KO_game', $KO_game);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getGroupGame() {
    return $this->get('group_game')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setGroupGame($group_game) {
    $this->set('group_game', $group_game);
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Game Name'))
      ->setDescription(t('The name of the Game.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 64,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'label' => 'hidden',
        'type' => 'string_textfield',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


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


    /**
     * This has to be activated, when the group and tipper implementation has finished
     *
     * $fields['tipper_id_first_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('First place tipper'))
      ->setDescription(t('The Name of the tipper who won this tournament.'))
      ->setSetting('target_type', 'soccerbet_tipper')
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

    $fields['tipper_id_second_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Second place tipper'))
      ->setDescription(t('The Name of the tipper who came second in this tournament.'))
      ->setSetting('target_type', 'soccerbet_tipper')
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

    $fields['tipper_id_third_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Third place tipper'))
      ->setDescription(t('The Name of the tipper who came third in this tournament.'))
      ->setSetting('target_type', 'soccerbet_tipper')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'entity_reference',
        'weight' => 12,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ),
        'weight' => 12,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    */

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
