<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Einsätze der Teilnehmer als bezahlt markieren.
 *
 * Ersetzt den D6-tipper_has_paid-Mechanismus mit einer übersichtlichen
 * Admin-Tabelle pro Turnier.
 */
final class PaymentForm extends FormBase {

  public function __construct(
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tournament_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_payment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    // Turnier auflösen
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }
    if ($tournament_id === 0) {
      return ['#markup' => $this->t('No active tournament configured.')];
    }

    $tournament = $this->tournamentManager->load($tournament_id);
    $tippers    = $this->tournamentManager->loadTippers($tournament_id);
    $form_state->set('tournament_id', $tournament_id);

    $form['#title'] = $this->t('Payments – @name', ['@name' => $tournament->tournament_desc]);

    // Turnier-Auswahl (Schnellwechsel)
    $form['tournament_id'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Tournament'),
      '#options'       => $this->tournamentManager->getOptions(),
      '#default_value' => $tournament_id,
      '#ajax'          => [
        'callback' => '::ajaxRefresh',
        'wrapper'  => 'payment-table-wrapper',
      ],
    ];

    $form['table_wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'payment-table-wrapper'],
    ];

    if (empty($tippers)) {
      $form['table_wrapper']['empty'] = [
        '#markup' => '<p>' . $this->t('No participants in this tournament.') . '</p>',
      ];
      return $form;
    }

    // Zahlungstabelle
    $form['table_wrapper']['payments'] = [
      '#type'       => 'table',
      '#header'     => [
        $this->t('Name'),
        $this->t('Stake paid'),
        $this->t('Note'),
        $this->t('Confirmed on'),
      ],
      '#empty'      => $this->t('No participants.'),
    ];

    foreach ($tippers as $tipper) {
      $key = 'tipper_' . $tipper->tipper_id;

      $form['table_wrapper']['payments'][$key]['name'] = [
        '#plain_text' => $tipper->tipper_name,
      ];
      $form['table_wrapper']['payments'][$key]['paid'] = [
        '#type'          => 'checkbox',
        '#default_value' => (bool) $tipper->tipper_has_paid,
      ];
      $form['table_wrapper']['payments'][$key]['note'] = [
        '#type'          => 'textfield',
        '#maxlength'     => 255,
        '#size'          => 30,
        '#default_value' => $tipper->payment_note ?? '',
        '#placeholder'   => $this->t('e.g. bank transfer 15.06.'),
      ];
      $form['table_wrapper']['payments'][$key]['confirmed'] = [
        '#markup' => $tipper->confirmed_date
          ? \Drupal::service('date.formatter')->format($tipper->confirmed_date, 'short')
          : '—',
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save payment status'),
    ];

    return $form;
  }

  /**
   * AJAX-Callback für Turnier-Wechsel.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form['table_wrapper'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tournament_id = $form_state->get('tournament_id');
    $payments      = $form_state->getValue('payments') ?? [];

    foreach ($payments as $key => $row) {
      // key ist 'tipper_123'
      $tipper_id = (int) str_replace('tipper_', '', $key);
      $this->tournamentManager->setPaymentStatus(
        $tournament_id,
        $tipper_id,
        (bool) $row['paid'],
        $row['note'] !== '' ? $row['note'] : NULL,
      );
    }

    $this->messenger()->addStatus($this->t('Payment status has been updated.'));
    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.payments', ['tournament_id' => $tournament_id])
    );
  }
}
