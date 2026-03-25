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
 * Formular: Team anlegen oder bearbeiten.
 */
final class TeamForm extends FormBase {

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
    return 'soccerbet_team_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $team_id = 0, int $tournament_id = 0): array {
    $team_id       = (int) $team_id;
    $tournament_id = (int) $tournament_id;

    $team = NULL;
    if ($team_id > 0) {
      $team          = $this->tipperManager->loadTeam($team_id);
      $tournament_id = (int) $team->tournament_id;
      $form_state->set('team_id', $team_id);
    }

    // Falls kein Turnier übergeben → aktives Standard-Turnier verwenden
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }

    if ($tournament_id === 0) {
      return [
        '#markup' => '<p>' . $this->t('No active tournament configured. Please first <a href=":url">create a tournament and mark it as active</a>.', [
          ':url' => Url::fromRoute('soccerbet.admin.tournament.create')->toString(),
        ]) . '</p>',
      ];
    }

    $form_state->set('tournament_id', $tournament_id);

    // Turnier laden für die Anzeige
    $tournament     = $this->tournamentManager->load($tournament_id);
    $tournament_name = $tournament->tournament_desc;

    $form['tournament_info'] = [
      '#type'   => 'item',
      '#markup' => '<div class="soccerbet-form-tournament-info">'
        . $this->t('Tournament: <strong>@name</strong>', ['@name' => $tournament_name])
        . '</div>',
      '#weight' => -10,
    ];

    $form['team_name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Team name'),
      '#maxlength'     => 64,
      '#required'      => TRUE,
      '#default_value' => $team?->team_name ?? '',
    ];

    $form['team_group'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Group'),
      '#description'   => $this->t('Single letter A–Z (leave empty for KO rounds)'),
      '#maxlength'     => 1,
      '#size'          => 3,
      '#default_value' => $team?->team_group ?? '',
    ];

    $form['team_flag'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Flag (ISO 3166-1 Alpha-2 code)'),
      '#description'   => $this->t(
        'Two-letter country code, e.g. <code>at</code> (Austria), <code>de</code> (Germany), <code>gb-eng</code> (England). Always stored as lowercase.'
      ),
      '#maxlength'     => 10,
      '#size'          => 12,
      '#default_value' => $team?->team_flag ?? '',
      '#attributes'    => ['placeholder' => 'at'],
    ];

    // Flag-Vorschau (nur im Edit-Modus wenn bereits ein Code gesetzt ist)
    $current_flag = $team?->team_flag ?? '';
    if ($current_flag) {
      $flag_lower = strtolower($current_flag);
      $svg_path   = '/modules/custom/soccerbet/images/flags/svg/' . $flag_lower . '.svg';
      $form['flag_preview'] = [
        '#markup' => '<div class="soccerbet-flag-preview">'
          . '<span class="soccerbet-flag-preview__label">' . $this->t('Preview:') . '</span> '
          . '<img src="' . $svg_path . '" alt="' . htmlspecialchars($flag_lower) . '" '
          . 'width="40" height="40" class="soccerbet-flag">'
          . '</div>',
      ];
    }

    // Im Edit-Modus: Statistiken bearbeitbar
    if ($team_id > 0) {
      $form['stats'] = [
        '#type'        => 'details',
        '#title'       => $this->t('League table'),
        '#open'        => FALSE,
        '#description' => $this->t('These values are normally calculated automatically.'),
      ];
      foreach (['games_played', 'games_won', 'games_drawn', 'games_lost', 'goals_shot', 'goals_got', 'points'] as $field) {
        $form['stats'][$field] = [
          '#type'          => 'number',
          '#title'         => $this->t($field),
          '#min'           => 0,
          '#default_value' => $team?->$field ?? 0,
          '#size'          => 5,
        ];
      }
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $team_id ? $this->t('Save team') : $this->t('Create team'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $group = $form_state->getValue('team_group');
    if ($group !== '' && !preg_match('/^[A-Z]$/i', $group)) {
      $form_state->setErrorByName('team_group', $this->t('The group must be a single letter (A–Z).'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [
      'team_name'  => $form_state->getValue('team_name'),
      'team_group' => strtoupper((string) $form_state->getValue('team_group')),
      'team_flag'  => strtolower(trim((string) $form_state->getValue('team_flag'))),
    ];

    // Statistiken (nur im Edit-Modus vorhanden)
    foreach (['games_played', 'games_won', 'games_drawn', 'games_lost', 'goals_shot', 'goals_got', 'points'] as $field) {
      $values[$field] = (int) ($form_state->getValue($field) ?? 0);
    }

    $team_id       = $form_state->get('team_id');
    $tournament_id = $form_state->get('tournament_id');

    if ($team_id) {
      $this->tipperManager->updateTeam($team_id, $values);
      $this->messenger()->addStatus($this->t('Team "@name" has been saved.', ['@name' => $values['team_name']]));
    }
    else {
      $this->tipperManager->createTeam($tournament_id, $values);
      $this->messenger()->addStatus($this->t('Team "@name" has been created.', ['@name' => $values['team_name']]));
    }

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.teams.list', ['tournament_id' => $tournament_id])
    );
  }
}
