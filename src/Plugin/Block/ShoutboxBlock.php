<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\soccerbet\Service\ShoutboxService;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shoutbox-Block.
 *
 * @Block(
 *   id = "soccerbet_shoutbox",
 *   admin_label = @Translation("Soccerbet Shoutbox"),
 *   category = @Translation("Soccerbet"),
 * )
 */
final class ShoutboxBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ShoutboxService $shoutbox,
    private readonly TournamentManager $tournamentManager,
    private readonly FormBuilderInterface $formBuilder,
    private readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('soccerbet.shoutbox'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('form_builder'),
      $container->get('current_user'),
    );
  }

  public function build(): array {
    $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    if ($tournament_id === 0) {
      return [];
    }

    $messages = $this->shoutbox->loadMessages($tournament_id, 30);
    $can_delete = $this->currentUser->hasPermission('administer soccerbet');

    $build = [
      '#theme'         => 'soccerbet_shoutbox',
      '#messages'      => $messages,
      '#tournament_id' => $tournament_id,
      '#can_delete'    => $can_delete,
      '#cache'         => [
        'tags'    => ['soccerbet_shoutbox:' . $tournament_id],
        'max-age' => 60,
        'contexts' => ['user'],
      ],
    ];

    // Eingabe-Formular für eingeloggte User
    if (!$this->currentUser->isAnonymous()) {
      $build['#form'] = $this->formBuilder->getForm(
        'Drupal\soccerbet\Form\ShoutboxForm',
        $tournament_id
      );
    }

    return $build;
  }
}
