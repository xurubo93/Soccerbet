<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\ShoutboxService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Shoutbox-Verwaltung.
 */
final class ShoutboxController extends ControllerBase {

  public function __construct(
    private readonly ShoutboxService $shoutbox,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('soccerbet.shoutbox'));
  }

  public function delete(int $shout_id): RedirectResponse {
    $this->shoutbox->deleteMessage($shout_id);
    $this->messenger()->addStatus($this->t('Message deleted.'));

    // Cache invalidieren
    $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    \Drupal::service('cache_tags.invalidator')
      ->invalidateTags(['soccerbet_shoutbox:' . $tournament_id]);

    return new RedirectResponse(
      Url::fromRoute('<front>')->toString()
    );
  }
}
