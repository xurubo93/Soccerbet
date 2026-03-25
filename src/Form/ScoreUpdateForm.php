<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\OpenLigaDbClient;
use Drupal\soccerbet\Service\ScoreUpdateService;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: OpenLigaDB-Einstellungen und manueller Score-Update.
 *
 * Pro Turnier:
 *  - Liga-Kürzel (z.B. "bl1", "em2024", "aut_bl")
 *  - Saison (z.B. "2024")
 *  - Manuelle Update-Schaltfläche mit Live-Feedback
 */
final class ScoreUpdateForm extends FormBase {

  public function __construct(
    private readonly OpenLigaDbClient $apiClient,
    private readonly ScoreUpdateService $scoreUpdateService,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.openligadb_client'),
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
      '#description'   => $this->t('When enabled, scores are automatically fetched from OpenLigaDB.'),
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
        'Enter the OpenLigaDB code and season for each tournament.
         Examples: <code>bl1</code> (Bundesliga), <code>em2024</code> (Euro 2024),
         <code>aut_bl</code> (Austrian Bundesliga).'
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
          '#type'       => 'fieldset',
          '#title'      => $t->tournament_desc . ($t->is_active ? ' ✓' : ''),
          '#attributes' => ['class' => ['soccerbet-oldb-tournament']],
        ];

        $form['tournaments']['tournament_' . $tid]['oldb_league_' . $tid] = [
          '#type'          => 'textfield',
          '#title'         => $this->t('League code'),
          '#description'   => $this->t('e.g. bl1, em2024, aut_bl, ucl'),
          '#default_value' => $t->oldb_league ?? '',
          '#size'          => 20,
          '#maxlength'     => 32,
          '#placeholder'   => 'bl1',
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
            // ID des Turniers für den Submit-Handler
            '#tournament_id' => $tid,
            '#limit_validation_errors' => [],
          ];
        }
      }
    }

    // ----------------------------------------------------------------
    // Verfügbare Ligen (Hilfe-Bereich)
    // ----------------------------------------------------------------
    $form['available_leagues'] = [
      '#type'        => 'details',
      '#title'       => $this->t('Available leagues at OpenLigaDB'),
      '#open'        => FALSE,
      '#description' => $this->t('Load the list of available leagues from OpenLigaDB.'),
    ];
    $form['available_leagues']['load_leagues'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Load leagues from OpenLigaDB'),
      '#submit' => ['::loadLeagues'],
      '#limit_validation_errors' => [],
    ];

    // Geladene Ligen anzeigen
    $leagues = $form_state->get('available_leagues');
    if (!empty($leagues)) {
      $rows = [];
      foreach (array_slice($leagues, 0, 100) as $league) {
        $rows[] = [
          $league['leagueShortcut'] ?? '',
          $league['leagueSeason']   ?? '',
          $league['leagueName']     ?? '',
        ];
      }
      $form['available_leagues']['table'] = [
        '#theme'  => 'table',
        '#header' => [$this->t('Code'), $this->t('Season'), $this->t('Name')],
        '#rows'   => $rows,
      ];
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

      // Forced: Last-Change-Cache ignorieren (manuell immer aktualisieren)
      $this->apiClient->markAsSeen($league, $season); // Reset
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

  /**
   * Lädt verfügbare Ligen von OpenLigaDB und zeigt sie im Formular an.
   */
  public function loadLeagues(array &$form, FormStateInterface $form_state): void {
    $leagues = $this->apiClient->getAvailableLeagues();
    if (empty($leagues)) {
      $this->messenger()->addWarning($this->t('No leagues received from OpenLigaDB.'));
    }
    else {
      $form_state->set('available_leagues', $leagues);
      $this->messenger()->addStatus($this->t('@count leagues loaded.', ['@count' => count($leagues)]));
    }
    $form_state->setRebuild(TRUE);
  }

}
