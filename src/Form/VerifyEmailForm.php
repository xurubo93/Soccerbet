<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: E-Mail-Bestätigungscode eingeben (Schritt 2).
 *
 * Nach erfolgreicher Verifikation werden Drupal-Konto, Tippergruppe und
 * Tipper-Eintrag angelegt und der Nutzer automatisch eingeloggt.
 */
final class VerifyEmailForm extends FormBase {

  public function __construct(
    private readonly Connection $db,
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_verify_email_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $token = ''): array {
    $pending = $this->loadPending($token);

    if (!$pending) {
      return [
        '#markup' => '<p class="messages messages--error">'
          . $this->t('Dieser Bestätigungslink ist ungültig oder abgelaufen. Bitte <a href=":url">erneut registrieren</a>.', [
            ':url' => Url::fromRoute('soccerbet.register')->toString(),
          ])
          . '</p>',
      ];
    }

    $form_state->set('token', $token);

    $form['info'] = [
      '#markup' => '<p>' . $this->t(
          'Wir haben einen 6-stelligen Code an <strong>@email</strong> gesendet. Bitte gib ihn unten ein.',
          ['@email' => $pending->email]
        ) . '</p>',
    ];
    $form['code'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Bestätigungscode'),
      '#maxlength'   => 6,
      '#size'        => 8,
      '#required'    => TRUE,
      '#attributes'  => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric'],
      '#description' => $this->t('Schau auch im Spam-Ordner nach.'),
    ];
    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Bestätigen & Tippspiel starten'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $token   = $form_state->get('token');
    $pending = $this->loadPending($token);

    if (!$pending) {
      $form_state->setErrorByName('code', $this->t('Ungültiger oder abgelaufener Link.'));
      return;
    }

    if (trim($form_state->getValue('code')) !== $pending->verify_code) {
      $form_state->setErrorByName('code', $this->t('Falscher Code. Bitte versuche es erneut.'));
    }

    $form_state->set('pending', $pending);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pending = $form_state->get('pending');
    $token   = $form_state->get('token');

    // 1. Drupal-Benutzerkonto anlegen
    $user = User::create([
      'name'   => $pending->email,
      'mail'   => $pending->email,
      'status' => 1,
    ]);
    $user->setPassword($pending->password);
    $user->save();
    $uid = (int) $user->id();

    // 2. Benutzer einloggen (damit currentUser in TipperManager korrekt ist)
    user_login_finalize($user);

    // 3. Tippergruppe anlegen
    $grp_id = $this->tipperManager->createGroup(
      $pending->group_name,
      $uid,
      $pending->group_slug,
    );

    // 4. Tipper-Eintrag anlegen
    $tipper_name = ucfirst(explode('@', $pending->email)[0]);
    $tipper_id   = $this->tipperManager->createTipper($uid, $grp_id, $tipper_name);

    // 5. Gruppe und Tipper dem aktiven Turnier zuordnen
    $tournament_id = (int) $pending->tournament_id;
    $this->tournamentManager->setTipperGroups($tournament_id, [
      ...$this->tournamentManager->loadTipperGroupIds($tournament_id),
      $grp_id,
    ]);
    $this->tournamentManager->addTipper($tournament_id, $tipper_id);

    // 6. Ausstehende Registrierung sofort löschen (Passwort-Sicherheit)
    $this->db->delete('soccerbet_pending_registrations')
      ->condition('token', $token)
      ->execute();

    $this->messenger()->addStatus($this->t(
      'Willkommen! Dein Tippspiel „@group" ist bereit.',
      ['@group' => $pending->group_name]
    ));

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.group.invite', ['group_slug' => $pending->group_slug])
    );
  }

  /**
   * Lädt eine ausstehende Registrierung anhand des Tokens (nur wenn nicht abgelaufen).
   */
  private function loadPending(string $token): ?object {
    if ($token === '') {
      return NULL;
    }
    $now = \Drupal::time()->getRequestTime();
    return $this->db->select('soccerbet_pending_registrations', 'pr')
      ->fields('pr')
      ->condition('pr.token', $token)
      ->condition('pr.expires', $now, '>')
      ->execute()->fetchObject() ?: NULL;
  }
}
