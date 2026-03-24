<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Gratis-Tippspiel erstellen (Schritt 1 – Daten + E-Mail-Versand).
 */
final class RegisterGroupForm extends FormBase {

  /** Slugs, die bereits als Pfadsegmente unter /soccerbet/ reserviert sind. */
  private const RESERVED_SLUGS = [
    'standings', 'tables', 'tipps', 'admin', 'live', 'register',
    'shoutbox', 'gruppe',
  ];

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly Connection $db,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_register_group_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    if (!$tournament_id) {
      return ['#markup' => $this->t('Derzeit ist kein aktives Turnier verfügbar.')];
    }

    $form['#attributes']['class'][] = 'soccerbet-register-form';

    $form['intro'] = [
      '#markup' => '<p class="soccerbet-register__intro">'
        . $this->t('Erstelle kostenlos dein eigenes Tippspiel und lade bis zu 4 Freunde ein.')
        . '</p>',
    ];

    $form['group_name'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Name deiner Tippergruppe'),
      '#required'    => TRUE,
      '#maxlength'   => 64,
      '#description' => $this->t('Dieser Name erscheint auf der öffentlichen Gruppen-Seite.'),
    ];
    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('E-Mail-Adresse'),
      '#required' => TRUE,
    ];
    $form['password'] = [
      '#type'        => 'password',
      '#title'       => $this->t('Passwort'),
      '#required'    => TRUE,
      '#description' => $this->t('Mindestens 8 Zeichen.'),
    ];
    $form['password_confirm'] = [
      '#type'     => 'password',
      '#title'    => $this->t('Passwort bestätigen'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Tippspiel erstellen →'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $pass  = $form_state->getValue('password');
    $pass2 = $form_state->getValue('password_confirm');
    $email = trim($form_state->getValue('email'));
    $name  = trim($form_state->getValue('group_name'));

    if ($pass !== $pass2) {
      $form_state->setErrorByName('password_confirm', $this->t('Die Passwörter stimmen nicht überein.'));
    }
    if (strlen($pass) < 8) {
      $form_state->setErrorByName('password', $this->t('Das Passwort muss mindestens 8 Zeichen lang sein.'));
    }

    // E-Mail darf noch nicht als Drupal-Konto existieren
    if (user_load_by_mail($email)) {
      $form_state->setErrorByName('email', $this->t(
        'Diese E-Mail-Adresse ist bereits registriert. <a href=":url">Jetzt einloggen</a>.',
        [':url' => Url::fromRoute('user.login')->toString()]
      ));
    }

    // Gruppenname eindeutig
    $exists = $this->db->select('soccerbet_tipper_groups', 'g')
      ->fields('g', ['tipper_grp_id'])
      ->condition('g.tipper_grp_name', $name)
      ->execute()->fetchField();
    if ($exists) {
      $form_state->setErrorByName('group_name', $this->t('Dieser Gruppenname ist bereits vergeben.'));
    }

    // Slug prüfen (wird in buildForm noch nicht generiert – hier Pre-Check)
    $slug = $this->tipperManager->generateGroupSlug($name);
    if (in_array($slug, self::RESERVED_SLUGS, TRUE)) {
      $form_state->setErrorByName('group_name', $this->t('Dieser Gruppenname ist leider reserviert.'));
    }

    // Slug für submitForm vorhalten
    $form_state->set('generated_slug', $slug);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    $email         = trim($form_state->getValue('email'));
    $group_name    = trim($form_state->getValue('group_name'));
    $password      = $form_state->getValue('password');
    $slug          = $form_state->get('generated_slug')
      ?? $this->tipperManager->generateGroupSlug($group_name);

    // 6-stelliger Code + Token
    $code  = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(32));
    $now   = \Drupal::time()->getRequestTime();

    // Ausstehende Registrierung speichern
    $this->db->insert('soccerbet_pending_registrations')
      ->fields([
        'token'         => $token,
        'email'         => $email,
        'password'      => $password,
        'group_name'    => $group_name,
        'group_slug'    => $slug,
        'verify_code'   => $code,
        'tournament_id' => $tournament_id,
        'created'       => $now,
        'expires'       => $now + 3600,
      ])
      ->execute();

    // Bestätigungs-E-Mail versenden
    \Drupal::service('plugin.manager.mail')->mail(
      'soccerbet',
      'email_verify',
      $email,
      \Drupal::languageManager()->getCurrentLanguage()->getId(),
      ['code' => $code, 'group_name' => $group_name],
    );

    $this->messenger()->addStatus($this->t(
      'Wir haben einen Bestätigungscode an @email gesendet.',
      ['@email' => $email]
    ));

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.register.verify', ['token' => $token])
    );
  }
}
