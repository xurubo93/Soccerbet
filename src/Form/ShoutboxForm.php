<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\soccerbet\Service\ShoutboxService;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shoutbox-Eingabeformular.
 */
final class ShoutboxForm extends FormBase {

  public function __construct(
    private readonly ShoutboxService $shoutbox,
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.shoutbox'),
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_shoutbox_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }

    if ($tournament_id === 0 || $this->currentUser()->isAnonymous()) {
      return [];
    }

    $form_state->set('tournament_id', $tournament_id);

    // Tipper-Name ermitteln
    $uid        = (int) $this->currentUser()->id();
    $tournament = $this->tournamentManager->load($tournament_id);
    $tipper      = NULL;
    foreach ($this->tournamentManager->loadTipperGroupIds($tournament_id) as $group_id) {
      $tipper = $this->tipperManager->loadTipperByUid($uid, $group_id);
      if ($tipper) {
        break;
      }
    }
    $tipper_name = $tipper?->tipper_name ?? $this->currentUser()->getDisplayName();

    $form_state->set('tipper_name', $tipper_name);

    $form['#attributes'] = ['class' => ['soccerbet-shoutbox-form']];

    $form['message'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Message from @name', ['@name' => $tipper_name]),
      '#rows'        => 2,
      '#maxlength'   => 500,
      '#placeholder' => $this->t('Your message …'),
      '#required'    => TRUE,
    ];

    $form['submit'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Send'),
      '#attributes' => ['class' => ['button', 'button--primary', 'button--small']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tournament_id = (int) $form_state->get('tournament_id');
    $tipper_name   = (string) $form_state->get('tipper_name');
    $message       = (string) $form_state->getValue('message');

    $this->shoutbox->postMessage($tournament_id, $tipper_name, $message);
    $this->messenger()->addStatus($this->t('Message posted!'));

    // Cache-Tag invalidieren damit Block sofort aktualisiert wird
    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_shoutbox:' . $tournament_id]);
  }
}
