<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Tippergruppe erstellen oder bearbeiten.
 *
 * Mitglieder werden als Tabelle dargestellt:
 *   Tipp-Name | Drupal-User (Dropdown) | Löschen
 * Darunter ein Zeile zum Hinzufügen eines neuen Tippers.
 */
final class TipperGroupForm extends FormBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tipper_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_tipper_group_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tipper_grp_id = 0): array {
    $tipper_grp_id = (int) $tipper_grp_id;
    $group = NULL;
    if ($tipper_grp_id > 0) {
      $group = $this->tipperManager->loadGroup($tipper_grp_id);
      $form_state->set('tipper_grp_id', $tipper_grp_id);
    }

    // ------------------------------------------------------------------ //
    // Gruppenname + Admin                                                  //
    // ------------------------------------------------------------------ //
    $form['tipper_grp_name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Name der Gruppe'),
      '#maxlength'     => 64,
      '#required'      => TRUE,
      '#default_value' => $group?->tipper_grp_name ?? '',
    ];

    $form['tipper_admin_id'] = [
      '#type'               => 'entity_autocomplete',
      '#target_type'        => 'user',
      '#title'              => $this->t('Gruppen-Administrator'),
      '#description'        => $this->t('Dieser Benutzer kann die Gruppe verwalten.'),
      '#required'           => TRUE,
      '#default_value'      => $group ? User::load((int) $group->tipper_admin_id) : NULL,
      '#selection_settings' => ['include_anonymous' => FALSE],
    ];

    // ------------------------------------------------------------------ //
    // Tipper-Tabelle (nur im Edit-Modus)                                  //
    // ------------------------------------------------------------------ //
    if ($tipper_grp_id > 0) {
      $members    = $this->tipperManager->loadTippersByGroup($tipper_grp_id);
      $user_options = $this->getUserOptions();

      $form['members'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Tipper (@count)', ['@count' => count($members)]),
        '#tree'  => TRUE,
      ];

      // Tabellen-Header als Markup
      $form['members']['header'] = [
        '#markup' => '<div class="soccerbet-tipper-table-wrap"><table class="soccerbet-tipper-table">'
          . '<thead><tr>'
          . '<th>' . $this->t('Tipp-Name') . '</th>'
          . '<th>' . $this->t('Drupal-User') . '</th>'
          . '<th>' . $this->t('Löschen') . '</th>'
          . '</tr></thead><tbody>',
      ];

      foreach ($members as $member) {
        $tipper_id = (int) $member->tipper_id;

        $form['members'][$tipper_id] = [];

        $form['members'][$tipper_id]['_row_open'] = [
          '#markup' => '<tr>',
        ];

        $form['members'][$tipper_id]['tipper_name'] = [
          '#type'          => 'textfield',
          '#title'         => '',
          '#title_display' => 'invisible',
          '#default_value' => $member->tipper_name,
          '#maxlength'     => 64,
          '#size'          => 16,
          '#required'      => TRUE,
          '#prefix'        => '<td>',
          '#suffix'        => '</td>',
        ];

        $form['members'][$tipper_id]['uid'] = [
          '#type'          => 'select',
          '#title'         => '',
          '#title_display' => 'invisible',
          '#options'       => [0 => $this->t('— kein User —')] + $user_options,
          '#default_value' => (int) $member->uid,
          '#attributes'    => ['class' => ['soccerbet-tipper-uid-select']],
          '#prefix'        => '<td>',
          '#suffix'        => '</td>',
        ];

        $form['members'][$tipper_id]['delete'] = [
          '#type'          => 'checkbox',
          '#title'         => '',
          '#title_display' => 'invisible',
          '#prefix'        => '<td style="text-align:center">',
          '#suffix'        => '</td></tr>',
        ];
      }

      $form['members']['footer'] = [
        '#markup' => '</tbody></table></div>',
      ];

      // ---------------------------------------------------------------- //
      // Neuen Tipper hinzufügen                                           //
      // ---------------------------------------------------------------- //
      $form['new_tipper'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Neuen Tipper hinzufügen'),
        '#tree'  => FALSE,
      ];

      $form['new_tipper']['new_tipper_name'] = [
        '#type'      => 'textfield',
        '#title'     => $this->t('Tipp-Name'),
        '#maxlength' => 64,
        '#size'      => 28,
      ];

      $form['new_tipper']['new_tipper_uid'] = [
        '#type'    => 'select',
        '#title'   => $this->t('Drupal-User'),
        '#options' => [0 => $this->t('— kein User —')] + $user_options,
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $tipper_grp_id ? $this->t('Gruppe speichern') : $this->t('Gruppe erstellen'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name          = $form_state->getValue('tipper_grp_name');
    $admin_id      = (int) $form_state->getValue('tipper_admin_id');
    $tipper_grp_id = (int) $form_state->get('tipper_grp_id');

    if ($tipper_grp_id) {
      $this->tipperManager->updateGroup($tipper_grp_id, $name, $admin_id);

      // Bestehende Tipper aktualisieren oder löschen
      $members_values = $form_state->getValue('members') ?? [];
      foreach ($members_values as $tipper_id => $values) {
        if (!is_array($values)) {
          continue;
        }
        if (!empty($values['delete'])) {
          $this->tipperManager->deleteTipper((int) $tipper_id);
          continue;
        }
        $this->tipperManager->updateTipperWithUid(
          (int) $tipper_id,
          (string) ($values['tipper_name'] ?? ''),
          (int) ($values['uid'] ?? 0)
        );
      }

      // Neuen Tipper anlegen
      $new_name = trim((string) ($form_state->getValue('new_tipper_name') ?? ''));
      if ($new_name !== '') {
        $new_uid = (int) $form_state->getValue('new_tipper_uid');
        $this->tipperManager->createTipper($new_uid, $tipper_grp_id, $new_name);
        $this->messenger()->addStatus($this->t('Tipper "@name" wurde hinzugefügt.', ['@name' => $new_name]));
      }

      $this->messenger()->addStatus($this->t('Gruppe "@name" wurde gespeichert.', ['@name' => $name]));
    }
    else {
      $this->tipperManager->createGroup($name, $admin_id);
      $this->messenger()->addStatus($this->t('Gruppe "@name" wurde erstellt.', ['@name' => $name]));
    }

    $form_state->setRedirectUrl(Url::fromRoute('soccerbet.admin.tippergroups.list'));
  }

  /**
   * Lädt alle Drupal-User als Dropdown-Optionen (außer User 0/anonymous).
   *
   * @return array<int, string>
   */
  private function getUserOptions(): array {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['status' => 1]);

    $options = [];
    foreach ($users as $user) {
      if ((int) $user->id() === 0) {
        continue;
      }
      $options[(int) $user->id()] = $user->getDisplayName()
        . ' (' . $user->getEmail() . ')';
    }
    asort($options);
    return $options;
  }
}
