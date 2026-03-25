<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Spiel anlegen oder bearbeiten.
 */
final class GameForm extends FormBase {

  private const PHASES = [
    'group'       => 'Group stage',
    'round_of_32' => 'Round of 32',
    'round_of_16' => 'Round of 16',
    'quarter'     => 'Quarter-final',
    'semi'        => 'Semi-final',
    'third_place' => 'Third-place match',
    'final'       => 'Final',
  ];

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_game_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $game_id = 0, int $tournament_id = 0): array {
    $game_id       = (int) $game_id;
    $tournament_id = (int) $tournament_id;

    $game = NULL;
    if ($game_id > 0) {
      $game          = $this->tipperManager->loadGame($game_id);
      $tournament_id = (int) $game->tournament_id;
      $form_state->set('game_id', $game_id);
    }

    // Falls kein Turnier übergeben → aktives Standard-Turnier verwenden
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }

    $form_state->set('tournament_id', $tournament_id);

    // Turnier-Info-Banner
    $tournament = $this->tournamentManager->load($tournament_id);
    $form['tournament_info'] = [
      '#type'   => 'item',
      '#markup' => '<div class="soccerbet-form-tournament-info">'
        . $this->t('Tournament: <strong>@name</strong>', ['@name' => $tournament->tournament_desc])
        . '</div>',
      '#weight' => -10,
    ];

    $team_options = $this->tipperManager->getTeamOptions($tournament_id);
    if (empty($team_options)) {
      $this->messenger()->addWarning($this->t('Please first <a href=":url">create teams</a>.', [
        ':url' => Url::fromRoute('soccerbet.admin.teams.create', ['tournament_id' => $tournament_id])->toString(),
      ]));
    }

    $form['team_id_1'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Home team'),
      '#options'       => [0 => $this->t('— select team —')] + $team_options,
      '#required'      => TRUE,
      '#default_value' => $game?->team_id_1 ?? 0,
    ];
    $form['team_id_2'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Away team'),
      '#options'       => [0 => $this->t('— select team —')] + $team_options,
      '#required'      => TRUE,
      '#default_value' => $game?->team_id_2 ?? 0,
    ];

    $default_date = NULL;
    if ($game?->game_date) {
      // DB speichert UTC – für Anzeige im Formular in User-Zeitzone konvertieren
      $default_date = new \Drupal\Core\Datetime\DrupalDateTime(
        $game->game_date,
        new \DateTimeZone('UTC')
      );
      $user_timezone = \Drupal::currentUser()->getTimeZone()
        ?: \Drupal::config('system.date')->get('timezone.default')
        ?: 'UTC';
      $default_date->setTimezone(new \DateTimeZone($user_timezone));
    }

    $form['game_date'] = [
      '#type'          => 'datetime',
      '#title'         => $this->t('Kickoff'),
      '#required'      => TRUE,
      '#default_value' => $default_date,
      '#date_date_format' => 'd.m.Y',
      '#date_time_format' => 'H:i',
    ];

    $form['location'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Venue'),
    ];
    $form['location']['game_location'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('City'),
      '#maxlength'     => 64,
      '#default_value' => $game?->game_location ?? '',
    ];
    $form['location']['game_stadium'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Stadium'),
      '#maxlength'     => 64,
      '#default_value' => $game?->game_stadium ?? '',
    ];

    $form['phase'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Round'),
      '#options'       => self::PHASES,
      '#default_value' => $game?->phase ?? 'group',
    ];
    $form['published'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Published (include in scoring)'),
      '#default_value' => $game?->published ?? 1,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $game_id ? $this->t('Save match') : $this->t('Create match'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('team_id_1') == $form_state->getValue('team_id_2')) {
      $form_state->setErrorByName('team_id_2', $this->t('Home and away team must be different.'));
    }
    if ($form_state->getValue('team_id_1') == 0) {
      $form_state->setErrorByName('team_id_1', $this->t('Please select a home team.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Datetime\DrupalDateTime $dt */
    $dt = $form_state->getValue('game_date');
    // Immer als UTC in DB speichern
    $dt->setTimezone(new \DateTimeZone('UTC'));
    $values = [
      'team_id_1'     => (int) $form_state->getValue('team_id_1'),
      'team_id_2'     => (int) $form_state->getValue('team_id_2'),
      'game_date'     => $dt->format('Y-m-d\TH:i:s'),
      'game_location' => $form_state->getValue('game_location'),
      'game_stadium'  => $form_state->getValue('game_stadium'),
      'phase'         => $form_state->getValue('phase'),
      'published'     => (int) $form_state->getValue('published'),
    ];

    $tournament_id = $form_state->get('tournament_id');
    $game_id       = $form_state->get('game_id');

    if ($game_id) {
      $this->tipperManager->updateGame($game_id, $values);
      $this->messenger()->addStatus($this->t('Match has been updated.'));
    }
    else {
      $this->tipperManager->createGame($tournament_id, $values);
      $this->messenger()->addStatus($this->t('Match has been created.'));
    }

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $tournament_id])
    );
  }
}
