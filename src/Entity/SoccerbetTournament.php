<?php

namespace Drupal\soccerbet\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the soccerbet tournament entity class.
 *
 * @ContentEntityType(
 *   id = "soccerbet_tournament",
 *   label = @Translation("Soccerbet Tournament"),
 *   label_collection = @Translation("Soccerbet Tournaments"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\SoccerbetTournamentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\SoccerbetTournamentForm",
 *       "edit" = "Drupal\soccerbet\Form\SoccerbetTournamentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "soccerbet_tournament",
 *   data_table = "soccerbet_tournament_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer soccerbet tournament",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/soccerbet-tournament/add",
 *     "canonical" = "/soccerbet/tournament/{soccerbet_tournament}",
 *     "edit-form" = "/admin/content/soccerbet-tournament/{soccerbet_tournament}/edit",
 *     "delete-form" = "/admin/content/soccerbet-tournament/{soccerbet_tournament}/delete",
 *     "collection" = "/admin/content/soccerbet-tournament"
 *   },
 *   field_ui_base_route = "entity.soccerbet_tournament.settings"
 * )
 */
class SoccerbetTournament extends ContentEntityBase implements SoccerbetTournamentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new soccerbet tournament entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += ['uid' => Drupal::currentUser()->id()];
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->get('name')->value;
  }

  /**
   * @param $name
   *
   * @return \Drupal\soccerbet_tournament\SoccerbetTournamentInterface
   */
  public function setName($name): SoccerbetTournamentInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * @return array
   */
  public function getStartAndEndDate(): array {
    return [
      'value' => $this->get('start_and_end_date')->value,
      'end_value' => $this->get('start_and_end_date')->end_value,
    ];
  }

  /**
   * @param $start_and_end_date
   *
   * @return \Drupal\soccerbet_tournament\SoccerbetTournamentInterface
   */
  public function setStartAndEndDate($start_and_end_date): SoccerbetTournamentInterface {
    $this->set('start_and_end_date', $start_and_end_date);
    return $this;
  }

  /**
   * @return int
   */
  public function getGroupCount() {
    return $this->get('group_count')->value;
  }

  /**
   * @param $group_count
   *
   * @return $this|\Drupal\soccerbet_tournament\Entity\SoccerbetTournament
   */
  public function setGroupCount($group_count) {
    $this->set('group_count', $group_count);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('name', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Tournament entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Tournament entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setDescription(t('The name of the Tournament.'))
      ->setRequired(TRUE)
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['start_and_end_date'] = BaseFieldDefinition::create('daterange')
      ->setLabel(t('Start and End date'))
      ->setDescription(t('The start and enddate of this tournament.'))
      ->setRequired(TRUE)
      ->setSettings(array(
        'datetime_type' => 'date'
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'daterange_default',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the soccerbet tournament is enabled.'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the soccerbet tournament.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the soccerbet tournament author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the soccerbet tournament was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the soccerbet tournament was last edited.'));

    return $fields;
  }

}
