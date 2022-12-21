<?php

namespace Drupal\soccerbet\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\soccerbet\Entity\SoccerbetTeamInterface;
use Drupal\user\UserInterface;

/**
 * Defines the soccerbet team entity class.
 *
 * @ContentEntityType(
 *   id = "soccerbet_team",
 *   label = @Translation("Soccerbet Team"),
 *   label_collection = @Translation("Soccerbet Teams"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\SoccerbetTeamListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\soccerbet\SoccerbetTeamAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\SoccerbetTeamForm",
 *       "edit" = "Drupal\soccerbet\Form\SoccerbetTeamForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "soccerbet_team",
 *   data_table = "soccerbet_team_field_data",
 *   translatable = TRUE,
 *   admin_permission = "access soccerbet team overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/soccerbet-team/add",
 *     "canonical" = "/soccerbet_team/{soccerbet_team}",
 *     "edit-form" = "/admin/content/soccerbet-team/{soccerbet_team}/edit",
 *     "delete-form" = "/admin/content/soccerbet-team/{soccerbet_team}/delete",
 *     "collection" = "/admin/content/soccerbet-team"
 *   },
 *   field_ui_base_route = "entity.soccerbet_team.settings"
 * )
 */
class SoccerbetTeam extends ContentEntityBase implements SoccerbetTeamInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new soccerbet team entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += ['uid' => Drupal::currentUser()->id()];
  }

  public function getTeamNameCode(): string {
    return $this->get('team_name_code')->value;
  }

  public function setTeamNameCode(string $team_name_code): SoccerbetTeamInterface {
    $this->set('team_name_code', $team_name_code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title): SoccerbetTeamInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status): SoccerbetTeamInterface {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp): SoccerbetTeamInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): SoccerbetTeamInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): SoccerbetTeamInterface {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Team entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Standard field, unique outside the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Team entity.'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the soccerbet team entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['team_name_code'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Team namecode'))
      ->setDescription(t('The shortcode of this team. This shortcode refers to the flag which is stored in the flags directory.'))
      ->setSettings([
        'allowed_values' => [
          "AE" => "AE ",
          "AF" => "AF ",
          "AG" => "AG ",
          "AI" => "AI ",
          "AL" => "AL ",
          "AM" => "AM ",
          "AO" => "AO ",
          "AR" => "AR ",
          "AS" => "AS ",
          "AT" => "AT ",
          "AU" => "AU ",
          "AW" => "AW ",
          "AX" => "AX ",
          "AZ" => "AZ ",
          "BA" => "BA ",
          "BB" => "BB ",
          "BD" => "BD ",
          "BE" => "BE ",
          "BF" => "BF ",
          "BG" => "BG ",
          "BH" => "BH ",
          "BI" => "BI ",
          "BJ" => "BJ ",
          "BL" => "BL ",
          "BM" => "BM ",
          "BN" => "BN ",
          "BO" => "BO ",
          "BR" => "BR ",
          "BS" => "BS ",
          "BT" => "BT ",
          "BV" => "BV ",
          "BW" => "BW ",
          "BY" => "BY ",
          "BZ" => "BZ ",
          "CA" => "CA ",
          "CC" => "CC ",
          "CD" => "CD ",
          "CF" => "CF ",
          "CG" => "CG ",
          "CH" => "CH ",
          "CI" => "CI ",
          "CK" => "CK ",
          "CL" => "CL ",
          "CM" => "CM ",
          "CN" => "CN ",
          "CO" => "CO ",
          "CR" => "CR ",
          "CU" => "CU ",
          "CV" => "CV ",
          "CW" => "CW ",
          "CX" => "CX ",
          "CY" => "CY ",
          "CZ" => "CZ ",
          "DE" => "DE ",
          "DJ" => "DJ ",
          "DK" => "DK ",
          "DM" => "DM ",
          "DO" => "DO ",
          "DZ" => "DZ ",
          "EC" => "EC ",
          "EE" => "EE ",
          "EG" => "EG ",
          "ER" => "ER ",
          "ES" => "ES ",
          "ET" => "ET ",
          "EU" => "EU ",
          "FI" => "FI ",
          "FJ" => "FJ ",
          "FK" => "FK ",
          "FM" => "FM ",
          "FO" => "FO ",
          "FR" => "FR ",
          "GA" => "GA ",
          "GB" => "GB ",
          "GB-ENG" => "GB-ENG ",
          "GB-NIR" => "GB-NIR ",
          "GB-SCT" => "GB-SCT ",
          "GB-WLS" => "GB-WLS ",
          "GB-ZET" => "GB-ZET ",
          "GD" => "GD ",
          "GE" => "GE ",
          "GF" => "GF ",
          "GG" => "GG ",
          "GH" => "GH ",
          "GI" => "GI ",
          "GL" => "GL ",
          "GM" => "GM ",
          "GN" => "GN ",
          "GP" => "GP ",
          "GQ" => "GQ ",
          "GR" => "GR ",
          "GS" => "GS ",
          "GT" => "GT ",
          "GU" => "GU ",
          "GW" => "GW ",
          "GY" => "GY ",
          "HK" => "HK ",
          "HM" => "HM ",
          "HN" => "HN ",
          "HR" => "HR ",
          "HT" => "HT ",
          "HU" => "HU ",
          "ID" => "ID ",
          "IE" => "IE ",
          "IL" => "IL ",
          "IM" => "IM ",
          "IN" => "IN ",
          "IO" => "IO ",
          "IQ" => "IQ ",
          "IR" => "IR ",
          "IS" => "IS ",
          "IT" => "IT ",
          "JE" => "JE ",
          "JM" => "JM ",
          "JO" => "JO ",
          "JP" => "JP ",
          "KE" => "KE ",
          "KG" => "KG ",
          "KH" => "KH ",
          "KI" => "KI ",
          "KM" => "KM ",
          "KN" => "KN ",
          "KP" => "KP ",
          "KR" => "KR ",
          "KW" => "KW ",
          "KY" => "KY ",
          "KZ" => "KZ ",
          "LA" => "LA ",
          "LB" => "LB ",
          "LC" => "LC ",
          "LGBT" => "LGBT ",
          "LI" => "LI ",
          "LK" => "LK ",
          "LR" => "LR ",
          "LS" => "LS ",
          "LT" => "LT ",
          "LU" => "LU ",
          "LV" => "LV ",
          "LY" => "LY ",
          "MA" => "MA ",
          "MC" => "MC ",
          "MD" => "MD ",
          "ME" => "ME ",
          "MF" => "MF ",
          "MG" => "MG ",
          "MH" => "MH ",
          "MK" => "MK ",
          "ML" => "ML ",
          "MM" => "MM ",
          "MN" => "MN ",
          "MO" => "MO ",
          "MP" => "MP ",
          "MQ" => "MQ ",
          "MR" => "MR ",
          "MS" => "MS ",
          "MT" => "MT ",
          "MU" => "MU ",
          "MV" => "MV ",
          "MW" => "MW ",
          "MX" => "MX ",
          "MY" => "MY ",
          "MZ" => "MZ ",
          "NA" => "NA ",
          "NC" => "NC ",
          "NE" => "NE ",
          "NF" => "NF ",
          "NG" => "NG ",
          "NI" => "NI ",
          "NL" => "NL ",
          "NO" => "NO ",
          "NP" => "NP ",
          "NR" => "NR ",
          "NU" => "NU ",
          "NZ" => "NZ ",
          "OM" => "OM ",
          "PA" => "PA ",
          "PE" => "PE ",
          "PF" => "PF ",
          "PG" => "PG ",
          "PH" => "PH ",
          "PK" => "PK ",
          "PL" => "PL ",
          "PM" => "PM ",
          "PN" => "PN ",
          "PR" => "PR ",
          "PS" => "PS ",
          "PT" => "PT ",
          "PW" => "PW ",
          "PY" => "PY ",
          "QA" => "QA ",
          "RE" => "RE ",
          "RO" => "RO ",
          "RS" => "RS ",
          "RU" => "RU ",
          "RW" => "RW ",
          "SA" => "SA ",
          "SB" => "SB ",
          "SC" => "SC ",
          "SD" => "SD ",
          "SE" => "SE ",
          "SG" => "SG ",
          "SH" => "SH ",
          "SI" => "SI ",
          "SJ" => "SJ ",
          "SK" => "SK ",
          "SL" => "SL ",
          "SM" => "SM ",
          "SN" => "SN ",
          "SO" => "SO ",
          "SR" => "SR ",
          "SS" => "SS ",
          "ST" => "ST ",
          "SV" => "SV ",
          "SX" => "SX ",
          "SY" => "SY ",
          "SZ" => "SZ ",
          "TC" => "TC ",
          "TD" => "TD ",
          "TF" => "TF ",
          "TG" => "TG ",
          "TH" => "TH ",
          "TJ" => "TJ ",
          "TK" => "TK ",
          "TL" => "TL ",
          "TM" => "TM ",
          "TN" => "TN ",
          "TO" => "TO ",
          "TR" => "TR ",
          "TT" => "TT ",
          "TV" => "TV ",
          "TW" => "TW ",
          "TZ" => "TZ ",
          "UA" => "UA ",
          "UG" => "UG ",
          "UM" => "UM ",
          "US" => "US ",
          "UY" => "UY ",
          "UZ" => "UZ ",
          "VA" => "VA ",
          "VC" => "VC ",
          "VE" => "VE ",
          "VG" => "VG ",
          "VI" => "VI ",
          "VN" => "VN ",
          "VU" => "VU ",
          "WF" => "WF ",
          "WS" => "WS ",
          "XK" => "XK ",
          "YE" => "YE ",
          "YT" => "YT ",
          "ZA" => "ZA ",
          "ZM" => "ZM ",
          "ZW" => "ZW",
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'hidden',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the soccerbet team is enabled.'))
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
      ->setDescription(t('A description of the soccerbet team.'))
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

    $fields['flag'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Flag'))
      ->setDescription(t('Flag of this team'))
      ->setSettings([
        'file_directory' => 'IMAGE_FOLDER',
        'alt_field_required' => FALSE,
        'file_extensions' => 'png jpg jpeg gif',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the soccerbet team author.'))
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
      ->setDescription(t('The time that the soccerbet team was created.'))
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
      ->setDescription(t('The time that the soccerbet team was last edited.'));

    return $fields;
  }

}
