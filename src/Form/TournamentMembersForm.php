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
 * Formular: Tipper einem Turnier zuweisen/entfernen + Tipps bearbeiten.
 */
final class TournamentMembersForm extends FormBase {

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
    return 'soccerbet_tournament_members_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    $tournament = $this->tournamentManager->load($tournament_id);
    $form_state->set('tournament_id', $tournament_id);

    // Alle Tipper aus allen Gruppen des Turniers
    $all_tippers = [];
    foreach ($this->tournamentManager->loadTipperGroupIds($tournament_id) as $group_id) {
      foreach ($this->tipperManager->loadTippersByGroup($group_id) as $tipper) {
        $all_tippers[$tipper->tipper_id] = $tipper;
      }
    }

    $current_tippers = $this->tournamentManager->loadTippers($tournament_id);
    $current_ids     = array_map('intval', array_column($current_tippers, 'tipper_id'));

    $form['#title'] = $this->t('Teilnehmer – @name', ['@name' => $tournament->tournament_desc]);

    if (empty($all_tippers)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('Keine Tipper in der zugehörigen Gruppe. Bitte zuerst <a href=":url">Tipper anlegen</a>.', [
          ':url' => Url::fromRoute('soccerbet.admin.tippergroups.list')->toString(),
        ]) . '</p>',
      ];
      return $form;
    }

    // #type => 'table': Formular-Elemente in Zellen funktionieren korrekt
    $form['members'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Teilnehmer'),
        $this->t('Im Turnier'),
        $this->t('Aktionen'),
      ],
      '#empty'  => $this->t('Keine Tipper verfügbar.'),
    ];

    foreach ($all_tippers as $tipper) {
      $tid       = (int) $tipper->tipper_id;
      $is_member = in_array($tid, $current_ids, TRUE);
      $row_key   = 'tipper_' . $tid;

      $form['members'][$row_key]['name'] = [
        '#markup' => $tipper->tipper_name,
      ];

      $form['members'][$row_key]['member'] = [
        '#type'          => 'checkbox',
        '#default_value' => $is_member ? 1 : 0,
        '#title'         => '',
        '#title_display' => 'invisible',
      ];

      $links = [];
      if ($is_member) {
        $links['edit_tipps'] = [
          'title' => $this->t('Tipps bearbeiten'),
          'url'   => Url::fromRoute('soccerbet.admin.tipps.edit', [
            'tournament_id' => $tournament_id,
            'tipper_id'     => $tid,
          ]),
        ];
      }
      $form['members'][$row_key]['operations'] = [
        '#type'  => 'operations',
        '#links' => $links,
      ];
    }

    // --- Turniersieger pro Tippergruppe ---
    $groups        = $this->tournamentManager->loadTipperGroups($tournament_id);
    $group_winners = $this->tournamentManager->loadGroupWinners($tournament_id);
    $winner_group_ids = [];

    $form['winners'] = [
      '#type'        => 'fieldset',
      '#title'       => $this->t('Turniersieger ★'),
      '#description' => $this->t('Der Sieger erhält einen Stern (★) neben seinem Namen in allen Ranglisten.'),
    ];

    foreach ($groups as $group) {
      $grp_id = (int) $group->tipper_grp_id;
      $winner_group_ids[] = $grp_id;

      $tipper_options = [0 => $this->t('— noch offen —')];
      foreach ($this->tipperManager->loadTippersByGroup($grp_id) as $t) {
        $tipper_options[(int) $t->tipper_id] = $t->tipper_name;
      }
      asort($tipper_options);

      $existing = $group_winners[$grp_id] ?? NULL;

      $form['winners']['group_' . $grp_id] = [
        '#type'  => 'fieldset',
        '#title' => $group->tipper_grp_name,
      ];
      $form['winners']['group_' . $grp_id]['winner_1_' . $grp_id] = [
        '#type'          => 'select',
        '#title'         => $this->t('★ 1. Platz'),
        '#options'       => $tipper_options,
        '#default_value' => (int) ($existing?->winner_tipper_id ?? 0),
      ];
      $form['winners']['group_' . $grp_id]['winner_2_' . $grp_id] = [
        '#type'          => 'select',
        '#title'         => $this->t('2. Platz'),
        '#options'       => $tipper_options,
        '#default_value' => (int) ($existing?->second_tipper_id ?? 0),
      ];
      $form['winners']['group_' . $grp_id]['winner_3_' . $grp_id] = [
        '#type'          => 'select',
        '#title'         => $this->t('3. Platz'),
        '#options'       => $tipper_options,
        '#default_value' => (int) ($existing?->third_tipper_id ?? 0),
      ];
    }

    $form_state->set('winner_group_ids', $winner_group_ids);

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Teilnehmer speichern'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tournament_id = $form_state->get('tournament_id');
    $members_data  = $form_state->getValue('members') ?? [];

    $current_tippers = $this->tournamentManager->loadTippers($tournament_id);
    $current_ids     = array_map('intval', array_column($current_tippers, 'tipper_id'));

    foreach ($members_data as $row_key => $row) {
      $tid       = (int) str_replace('tipper_', '', $row_key);
      $checked   = (bool) ($row['member'] ?? FALSE);
      $is_member = in_array($tid, $current_ids, TRUE);

      if ($checked && !$is_member) {
        $this->tournamentManager->addTipper($tournament_id, $tid);
      }
      elseif (!$checked && $is_member) {
        $this->tournamentManager->removeTipper($tournament_id, $tid);
      }
    }

    // Sieger pro Tippergruppe speichern
    foreach ($form_state->get('winner_group_ids') ?? [] as $grp_id) {
      $this->tournamentManager->saveGroupWinners($tournament_id, $grp_id, [
        'winner_tipper_id' => ($form_state->getValue('winner_1_' . $grp_id) ?: NULL),
        'second_tipper_id' => ($form_state->getValue('winner_2_' . $grp_id) ?: NULL),
        'third_tipper_id'  => ($form_state->getValue('winner_3_' . $grp_id) ?: NULL),
      ]);
    }

    $this->messenger()->addStatus($this->t('Teilnehmer wurden aktualisiert.'));
    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.tournament.list')
    );
  }
}
