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
use Drupal\soccerbet\TipInterface;
use Drupal\soccerbet\TournamentgroupInterface;

/**
 * Defines the Tournamentgroup entity.
 *
 * @ingroup soccerbet_tournamentgroup
 *
 * @ContentEntityType(
 *   id = "soccerbet_tournamentgroup",
 *   label = @Translation("Tournamentgroup"),
 *   label_collection = @Translation("Tournamentgroups"),
 *   label_singular = @Translation("tournamentgroup"),
 *   label_plural = @Translation("tournamentgroups"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tournamentgroup",
 *     plural = "@count tournamentgroups",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\TournamentgroupViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TournamentgroupListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TournamentgroupForm",
 *       "edit" = "Drupal\soccerbet\Form\TournamentgroupForm",
 *       "delete" = "Drupal\soccerbet\Form\TournamentgroupDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\TournamentgroupAccessControlHandler",
 *   },
 *   base_table = "soccerbet_tournamentgroup",
 *   data_table = "soccerbet_tournamentgroup_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "tournamentgroup_id",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/tournamentgroup/{soccerbet_tournamentgroup}",
 *     "edit-form" = "/soccerbet/tournamentgroup/{soccerbet_tournamentgroup}/edit",
 *     "delete-form" = "/soccerbet/tournamentgroup/{soccerbet_tournamentgroup}/delete",
 *     "collection" = "/soccerbet/tournamentgroup/list"
 *   }
 * )
 *
 */
class Tournamentgroup extends ContentEntityBase implements TournamentgroupInterface {





  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($tournamentgroupname) {
    $this->set('name', $tournamentgroupname);
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
   * @return $this|Tournamentgroup
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
   * @param TournamentInterface $tournament
   * @return $this|Tournament
   */
  public function setTournament(TournamentInterface $tournament) {
    $this->set('tournament', $tournament);
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
    $fields['tournamentgroup_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the tournament.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Name field for the tournament.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tournamentgroup Name'))
      ->setDescription(t('The name of the Tournamentgroup.'))
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

    $fields['tournament'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('tournament'))
      ->setDescription(t('The tournament of this tournamentgroup'))
      ->setSetting('target_type', 'soccerbet_tournament')
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
