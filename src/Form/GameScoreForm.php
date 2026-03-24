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
      '#markup' => '<h3>' . htmlspecialchars($team1->team_name) . ' vs. ' . htmlspecialchars($team2->team_name) . '</h3>',
    ];

    $form['score'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Endergebnis (nach 90 Min.)'),
      '#attributes' => ['class' => ['soccerbet-score-fieldset']],
    ];
    $form['score']['team1_score'] = [
      '#type'          => 'number',
      '#title'         => $team1->team_name,
      '#min'           => 0,
      '#max'           => 99,
      '#required'      => TRUE,
      '#default_value' => $game->team1_score ?? '',
      '#attributes'    => ['style' => 'width: 70px;'],
    ];
    $form['score']['team2_score'] = [
      '#type'          => 'number',
      '#title'         => $team2->team_name,
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
        '#title'         => $this->t('Aufsteiger / Sieger (nach Verlängerung/Elfmeter)'),
        '#description'   => $this->t('Nur ausfüllen wenn nicht durch Ergebnis nach 90 Min. eindeutig.'),
        '#options'       => [
          0                => $this->t('Aus Ergebnis (kein Elfmeterschießen)'),
          $game->team_id_1 => $team1->team_name,
          $game->team_id_2 => $team2->team_name,
        ],
        '#default_value' => $game->winner_team_id ?? 0,
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Ergebnis speichern'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ko_phases = ['round_of_32', 'round_of_16', 'quarter', 'semi', 'third_place', 'final'];
    if (!in_array($form_state->get('game_phase'), $ko_phases, TRUE)) {
      return;
    }
    $s1 = (int) $form_state->getValue('team1_score');
    $s2 = (int) $form_state->getValue('team2_score');
    if ($s1 === $s2 && (int) ($form_state->getValue('winner_team_id') ?? 0) === 0) {
      $form_state->setErrorByName(
        'winner_team_id',
        $this->t('Bei Unentschieden muss ein Aufsteiger gewählt werden.')
      );
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $game_id      = $form_state->get('game_id');
    $tournament_id = $form_state->get('tournament_id');
    $winner       = (int) ($form_state->getValue('winner_team_id') ?? 0);

    $this->tipperManager->saveScore(
      $game_id,
      (int) $form_state->getValue('team1_score'),
      (int) $form_state->getValue('team2_score'),
      $winner > 0 ? $winner : NULL,
    );

    $this->messenger()->addStatus($this->t('Ergebnis wurde gespeichert. Die Rangliste wird aktualisiert.'));
    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $tournament_id])
    );
  }
}
