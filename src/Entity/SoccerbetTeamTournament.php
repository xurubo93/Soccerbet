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
 * Defines the soccerbet team tournament entity class.
 *
 * @ContentEntityType(
 *   id = "soccerbet_team_tournament",
 *   label = @Translation("Soccerbet Team Tournament Relation"),
 *   label_collection = @Translation("Soccerbet Teams Tournament Relation"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\soccerbet\SoccerbetTeamTournamentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\soccerbet\SoccerbetTeamTournamentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\soccerbet\Form\SoccerbetTeamTournamentForm",
 *       "edit" = "Drupal\soccerbet\Form\SoccerbetTeamTournamentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "soccerbet_team_tournament",
 *   admin_permission = "access soccerbet team overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/soccerbet-team-tournament/add",
 *     "canonical" = "/soccerbet_team-tournament/{soccerbet_team_tournament}",
 *     "edit-form" = "/admin/content/soccerbet-team-tournament/{soccerbet_team_tournament}/edit",
 *     "delete-form" = "/admin/content/soccerbet-team-tournament/{soccerbet_team_tournament}/delete",
 *     "collection" = "/admin/content/soccerbet-team-tournament"
 *   },
 *   field_ui_base_route = "entity.soccerbet_team_tournament.settings"
 * )
 */
class SoccerbetTeamTournament extends ContentEntityBase implements SoccerbetTeamTournamentInterface {

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

  /**
   * @return \Drupal\soccerbet\Entity\SoccerbetTeamInterface
   */
  public function getTeam(): SoccerbetTeamInterface {
    return $this->get('team')->entity;
  }

  /**
   * @param \Drupal\soccerbet\Entity\SoccerbetTeamInterface $soccerbetTeam
   *
   * @return $this
   */
  public function setTeam(SoccerbetTeamInterface $soccerbetTeam): SoccerbetTeamTournamentInterface {
    $this->set('team', $soccerbetTeam);
    return $this;
  }

  /**
   * @return \Drupal\soccerbet\Entity\SoccerbetTournamentInterface
   */
  public function getTournament(): SoccerbetTournament {
    return $this->get('tournament')->entity;
  }

  /**
   * @param \Drupal\soccerbet\Entity\SoccerbetTournamentInterface $soccerbetTournament
   *
   * @return $this
   */
  public function setTournament(SoccerbetTournamentInterface $soccerbetTournament): SoccerbetTeamTournamentInterface {
    $this->set('tournament', $soccerbetTournament);
    return $this;
  }

  /**
   * @return string
   */
  public function getGroup(): string {
    return $this->get('group')->value;
  }

  /**
   * @param string $group
   *
   * @return $this
   */
  public function setGroup(string $group): SoccerbetTeamTournamentInterface {
    $this->set('group', $group);
    return $this;
  }

  /**
   * @return int
   */
  public function getGamesPlayed(): int {
    return $this->get('games_played')->value;
  }

  /**
   * @param int $games_played
   *
   * @return $this
   */
  public function setGamesPlayed(int $games_played): SoccerbetTeamTournamentInterface {
    $this->set('games_played', $games_played);
    return $this;
  }

  /**
   * @return int
   */
  public function getGamesWon(): int {
    return $this->get('games_won')->value;
  }

  /**
   * @param int $games_won
   *
   * @return $this
   */
  public function setGamesWon(int $games_won): SoccerbetTeamTournamentInterface {
    $this->set('games_won', $games_won);
    return $this;
  }

  /**
   * @return int
   */
  public function getGamesDrawn(): int {
    return $this->get('games_drawn')->value;
  }

  /**
   * @param int $games_drawn
   *
   * @return $this
   */
  public function setGamesDrawn(int $games_drawn): SoccerbetTeamTournamentInterface {
    $this->set('games_drawn', $games_drawn);
    return $this;
  }

  /**
   * @return int
   */
  public function getGamesLost(): int {
    return $this->get('games_lost')->value;
  }

  /**
   * @param int $games_lost
   *
   * @return $this
   */
  public function setGamesLost(int $games_lost): SoccerbetTeamTournamentInterface {
    $this->set('games_lost', $games_lost);
    return $this;
  }

  /**
   * @return string
   */
  public function getGoalDifference(): string {
    return $this->get('goal_difference')->value;
  }

  /**
   * @param string $goal_difference
   *
   * @return $this
   */
  public function setGoalDifference(string $goal_difference): SoccerbetTeamTournamentInterface {
    $this->set('goal_difference', $goal_difference);
    return $this;
  }

  /**
   * @return int
   */
  public function getPoints(): int {
    return $this->get('points')->value;
  }

  /**
   * @param int $points
   *
   * @return $this
   */
  public function setPoints(int $points): SoccerbetTeamTournamentInterface {
    $this->set('points', $points);
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
  public function setCreatedTime($timestamp): SoccerbetTeamTournamentInterface {
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
  public function setOwnerId($uid): SoccerbetTeamTournamentInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): SoccerbetTeamTournamentInterface {
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

    $fields['team'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(FALSE)
      ->setLabel(t('Team'))
      ->setDescription(t('The Soccerbet Team Entity of this Relation.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'soccerbet_team')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => 'Start typing the name of the team',
        ],
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'soccerbet_team',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['tournament'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(FALSE)
      ->setLabel(t('Tournament'))
      ->setDescription(t('The Soccerbet Tournament Entity of this Relation.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'soccerbet_tournament')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => 'Start typing the name of the tournament',
        ],
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['group'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Group of the team in this tournament'))
      ->setDescription(t('The group in which this team was drawn to inside this tournament'))
      ->setSettings([
        'allowed_values' => [
          "A" => "A",
          "B" => "B",
          "C" => "C",
          "D" => "D",
          "E" => "E",
          "F" => "F",
          "G" => "G",
          "H" => "H",
          "I" => "I",
          "J" => "J",
          "K" => "K",
          "L" => "L",
          "M" => "M",
          "N" => "N",
          "O" => "O",
          "P" => "P",

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

    $fields['games_played'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Games played'))
      ->setDescription(t('The number of games played by this team in this tournament.'))
      ->setDefaultValue(8)
      ->setSettings([
        'allowed_values' => [
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
        ]
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

    $fields['games_won'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Games won'))
      ->setDescription(t('The number of games won by this team in this tournament.'))
      ->setDefaultValue(8)
      ->setSettings([
        'allowed_values' => [
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
        ]
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

    $fields['games_drawn'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Games drawn'))
      ->setDescription(t('The number of games drawn by this team in this tournament.'))
      ->setDefaultValue(8)
      ->setSettings([
        'allowed_values' => [
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
        ]
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

    $fields['games_lost'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Games lost'))
      ->setDescription(t('The number of games lost by this team in this tournament.'))
      ->setDefaultValue(8)
      ->setSettings([
        'allowed_values' => [
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
        ]
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

    $fields['goal_difference'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Goal Difference'))
      ->setDescription(t('The goal difference of ths team in this tournament.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ])
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

    $fields['points'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Points'))
      ->setDescription(t('The points of this team in this tournament.'))
      ->setDefaultValue(8)
      ->setSettings([
        'allowed_values' => [
          1 => 1,
          2 => 2,
          3 => 3,
          4 => 4,
          5 => 5,
          6 => 6,
          7 => 7,
          8 => 8,
        ]
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