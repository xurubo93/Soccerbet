<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Globale Tippspiel-Einstellungen.
 */
final class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['soccerbet.settings'];
  }

  public function getFormId(): string {
    return 'soccerbet_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('soccerbet.settings');

    $form['scoring'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Scoring'),
    ];
    $form['scoring']['points_exact'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Points for exact result'),
      '#default_value' => $config->get('points_exact'),
      '#min'           => 1,
      '#max'           => 10,
      '#required'      => TRUE,
    ];
    $form['scoring']['points_tendency'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Points for correct tendency'),
      '#default_value' => $config->get('points_tendency'),
      '#min'           => 0,
      '#max'           => 5,
      '#required'      => TRUE,
    ];

    $form['gameplay'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Game flow'),
    ];
    $form['gameplay']['betting_closes_minutes_before'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Bet lock X minutes before kickoff'),
      '#description'   => $this->t('0 = bets possible until kickoff.'),
      '#default_value' => $config->get('betting_closes_minutes_before'),
      '#min'           => 0,
    ];
    $form['gameplay']['allow_tipper_self_register'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Allow self-registration as bettor'),
      '#default_value' => $config->get('allow_tipper_self_register'),
    ];

    $form['display'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Display'),
    ];
    $form['display']['show_payment_status_in_standings'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show payment status in standings'),
      '#description'   => $this->t('Bettors with unpaid stake will be marked.'),
      '#default_value' => $config->get('show_payment_status_in_standings'),
    ];

    // Aktives Turnier auswählen
    $tournaments = $this->getTournamentOptions();
    $form['display']['default_tournament'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Default tournament'),
      '#description'   => $this->t('Pre-selected on the standings and betting page.'),
      '#options'       => [0 => $this->t('— please select —')] + $tournaments,
      '#default_value' => $config->get('default_tournament'),
    ];

    // Turniersieger-Tipp
    $form['winner_bet'] = [
      '#type'        => 'fieldset',
      '#title'       => $this->t('Tournament winner bet'),
      '#description' => $this->t('Points depend on when the bet is placed. Phase 0 = before tournament start, Phase 1 = after group stage, etc.'),
    ];
    $points = $config->get('winner_bet_points') ?? [10, 7, 5, 3, 1];
    foreach ($points as $i => $pts) {
      $form['winner_bet']['winner_bet_points_' . $i] = [
        '#type'          => 'number',
        '#title'         => $i === 0
          ? $this->t('Points before tournament start')
          : $this->t('Points after phase @n', ['@n' => $i]),
        '#default_value' => $pts,
        '#min'           => 0,
        '#max'           => 99,
        '#required'      => TRUE,
      ];
    }

    // API-Konfiguration
    $form['api'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('API configuration'),
    ];
    $form['api']['api_provider'] = [
      '#type'          => 'radios',
      '#title'         => $this->t('Data source'),
      '#options'       => [
        'openligadb'   => $this->t('OpenLigaDB <em>(free, no key needed – focus Germany/Austria)</em>'),
        'footballdata' => $this->t('football-data.org <em>(free with key – international leagues, more structured data)</em>'),
      ],
      '#default_value' => $config->get('api_provider') ?? 'openligadb',
    ];
    $form['api']['footballdata_api_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('API-Key (football-data.org)'),
      '#description'   => $this->t('Kostenlos registrieren auf <a href="https://www.football-data.org/client/register" target="_blank">football-data.org</a>. Nur benötigt wenn football-data.org als Quelle gewählt ist.'),
      '#default_value' => $config->get('footballdata_api_key') ?? '',
      '#size'          => 40,
      '#states'        => [
        'visible' => [
          ':input[name="api_provider"]' => ['value' => 'footballdata'],
        ],
      ],
    ];
    $form['api']['livescores_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable live scores'),
      '#description'   => $this->t('For live scores a Free+ plan or higher is required, see <a href="https://www.football-data.org/pricing" target="_blank">football-data.org/pricing</a>.'),
      '#default_value' => $config->get('livescores_enabled') ?? FALSE,
      '#states'        => [
        'visible' => [
          ':input[name="api_provider"]' => ['value' => 'footballdata'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('soccerbet.settings')
      ->set('points_exact',                      (int)  $form_state->getValue('points_exact'))
      ->set('points_tendency',                   (int)  $form_state->getValue('points_tendency'))
      ->set('betting_closes_minutes_before',     (int)  $form_state->getValue('betting_closes_minutes_before'))
      ->set('allow_tipper_self_register',        (bool) $form_state->getValue('allow_tipper_self_register'))
      ->set('show_payment_status_in_standings',  (bool) $form_state->getValue('show_payment_status_in_standings'))
      ->set('default_tournament',                (int)  $form_state->getValue('default_tournament'))
      ->set('api_provider',                      (string) $form_state->getValue('api_provider'))
      ->set('footballdata_api_key',              trim($form_state->getValue('footballdata_api_key') ?? ''))
      ->set('livescores_enabled',               (bool) $form_state->getValue('livescores_enabled'))
      ->set('winner_bet_points', array_values(array_map(
        fn($i) => (int) $form_state->getValue('winner_bet_points_' . $i),
        range(0, 4)
      )))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Liefert alle Turniere als Select-Optionen.
   */
  private function getTournamentOptions(): array {
    $rows = \Drupal::database()->select('soccerbet_tournament', 't')
      ->fields('t', ['tournament_id', 'tournament_desc'])
      ->orderBy('t.start_date', 'DESC')
      ->execute()
      ->fetchAll();
    $options = [];
    foreach ($rows as $row) {
      $options[$row->tournament_id] = $row->tournament_desc;
    }
    return $options;
  }

}
