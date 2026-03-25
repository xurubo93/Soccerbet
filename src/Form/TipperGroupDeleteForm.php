<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bestätigungs-Dialog: Tippergruppe löschen.
 */
final class TipperGroupDeleteForm extends ConfirmFormBase {

  private ?object $group = NULL;

  public function __construct(
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tipper_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_tippergroup_delete_form';
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Really delete betting group "@name"?', [
      '@name' => $this->group?->tipper_grp_name ?? '',
    ]);
  }

  public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('This will permanently delete all bettors, bets, invitations and tournament assignments of this group.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('soccerbet.admin.tippergroups.list');
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tipper_grp_id = 0): array {
    $this->group = $this->tipperManager->loadGroup($tipper_grp_id);
    $form_state->set('tipper_grp_id', $tipper_grp_id);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->tipperManager->deleteGroup($form_state->get('tipper_grp_id'));
    $this->messenger()->addStatus($this->t('Betting group has been deleted.'));
    $form_state->setRedirectUrl(Url::fromRoute('soccerbet.admin.tippergroups.list'));
  }

}
