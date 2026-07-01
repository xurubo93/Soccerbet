<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Ergebnis eines Spiels eintragen.
 */
final class GameScoreForm extends FormBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tipper_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_game_score_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $game_id = 0): array {
    $game = $this->tipperManager->loadGame($game_id);
    $form_state->set('game_id', $game_id);
    $form_state->set('tournament_id', (int) $game->tournament_id);
    $form_state->set('game_phase', $game->phase);

    // Team-Namen laden
    $team1 = $this->tipperManager->loadTeam((int) $game->team_id_1);
    $team2 = $this->tipperManager->loadTeam((int) $game->team_id_2);

    $form['game_info'] = [
      '#markup' => '<h3>' . htmlspecialchars((string) $this->t($team1->team_name)) . ' vs. ' . htmlspecialchars((string) $this->t($team2->team_name)) . '</h3>',
    ];

    $form['score'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Final result (after 90 min.)'),
      '#attributes' => ['class' => ['soccerbet-score-fieldset']],
    ];
    $form['score']['team1_score'] = [
      '#type'          => 'number',
      '#title'         => $this->t($team1->team_name),
      '#min'           => 0,
      '#max'           => 99,
      '#required'      => TRUE,
      '#default_value' => $game->team1_score ?? '',
      '#attributes'    => ['style' => 'width: 70px;'],
    ];
    $form['score']['team2_score'] = [
      '#type'          => 'number',
      '#title'         => $this->t($team2->team_name),
      '#min'           => 0,
      '#max'           => 99,
      '#required'      => TRUE,
      '#default_value' => $game->team2_score ?? '',
      '#attributes'    => ['style' => 'width: 70px;'],
    ];

    // KO-Runden: Aufsteiger wählbar
    $ko_phases = ['round_of_32', 'round_of_16', 'quarter', 'semi', 'third_place', 'final'];
    if (in_array($game->phase, $ko_phases, TRUE)) {
      $form['winner_team_id'] = [
        '#type'          => 'radios',
        '#title'         => $this->t('Qualifier / Winner (after extra time/penalties)'),
        '#description'   => $this->t('Only fill in if not determined by the 90 min. result.'),
        '#options'       => [
          0                => $this->t('From result (no penalty shootout)'),
          $game->team_id_1 => $this->t($team1->team_name),
          $game->team_id_2 => $this->t($team2->team_name),
        ],
        '#default_value' => $game->winner_team_id ?? 0,
      ];

      $form['penalty'] = [
        '#type'        => 'fieldset',
        '#title'       => $this->t('Penalty shootout result (optional)'),
        '#description' => $this->t('Only fill in if the match was decided by a penalty shootout.'),
      ];
      $form['penalty']['penalty_score_1'] = [
        '#type'          => 'number',
        '#title'         => $this->t($team1->team_name),
        '#min'           => 0,
        '#max'           => 99,
        '#default_value' => $game->penalty_score_1 ?? '',
        '#attributes'    => ['style' => 'width: 70px;'],
      ];
      $form['penalty']['penalty_score_2'] = [
        '#type'          => 'number',
        '#title'         => $this->t($team2->team_name),
        '#min'           => 0,
        '#max'           => 99,
        '#default_value' => $game->penalty_score_2 ?? '',
        '#attributes'    => ['style' => 'width: 70px;'],
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save result'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $game_id      = $form_state->get('game_id');
    $tournament_id = $form_state->get('tournament_id');
    $winner       = (int) ($form_state->getValue('winner_team_id') ?? 0);

    $pen1_raw = $form_state->getValue('penalty_score_1');
    $pen2_raw = $form_state->getValue('penalty_score_2');
    $pen1 = ($pen1_raw === NULL || $pen1_raw === '') ? NULL : (int) $pen1_raw;
    $pen2 = ($pen2_raw === NULL || $pen2_raw === '') ? NULL : (int) $pen2_raw;

    $this->tipperManager->saveScore(
      $game_id,
      (int) $form_state->getValue('team1_score'),
      (int) $form_state->getValue('team2_score'),
      $winner > 0 ? $winner : NULL,
      $pen1,
      $pen2,
    );

    $this->messenger()->addStatus($this->t('Result has been saved. The standings will be updated.'));
    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $tournament_id])
    );
  }
}
