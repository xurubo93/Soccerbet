<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formular: Gruppenbesitzer ordnet seine Gruppe einem Turnier zu.
 */
final class GroupTournamentsForm extends FormBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
    private readonly Connection $db,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('database'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_group_tournaments_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $group_slug = ''): array {
    $group = $this->tipperManager->loadGroupBySlug($group_slug);
    if (!$group) {
      throw new NotFoundHttpException();
    }

    $uid = (int) $this->currentUser()->id();
    if ((int) $group->tipper_admin_id !== $uid) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('group', $group);

    $grp_id = (int) $group->tipper_grp_id;

    $all_tournaments     = $this->tournamentManager->loadAll();
    $current_tournament_ids = $this->loadTournamentIdsForGroup($grp_id);

    $options = [];
    foreach ($all_tournaments as $t) {
      $label = $t->tournament_desc;
      if ($t->is_active) {
        $label .= ' (' . $this->t('aktiv') . ')';
      }
      $options[(int) $t->tournament_id] = $label;
    }

    if (empty($options)) {
      $form['notice'] = [
        '#markup' => '<p>' . $this->t('Es sind noch keine Turniere vorhanden.') . '</p>',
      ];
      $form['back'] = [
        '#markup' => '<p><a href="' . Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString() . '">'
          . $this->t('← Zurück zur Gruppenpage')
          . '</a></p>',
      ];
      return $form;
    }

    $form['tournaments'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Deiner Gruppe zugeordnete Turniere'),
      '#options'       => $options,
      '#default_value' => $current_tournament_ids,
      '#description'   => $this->t('Wähle die Turniere, an denen deine Gruppe teilnehmen soll.'),
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Speichern'),
    ];

    $form['back'] = [
      '#markup' => '<p><a href="' . Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString() . '">'
        . $this->t('← Zurück zur Gruppenpage')
        . '</a></p>',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $group  = $form_state->get('group');
    $grp_id = (int) $group->tipper_grp_id;

    $selected     = array_filter((array) $form_state->getValue('tournaments'));
    $selected_ids = array_map('intval', array_keys($selected));
    $current_ids  = $this->loadTournamentIdsForGroup($grp_id);

    $to_add    = array_diff($selected_ids, $current_ids);
    $to_remove = array_diff($current_ids, $selected_ids);

    // Neue Turniere hinzufügen
    foreach ($to_add as $tid) {
      $this->db->merge('soccerbet_tournament_groups')
        ->keys(['tournament_id' => $tid, 'tipper_grp_id' => $grp_id])
        ->execute();

      // Alle Tipper der Gruppe dem Turnier hinzufügen
      foreach ($this->tipperManager->loadTippersByGroup($grp_id) as $tipper) {
        $this->tournamentManager->addTipper($tid, (int) $tipper->tipper_id);
      }
    }

    // Turnier-Zuordnungen entfernen (Tipper-Daten bleiben erhalten)
    foreach ($to_remove as $tid) {
      $this->db->delete('soccerbet_tournament_groups')
        ->condition('tournament_id', $tid)
        ->condition('tipper_grp_id', $grp_id)
        ->execute();
    }

    $this->messenger()->addStatus($this->t('Turnier-Zuordnung gespeichert.'));

    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.group.page', ['group_slug' => $group->group_slug])
    );
  }

  /**
   * Gibt die IDs aller Turniere zurück, denen diese Gruppe zugeordnet ist.
   *
   * @return int[]
   */
  private function loadTournamentIdsForGroup(int $tipper_grp_id): array {
    $rows = $this->db->select('soccerbet_tournament_groups', 'tg')
      ->fields('tg', ['tournament_id'])
      ->condition('tg.tipper_grp_id', $tipper_grp_id)
      ->execute()->fetchAll();
    return array_map(fn($r) => (int) $r->tournament_id, $rows);
  }

}
