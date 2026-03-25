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
      return ['#markup' => $this->t('Tournament not found.')];
    }

    $form_state->set('tournament_id', $tournament_id);

    $active_provider = $this->clientFactory->getActiveProvider();
    $api_name        = $this->importService->getApiName();

    $form['#attributes'] = ['class' => ['soccerbet-import-form']];

    $form['tournament_info'] = [
      '#type'   => 'item',
      '#markup' => '<div class="soccerbet-form-tournament-info">'
        . $this->t('Tournament: <strong>@name</strong>', ['@name' => $tournament->tournament_desc])
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
        . $this->t('Active API: <strong>@api</strong>. <a href=":url">Change API settings</a>.', [
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
            . $this->t('No API key for football-data.org configured. <a href=":url">Enter now</a>.', [
              ':url' => $settings_url,
            ])
            . '</p>',
        ];
      }
    }

    $form['info'] = [
      '#type'   => 'item',
      '#markup' => '<p>' . $this->t('Imports all teams and matches. Existing ones will be skipped.') . '</p>',
    ];

    $form['league'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('League / Competition code'),
      '#description'   => $this->importService->getLeagueHelp(),
      '#default_value' => $tournament->oldb_league ?? '',
      '#required'      => TRUE,
      '#size'          => 20,
    ];

    $form['season'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Season'),
      '#description'   => $this->importService->getSeasonHelp(),
      '#default_value' => $tournament->oldb_season ?? '',
      '#required'      => TRUE,
      '#size'          => 10,
    ];

    $form['options'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Options'),
    ];
    $form['options']['group_only'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Import group stage matches only'),
      '#description'   => $this->t('KO rounds (round of 16, quarter-finals, …) will be skipped and can be created manually.'),
      '#default_value' => TRUE,
    ];
    $form['options']['save_league'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Save league/season at tournament'),
      '#description'   => $this->t('Used for automatic score updates via cron.'),
      '#default_value' => TRUE,
    ];

    $form['submit'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Start import (@api)', ['@api' => $api_name]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['cancel'] = [
      '#type'       => 'link',
      '#title'      => $this->t('Cancel'),
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
        'No flag code found for: @teams – please set manually.',
        ['@teams' => implode(', ', $stats['teams_no_flag'])]
      ));
    }

    $this->messenger()->addStatus($this->t(
      'Import complete: @tc teams (@ts skipped), @gc matches (@gs skipped)@ko.',
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
