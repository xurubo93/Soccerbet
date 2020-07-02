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
use Drupal\soccerbet\ParticipantInterface;
use Drupal\soccerbet\TeamInterface;
use Drupal\soccerbet\TipInterface;

/**
 * Defines the Participant entity.
 *
 * @ingroup soccerbet_participant
 *
 * @ContentEntityType(
 *   id = "soccerbet_participant",
 *   label = @Translation("Participant"),
 *   label_collection = @Translation("Participants"),
 *   label_singular = @Translation("participant"),
 *   label_plural = @Translation("participants"),
 *   label_count = @PluralTranslation(
 *     singular = "@count participant",
 *     plural = "@count participants",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\ParticipantViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\ParticipantListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\ParticipantForm",
 *       "edit" = "Drupal\soccerbet\Form\ParticipantForm",
 *       "delete" = "Drupal\soccerbet\Form\ParticipantDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\ParticipantAccessControlHandler",
 *   },
 *   base_table = "soccerbet_participant",
 *   data_table = "soccerbet_participant_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "participant_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/participant/{soccerbet_participant}",
 *     "edit-form" = "/soccerbet/participant/{soccerbet_participant}/edit",
 *     "delete-form" = "/soccerbet/participant/{soccerbet_participant}/delete",
 *     "collection" = "/soccerbet/participant/list"
 *   }
 * )
 *
 */
class Participant extends ContentEntityBase implements ParticipantInterface {

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
   * @return $this|Participant
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
    $fields['participant_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the participant.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Name field for the participant.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.


    /**
     * This has to be activated, when the group and tipper implementation has finished
     *
     * $fields['tipper_id_first_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('First place tipper'))
      ->setDescription(t('The Name of the tipper who won this participant.'))
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
      ->setDescription(t('The Name of the tipper who came second in this participant.'))
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
      ->setDescription(t('The Name of the tipper who came third in this participant.'))
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
      ->setDescription(t('The user ID of the participant creator.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the participant was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the participant was last edited.'));

    return $fields;
  }
}
