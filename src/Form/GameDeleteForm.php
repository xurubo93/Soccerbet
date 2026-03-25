<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bestätigungs-Dialog: Spiel löschen.
 */
final class GameDeleteForm extends ConfirmFormBase {

  private int $tournament_id = 0;

  public function __construct(
    private readonly TipperManager $tipperManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.tipper_manager'));
  }

  public function getFormId(): string {
    return 'soccerbet_game_delete_form';
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Really delete this match?');
  }

  public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('All bets for this match will also be deleted.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $this->tournament_id]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $game_id = 0): array {
    $game                = $this->tipperManager->loadGame($game_id);
    $this->tournament_id = (int) $game->tournament_id;
    $form_state->set('game_id', $game_id);
    $form_state->set('tournament_id', $this->tournament_id);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->tipperManager->deleteGame($form_state->get('game_id'));
    $this->messenger()->addStatus($this->t('Match has been deleted.'));
    $form_state->setRedirectUrl(
      Url::fromRoute('soccerbet.admin.games.list', ['tournament_id' => $form_state->get('tournament_id')])
    );
  }
}
