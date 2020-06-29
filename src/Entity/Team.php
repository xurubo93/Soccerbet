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
use Drupal\soccerbet\TeamInterface;

/**
 * Defines the Team entity.
 *
 * @ingroup soccerbet_team
 *
 * @ContentEntityType(
 *   id = "soccerbet_team",
 *   label = @Translation("Team"),
 *   label_collection = @Translation("Teams"),
 *   label_singular = @Translation("team"),
 *   label_plural = @Translation("teams"),
 *   label_count = @PluralTranslation(
 *     singular = "@count team",
 *     plural = "@count teams",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\TeamViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TeamListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TeamForm",
 *       "edit" = "Drupal\soccerbet\Form\TeamForm",
 *       "delete" = "Drupal\soccerbet\Form\TeamDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\TeamAccessControlHandler",
 *   },
 *   base_table = "soccerbet_team",
 *   data_table = "soccerbet_team_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "team_id",
 *     "label" = "team_name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/team/{soccerbet_team}",
 *     "edit-form" = "/soccerbet/team/{soccerbet_team}/edit",
 *     "delete-form" = "/soccerbet/team/{soccerbet_team}/delete",
 *     "collection" = "/soccerbet/team/list"
 *   }
 * )
 *
 */
class Team extends ContentEntityBase implements TeamInterface {




  /**
   * {@inheritdoc}
   */
  public function getTeamName() {
    return $this->get('team_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTeamName($team_name) {
    $this->set('team_name', $team_name);
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
   * @return $this|Team
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
    $fields['team_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the team.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Name field for the team.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.

    $fields['team_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Team Name'))
      ->setDescription(t('The name of the Team.'))
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



    /**
     * This has to be activated, when the group and tipper implementation has finished
     *
     * $fields['tipper_id_first_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('First place tipper'))
      ->setDescription(t('The Name of the tipper who won this team.'))
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
      ->setDescription(t('The Name of the tipper who came second in this team.'))
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
      ->setDescription(t('The Name of the tipper who came third in this team.'))
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
      ->setDescription(t('The user ID of the team creator.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the team was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the team was last edited.'));

    return $fields;
  }
}
