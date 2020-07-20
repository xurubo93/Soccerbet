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
use Drupal\soccerbet\TipInterface;

/**
 * Defines the Tip entity.
 *
 * @ingroup soccerbet_tip
 *
 * @ContentEntityType(
 *   id = "soccerbet_tip",
 *   label = @Translation("Tip"),
 *   label_collection = @Translation("Tips"),
 *   label_singular = @Translation("tip"),
 *   label_plural = @Translation("tips"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tip",
 *     plural = "@count tips",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\soccerbet\Entity\Controller\TipViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\Entity\Controller\TipListBuilder",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\TipForm",
 *       "edit" = "Drupal\soccerbet\Form\TipForm",
 *       "delete" = "Drupal\soccerbet\Form\TipDeleteForm",
 *     },
 *     "access" = "Drupal\soccerbet\Entity\Access\TipAccessControlHandler",
 *   },
 *   base_table = "soccerbet_tip",
 *   data_table = "soccerbet_tip_field_data",
 *   admin_permission = "administer soccerbet",
 *   translateable = TRUE,
 *   entity_keys = {
 *     "id" = "tip_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/soccerbet/tip/{soccerbet_tip}",
 *     "edit-form" = "/soccerbet/tip/{soccerbet_tip}/edit",
 *     "delete-form" = "/soccerbet/tip/{soccerbet_tip}/delete",
 *     "collection" = "/soccerbet/tip/list"
 *   }
 * )
 *
 */
class Tip extends ContentEntityBase implements TipInterface {


  /**
   * {@inheritdoc}
   */
  public function getTipTeamA() {
    return $this->get('tip_team_A')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTipTeamA($tip_team_A) {
    $this->set('tip_team_A', $tip_team_A);
    return $this;
  }


    /**
     * {@inheritdoc}
     */
    public function getTipTeamB() {
        return $this->get('tip_team_B')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setTipTeamB($tip_team_B) {
        $this->set('tip_team_B', $tip_team_B);
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
   * @return $this|Tip
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
   *{@inheritdoc}
   */
  public function getGame() {
    return $this->get('game')->entity;
  }

  /**
   * @param GameInterface $game
   * @return $this|Game
   */
  public function setGame(GameInterface $game) {
    $this->set('game', $game);
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
    $fields['tip_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The entity_id of the tip.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['tip_team_A'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('tip team A'))
      ->setDescription(t('The tip for team A.'))
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

    $fields['tip_team_B'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('tip team B'))
      ->setDescription(t('The tip for team B.'))
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



    $fields['game'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('game'))
      ->setDescription(t('The game of this tip'))
      ->setSetting('target_type', 'soccerbet_game')
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


    // Name field for the tip.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.

    /**
     * This has to be activated, when the group and tipper implementation has finished
     *
     * $fields['tipper_id_first_place'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('First place tipper'))
      ->setDescription(t('The Name of the tipper who won this tip.'))
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
      ->setDescription(t('The Name of the tipper who came second in this tip.'))
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
      ->setDescription(t('The Name of the tipper who came third in this tip.'))
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
      ->setDescription(t('The user ID of the tip creator.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the tip was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the tip was last edited.'));

    return $fields;
  }
}
