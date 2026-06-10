<?php

declare(strict_types=1);

namespace Drupal\soccerbet\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\soccerbet\Service\ScoreUpdateService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reagiert auf den Drupal-Cron und startet das Score-Update als Fallback,
 * wenn Live-Scores deaktiviert sind (sonst übernimmt LiveController den Refresh).
 * Throttle + Aktiv-Fenster-Check liegen zentral in ScoreUpdateService.
 */
final class SoccerbetCronSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ScoreUpdateService $scoreUpdateService,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      'cron' => ['onCron', 100],
    ];
  }

  public function onCron(): void {
    $config = $this->configFactory->get('soccerbet.settings');

    if (!$config->get('score_update_enabled')) {
      return;
    }
    // Live-Page übernimmt den Refresh, wenn aktiviert.
    if ($config->get('livescores_enabled')) {
      return;
    }
    // Nachtruhe UTC 23:00–06:00.
    $hour = (int) gmdate('H', \Drupal::time()->getRequestTime());
    if ($hour >= 23 || $hour < 6) {
      return;
    }

    try {
      $this->scoreUpdateService->tryAutomaticUpdate();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('soccerbet')
        ->error('Score-Update fehlgeschlagen: @msg', ['@msg' => $e->getMessage()]);
    }
  }

}
