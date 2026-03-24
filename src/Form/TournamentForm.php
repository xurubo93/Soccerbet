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
 * Formular: Turnier anlegen oder bearbeiten.
 */
final class TournamentForm extends FormBase {

  public function __construct(
    private readonly TournamentManager $tournamentManager,
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.tipper_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_tournament_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    // Bestehendes Turnier laden (Edit-Modus)
    $tournament = NULL;
    if ($tournament_id > 0) {
      $tournament = $this->tournamentManager->load($tournament_id);
      $form_state->set('tournament_id', $tournament_id);
    }

    $form['tournament_desc'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Turnier-Name'),
      '#maxlength'     => 128,
      '#required'      => TRUE,
      '#default_value' => $tournament?->tournament_desc ?? '',
    ];

    $group_options = $this->tipperManager->getGroupOptions();
    if (empty($group_options)) {
      $this->messenger()->addWarning($this->t('Bitte zuerst eine <a href=":url">Tippergruppe anlegen</a>.', [
        ':url' => Url::fromRoute('soccerbet.admin.tippergroups.create')->toString(),
      ]));
    }

    // Bereits zugeordnete Gruppen laden
    $current_group_ids = $tournament_id > 0
      ? $this->tournamentManager->loadTipperGroupIds($tournament_id)
      : [];

    $form['tipper_grp_ids'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Tippergruppen'),
      '#description'   => $this->t('Wähle eine oder mehrere Tippergruppen für dieses Turnier.'),
      '#options'       => $group_options,
      '#default_value' => $current_group_ids,
      '#required'      => TRUE,
    ];

    $form['dates'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Zeitraum'),
    ];
    $form['dates']['start_date'] = [
      '#type'          => 'date',
      '#title'         => $this->t('Startdatum'),
      '#required'      => TRUE,
      '#default_value' => $tournament ? substr($tournament->start_date, 0, 10) : '',
    ];
    $form['dates']['end_date'] = [
      '#type'          => 'date',
      '#title'         => $this->t('Enddatum'),
      '#required'      => TRUE,
      '#default_value' => $tournament ? substr($tournament->end_date, 0, 10) : '',
    ];

    $form['group_count'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Anzahl Gruppen in der Vorrunde'),
      '#min'           => 0,
      '#max'           => 16,
      '#default_value' => $tournament?->group_count ?? 4,
    ];

    $form['is_active'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Als Standard-Turnier setzen'),
      '#description'   => $this->t('Dieses Turnier wird auf der Rangliste und Tipp-Seite vorausgewählt.'),
      '#default_value' => $tournament?->is_active ?? 0,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $tournament_id ? $this->t('Turnier speichern') : $this->t('Turnier erstellen'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_filter($form_state->getValue('tipper_grp_ids') ?? []);
    if (empty($selected)) {
      $form_state->setErrorByName('tipper_grp_ids', $this->t('Bitte mindestens eine Tippergruppe auswählen.'));
    }
    $start = $form_state->getValue('start_date');
    $end   = $form_state->getValue('end_date');
    if ($start && $end && $start > $end) {
      $form_state->setErrorByName('end_date', $this->t('Das Enddatum muss nach dem Startdatum liegen.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [
      'tournament_desc' => $form_state->getValue('tournament_desc'),
      'tipper_grp_ids'  => array_keys(array_filter($form_state->getValue('tipper_grp_ids') ?? [])),
      'start_date'      => $form_state->getValue('start_date'),
      'end_date'        => $form_state->getValue('end_date'),
      'group_count'     => (int) $form_state->getValue('group_count'),
      'is_active'       => (int) $form_state->getValue('is_active'),
    ];

    $tournament_id = $form_state->get('tournament_id');
    if ($tournament_id) {
      $this->tournamentManager->update($tournament_id, $values);
      $this->messenger()->addStatus($this->t('Turnier "@name" wurde aktualisiert.', ['@name' => $values['tournament_desc']]));
    }
    else {
      $tournament_id = $this->tournamentManager->create($values);
      $this->messenger()->addStatus($this->t('Turnier "@name" wurde erstellt.', ['@name' => $values['tournament_desc']]));
    }

    // Wenn als aktiv markiert → Konfiguration updaten
    if ($values['is_active']) {
      \Drupal::configFactory()->getEditable('soccerbet.settings')
        ->set('default_tournament', $tournament_id)->save();
    }

    $form_state->setRedirectUrl(Url::fromRoute('soccerbet.admin.tournament.list'));
  }
}
