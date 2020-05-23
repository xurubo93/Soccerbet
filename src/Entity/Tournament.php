<?php
/**
 * @file
 * Contains \Drupal\content_entity_example\Entity\ContentEntityExample.
 */

namespace Drupal\soccerbet\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\soccerbet\TournamentInterface;

/**
 * Defines the Tournament entity.
 *
 * @ingroup soccerbet_tournament
 *
 * @ContentEntityType(
 *   id = "soccerbet_tournament",
 *   label = @Translation("Tournament"),
 *   label_collection = @Translation("Tournaments"),
 *   label_singular = @Translation("tournament"),
 *   label_plural = @Translation("tournaments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tournament",
 *     plural = "@count tournaments",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\TournamentViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TournamentListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TournamentForm",
 *       "edit" = "Drupal\soccerbet\Form\TournamentForm",
 *       "delete" = "Drupal\soccerbet\Form\TournamentDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\TournamentAccessControlHandler",
 *   },
 *   base_table = "soccerbet_tournament",
 *   data_table = "soccerbet_tournament_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "tournament_id",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/tournament/{soccerbet_tournament}",
 *     "edit-form" = "/soccerbet/tournament/{soccerbet_tournament}/edit",
 *     "delete-form" = "/soccerbet/tournament/{soccerbet_tournament}/delete",
 *     "collection" = "/soccerbet/tournament/list"
 *   }
 * )
 *
 */
class Tournament extends ContentEntityBase implements TournamentInterface {

  /**
   * Denotes that a tournament is active
   */
  const TOURNAMENT_IS_ACTIVE = 1;

  /**
   * Denotes that a tournament is inactive
   */
  const TOURNAMENT_IS_INACTIVE = 0;
  /**
   * @var \Drupal\Core\Field\FieldItemListInterface|mixed
   */
  private $logo;

  /**
   * Generates a form option array based on the number of groups defined in this tournament
   *
   * @return array $options
   */
  public function getGroupOptions() {
    $group_count = $this->getGroupCount();
    $options = array();

    for ($i = 0; $i < $group_count; $i++) {
      //The Capital A starts with the ASCII char 65
      $options[chr(65+$i)] = chr(65+$i);
    }
    return $options;
  }

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
   * {@inheritdoc}
   */
  public function getLogo() {
    return $this->logo->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogo(ImageItem $image) {
    $this->set('logo', $image);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getStartDate() {
    return $this->get('start_date')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setStartDate($start_date) {
    $this->set('start_date', $start_date);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getEndDate() {
    return $this->get('end_date')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setEndDate($end_date) {
    $this->set('end_date', $end_date);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function isActive() {
    return (bool )$this->get('active')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function getStatus() {
    return $this->get('active')->value;
  }

  /**
   *{@inheritdoc}
   */
  public function setActive($status) {
    $this->set('active', $status ? Tournament::TOURNAMENT_IS_ACTIVE : Tournament::TOURNAMENT_IS_INACTIVE);
  }

  /**
   *{@inheritdoc}
   */
  public function getGroupCount() {
    return $this->get('group_count')->value;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setGroupCount($group_count) {
    $this->set('group_count', $group_count);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getTipperInFirstPlace() {
    return $this->get('tipper_id_in_first_place')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setTipperInFirstPlace($tipper_id) {
    $this->set('tipper_id_in_first_place', $tipper_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getTipperInSecondPlace() {
    return $this->get('tipper_id_in_second_place')->entity;
  }

  /**
   *{@inheritdoc}
   */
  public function setTipperInScondPlace($tipper_id) {
    $this->set('tipper_id_in_second_place', $tipper_id);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function getTipperInThirdPlace() {
    return $this->get('tipper_id_in_third_place')->entity;
  }

  /**
   *{@inheritdoc}
   *
   */
  public function setTipperInThirdPlace($tipper_id) {
    $this->set('tipper_id_in_third_place', $tipper_id);
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
   * @return $this|Tournament
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
    $fields['tournament_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the tournament.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Name field for the tournament.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tournament Name'))
      ->setDescription(t('The name of the Tournament.'))
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

    /*$fields['logo'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Logo'))
      ->setDescription(t('The logo of this tournament'))
      ->setSetting('target_type', 'file')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', array(
        'label' => 'hidden',
        'type' => 'image',
        'weight' => -7,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'image',
        'weight' => -7,
      ));*/

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Startdate'))
      ->setDescription(t('The startdate of this tournament.'))
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

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Enddate'))
      ->setDescription(t('The enddate of this tournament.'))
      ->setSettings(array(
        'datetime_type' => 'date',
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

    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Tournament status'))
      ->setDescription(t('A boolean indicating whether the tournament is active.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'radios',
        'weight' => -3,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'radios',
        'weight' => -3,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['group_count'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Group count'))
      ->setDescription(t('The number of groups in the preliminary round of this tournament.'))
      ->setDefaultValue(8)
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
      ->setDescription(t('The user ID of the tournament creator.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the tournament was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the tournament was last edited.'));

    return $fields;
  }
}
