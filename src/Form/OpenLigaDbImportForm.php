<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\ApiClientFactory;
use Drupal\soccerbet\Service\ApiImportService;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Erstbefüllung von Teams und Spielen via konfigurierter API.
 */
final class OpenLigaDbImportForm extends FormBase {

  public function __construct(
    private readonly ApiImportService $importService,
    private readonly ApiClientFactory $clientFactory,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.api_import'),
      $container->get('soccerbet.api_client_factory'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_oldb_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    $tournament_id = (int) $tournament_id;
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return ['#markup' => $this->t('Turnier nicht gefunden.')];
    }

    $form_state->set('tournament_id', $tournament_id);

    $active_provider = $this->clientFactory->getActiveProvider();
    $api_name        = $this->importService->getApiName();

    $form['#attributes'] = ['class' => ['soccerbet-import-form']];

    $form['tournament_info'] = [
      '#type'   => 'item',
      '#markup' => '<div class="soccerbet-form-tournament-info">'
        . $this->t('Turnier: <strong>@name</strong>', ['@name' => $tournament->tournament_desc])
        . '</div>',
      '#weight' => -10,
    ];

    // Hinweis welche API aktiv ist
    $provider_label = match($active_provider) {
      ApiClientFactory::PROVIDER_FOOTBALLDATA => 'football-data.org',
      default => 'OpenLigaDB',
    };
    $settings_url = Url::fromRoute('soccerbet.settings')->toString();
    $form['api_info'] = [
      '#type'   => 'item',
      '#markup' => '<p class="messages messages--status">'
        . $this->t('Aktive API: <strong>@api</strong>. <a href=":url">API-Einstellungen ändern</a>.', [
          '@api' => $provider_label,
          ':url' => $settings_url,
        ])
        . '</p>',
    ];

    // football-data.org: API-Key-Warnung
    if ($active_provider === ApiClientFactory::PROVIDER_FOOTBALLDATA) {
      $api_key = \Drupal::config('soccerbet.settings')->get('footballdata_api_key') ?? '';
      if (empty($api_key)) {
        $form['api_key_warning'] = [
          '#type'   => 'item',
          '#markup' => '<p class="messages messages--warning">'
            . $this->t('Kein API-Key für football-data.org konfiguriert. <a href=":url">Jetzt eintragen</a>.', [
              ':url' => $settings_url,
            ])
            . '</p>',
        ];
      }
    }

    $form['info'] = [
      '#type'   => 'item',
      '#markup' => '<p>' . $this->t('Importiert alle Teams und Spiele. Bereits vorhandene werden übersprungen.') . '</p>',
    ];

    $form['league'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Liga / Competition-Code'),
      '#description'   => $this->importService->getLeagueHelp(),
      '#default_value' => $tournament->oldb_league ?? '',
      '#required'      => TRUE,
      '#size'          => 20,
    ];

    $form['season'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Saison'),
      '#description'   => $this->importService->getSeasonHelp(),
      '#default_value' => $tournament->oldb_season ?? '',
      '#required'      => TRUE,
      '#size'          => 10,
    ];

    $form['options'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Optionen'),
    ];
    $form['options']['group_only'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Nur Gruppenspiele importieren'),
      '#description'   => $this->t('KO-Runden (Achtelfinale, Viertelfinale, …) werden übersprungen und können manuell angelegt werden.'),
      '#default_value' => TRUE,
    ];
    $form['options']['save_league'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Liga/Saison am Turnier speichern'),
      '#description'   => $this->t('Wird für automatische Score-Updates via Cron verwendet.'),
      '#default_value' => TRUE,
    ];

    $form['submit'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Import starten (@api)', ['@api' => $api_name]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['cancel'] = [
      '#type'       => 'link',
      '#title'      => $this->t('Abbrechen'),
      '#url'        => Url::fromRoute('soccerbet.admin.tournament.list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tournament_id = (int) $form_state->get('tournament_id');
    $league        = trim($form_state->getValue('league'));
    $season        = trim($form_state->getValue('season'));

    if ($form_state->getValue('save_league')) {
      \Drupal::database()->update('soccerbet_tournament')
        ->fields(['oldb_league' => $league, 'oldb_season' => $season])
        ->condition('tournament_id', $tournament_id)
        ->execute();
    }

    $group_only = (bool) $form_state->getValue('group_only');
    $stats = $this->importService->importAll($tournament_id, $league, $season, $group_only);

    if (!empty($stats['errors'])) {
      foreach ($stats['errors'] as $error) {
        $this->messenger()->addWarning($error);
      }
    }

    if (!empty($stats['teams_no_flag'])) {
      $this->messenger()->addWarning($this->t(
        'Kein Flag-Code gefunden für: @teams – bitte manuell setzen.',
        ['@teams' => implode(', ', $stats['teams_no_flag'])]
      ));
    }

    $this->messenger()->addStatus($this->t(
      'Import abgeschlossen: @tc Teams (@ts übersprungen), @gc Spiele (@gs übersprungen)@ko.',
      [
        '@tc' => $stats['teams_created'],
        '@ts' => $stats['teams_skipped'],
        '@gc' => $stats['games_created'],
        '@gs' => $stats['games_skipped'],
        '@ko' => ($stats['games_ko_skip'] ?? 0) > 0
          ? ', ' . $stats['games_ko_skip'] . ' KO-Spiele nicht importiert'
          : '',
      ]
    ));

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $tournament_id])
    );
  }
}
