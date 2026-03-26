<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bestätigungs-Dialog: Alle Turnierdaten zurücksetzen.
 */
final class TournamentResetForm extends ConfirmFormBase {

  private ?object $tournament = NULL;

  public function __construct(
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tournament_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_tournament_reset_form';
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Reset all data for tournament "@name"?', [
      '@name' => $this->tournament?->tournament_desc ?? '',
    ]);
  }

  public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('This will permanently delete all teams, matches, bets, participant assignments and group assignments for this tournament. Only the tournament itself will be kept.');
  }

  public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Reset data');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('soccerbet.admin.tournament.list');
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $tournament_id = 0): array {
    $this->tournament = $this->tournamentManager->load($tournament_id);
    $form_state->set('tournament_id', $tournament_id);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->tournamentManager->resetData($form_state->get('tournament_id'));
    $this->messenger()->addStatus($this->t('Tournament data has been reset.'));
    $form_state->setRedirectUrl(Url::fromRoute('soccerbet.admin.tournament.list'));
  }
}
