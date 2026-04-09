<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\soccerbet\Service\ScoreUpdateService;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Score-Update-Einstellungen und manueller Update-Button.
 *
 * Pro Turnier:
 *  - Liga-Kürzel (z.B. "BL1", "EC", "WC")
 *  - Saison (z.B. "2024")
 *  - Manuelle Update-Schaltfläche mit Live-Feedback
 */
final class ScoreUpdateForm extends FormBase {

  public function __construct(
    private readonly ScoreUpdateService $scoreUpdateService,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.score_update'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_score_update_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config      = $this->config('soccerbet.settings');
    $tournaments = $this->tournamentManager->loadAll();

    // ----------------------------------------------------------------
    // Globale Einstellungen
    // ----------------------------------------------------------------
    $form['global'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Automatic score update'),
    ];

    $form['global']['score_update_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable automatic score update via cron'),
      '#description'   => $this->t('When enabled, scores are automatically fetched from football-data.org.'),
      '#default_value' => (bool) $config->get('score_update_enabled'),
    ];

    // ----------------------------------------------------------------
    // Pro Turnier: Liga-Zuordnung
    // ----------------------------------------------------------------
    $form['tournaments'] = [
      '#type'        => 'details',
      '#title'       => $this->t('League assignment per tournament'),
      '#open'        => TRUE,
      '#description' => $this->t(
        'Enter the football-data.org competition code and season for each tournament.
         Examples: <code>BL1</code> (Bundesliga), <code>EC</code> (Euro), <code>WC</code> (World Cup),
         <code>CL</code> (Champions League).'
      ),
    ];

    if (empty($tournaments)) {
      $form['tournaments']['empty'] = [
        '#markup' => '<p>' . $this->t('No tournaments available.') . '</p>',
      ];
    }
    else {
      foreach ($tournaments as $t) {
        $tid = (int) $t->tournament_id;

        $form['tournaments']['tournament_' . $tid] = [
          '#type'  => 'fieldset',
          '#title' => $t->tournament_desc . ($t->is_active ? ' ✓' : ''),
        ];

        $form['tournaments']['tournament_' . $tid]['oldb_league_' . $tid] = [
          '#type'          => 'textfield',
          '#title'         => $this->t('Competition code'),
          '#description'   => $this->t('e.g. BL1, EC, WC, CL'),
          '#default_value' => $t->oldb_league ?? '',
          '#size'          => 20,
          '#maxlength'     => 32,
          '#placeholder'   => 'BL1',
        ];

        $form['tournaments']['tournament_' . $tid]['oldb_season_' . $tid] = [
          '#type'          => 'textfield',
          '#title'         => $this->t('Season'),
          '#description'   => $this->t('e.g. 2024 (for season 2024/25)'),
          '#default_value' => $t->oldb_season ?? '',
          '#size'          => 10,
          '#maxlength'     => 8,
          '#placeholder'   => '2024',
        ];

        // Letzter Update-Status
        $last_run = \Drupal::state()->get('soccerbet.last_update.' . $tid);
        if ($last_run) {
          $form['tournaments']['tournament_' . $tid]['last_run'] = [
            '#markup' => '<div class="soccerbet-update-status">'
              . $this->t('Last update: @date', [
                '@date' => \Drupal::service('date.formatter')->format($last_run, 'short'),
              ])
              . '</div>',
          ];
        }

        // Manueller Update-Button (nur wenn Liga konfiguriert)
        if (!empty($t->oldb_league) && !empty($t->oldb_season)) {
          $form['tournaments']['tournament_' . $tid]['update_' . $tid] = [
            '#type'   => 'submit',
            '#value'  => $this->t('Update now'),
            '#name'   => 'update_' . $tid,
            '#submit' => ['::manualUpdate'],
            '#attributes' => ['class' => ['button', 'button--secondary']],
            '#tournament_id' => $tid,
            '#limit_validation_errors' => [],
          ];
        }
      }
    }

    // ----------------------------------------------------------------
    // Speichern
    // ----------------------------------------------------------------
    $form['save'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save settings'),
    ];

    return $form;
  }

  /**
   * Speichert die Liga-Zuordnungen und globalen Einstellungen.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values      = $form_state->getValues();
    $tournaments = $this->tournamentManager->loadAll();

    // Globale Einstellung speichern
    \Drupal::configFactory()
      ->getEditable('soccerbet.settings')
      ->set('score_update_enabled', (bool) $values['score_update_enabled'])
      ->save();

    // Pro Turnier: Liga-Zuordnung in DB speichern
    foreach ($tournaments as $t) {
      $tid    = (int) $t->tournament_id;
      $league = trim($values['oldb_league_' . $tid] ?? '');
      $season = trim($values['oldb_season_' . $tid] ?? '');

      \Drupal::database()->update('soccerbet_tournament')
        ->fields([
          'oldb_league' => $league ?: NULL,
          'oldb_season' => $season ?: NULL,
        ])
        ->condition('tournament_id', $tid)
        ->execute();
    }

    $this->messenger()->addStatus($this->t('Settings have been saved.'));
  }

  /**
   * Manueller Update für ein einzelnes Turnier.
   */
  public function manualUpdate(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $tid     = (int) ($trigger['#tournament_id'] ?? 0);
    if (!$tid) {
      return;
    }

    try {
      $tournament = $this->tournamentManager->load($tid);
      $league     = $tournament->oldb_league ?? '';
      $season     = $tournament->oldb_season ?? '';

      if (!$league || !$season) {
        $this->messenger()->addWarning($this->t('No league code configured.'));
        return;
      }

      $result = $this->scoreUpdateService->updateTournament($tid, $league, $season);

      \Drupal::state()->set('soccerbet.last_update.' . $tid, \Drupal::time()->getRequestTime());

      $this->messenger()->addStatus($this->t(
        'Update complete: @u scores updated, table: @t.',
        [
          '@u' => $result['scores_updated'],
          '@t' => $result['table_updated'] ? $this->t('yes') : $this->t('no'),
        ]
      ));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error: @msg', ['@msg' => $e->getMessage()]));
    }
  }

}
