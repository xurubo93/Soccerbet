<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formular: Freunde zur Tippergruppe einladen (bis zu 4 zusätzliche Personen).
 */
final class InviteForm extends FormBase {

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
    return 'soccerbet_invite_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $group_slug = ''): array {
    $group = $this->tipperManager->loadGroupBySlug($group_slug);
    if (!$group) {
      throw new NotFoundHttpException();
    }

    $uid = (int) $this->currentUser()->id();
    if ((int) $group->tipper_admin_id !== $uid) {
      return ['#markup' => $this->t('Du hast keine Berechtigung, diese Gruppe zu verwalten.')];
    }

    $form_state->set('group', $group);

    // Aktuelle Mitgliederzahl
    $current_members = count($this->tipperManager->loadTippersByGroup((int) $group->tipper_grp_id));
    $max             = (int) $group->max_members;
    $remaining       = max(0, $max - $current_members);

    // Öffentliche Gruppen-URL
    $group_url = Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])
      ->setAbsolute()
      ->toString();

    $form['group_url_info'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Dein Tippspiel-Link'),
    ];
    $form['group_url_info']['url_display'] = [
      '#markup' => '<div class="soccerbet-invite__url">'
        . '<input type="text" readonly value="' . htmlspecialchars($group_url) . '" '
        . 'class="soccerbet-invite__url-input" id="soccerbet-group-url" />'
        . '<button type="button" class="button button--small soccerbet-invite__copy-btn" '
        . 'onclick="navigator.clipboard.writeText(document.getElementById(\'soccerbet-group-url\').value);this.textContent=\'✓ Kopiert\'">'
        . $this->t('Link kopieren')
        . '</button></div>',
    ];

    $form['invites'] = [
      '#type'        => 'fieldset',
      '#title'       => $this->t('Per E-Mail einladen'),
      '#description' => $this->t(
        'Freie Plätze: @remaining von @max. Du kannst bis zu @n E-Mail-Adressen eingeben.',
        ['@remaining' => $remaining, '@max' => $max, '@n' => min(4, $remaining)]
      ),
    ];

    $slots = min(4, max(0, $remaining));
    for ($i = 1; $i <= $slots; $i++) {
      $form['invites']['invite_email_' . $i] = [
        '#type'     => 'email',
        '#title'    => $this->t('E-Mail @n', ['@n' => $i]),
        '#required' => FALSE,
      ];
    }

    if ($slots === 0) {
      $form['invites']['full_notice'] = [
        '#markup' => '<p>' . $this->t('Die Gruppe ist voll. Upgrade auf Premium für bis zu 15 Personen.') . '</p>',
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Einladungen senden'),
      '#access' => $slots > 0,
    ];

    $form['skip'] = [
      '#markup' => '<p><a href="' . Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString() . '">'
        . $this->t('Zum Tippspiel →')
        . '</a></p>',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $group     = $form_state->get('group');
    $grp_id    = (int) $group->tipper_grp_id;
    $group_url = Url::fromRoute('soccerbet.group.page', ['group_slug' => $group->group_slug])
      ->setAbsolute()->toString();
    $inviter   = $this->currentUser()->getDisplayName();
    $lang      = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $now       = \Drupal::time()->getRequestTime();
    $expires   = $now + 7 * 24 * 3600;
    $sent      = 0;

    $values = $form_state->getValues();
    $emails = array_filter(array_map('trim', array_filter(
      array_map(fn($k) => $values[$k] ?? '', ['invite_email_1', 'invite_email_2', 'invite_email_3', 'invite_email_4'])
    )));

    foreach ($emails as $email) {
      // Einladungs-Token anlegen
      $invite_token = bin2hex(random_bytes(32));
      $this->db->insert('soccerbet_invitations')
        ->fields([
          'invite_token'  => $invite_token,
          'tipper_grp_id' => $grp_id,
          'invited_email' => $email,
          'expires'       => $expires,
          'used'          => 0,
        ])->execute();

      $join_url = Url::fromRoute('soccerbet.group.join', [
        'group_slug'   => $group->group_slug,
        'invite_token' => $invite_token,
      ])->setAbsolute()->toString();

      \Drupal::service('plugin.manager.mail')->mail(
        'soccerbet',
        'group_invite',
        $email,
        $lang,
        [
          'group_name'   => $group->tipper_grp_name,
          'inviter_name' => $inviter,
          'join_url'     => $join_url,
        ],
      );
      $sent++;
    }

    if ($sent > 0) {
      $this->messenger()->addStatus($this->t('@n Einladung(en) wurden versendet.', ['@n' => $sent]));
    }

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.group.page', ['group_slug' => $group->group_slug])
    );
  }
}
