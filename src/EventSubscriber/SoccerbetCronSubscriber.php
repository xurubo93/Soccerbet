<?php

declare(strict_types=1);

namespace Drupal\soccerbet\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\soccerbet\Service\ScoreUpdateService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\CronInterface;

/**
 * Reagiert auf den Drupal-Cron und startet das Score-Update.
 *
 * Cron-Strategie für Shared Hosting (Cron alle 5 Min.):
 *
 *  - Außerhalb von Spielzeiten: Update alle 60 Minuten
 *  - Wenn ein Spiel läuft (±3h um Anpfiff): Update bei jedem Cron-Lauf
 *  - Nachts (23:00–06:00): Kein Update
 *
 * Diese adaptive Strategie schont Rate-Limits und Server-Ressourcen.
 */
final class SoccerbetCronSubscriber implements EventSubscriberInterface {

  /** State-Key: Letzter erfolgreicher Update-Zeitpunkt */
  private const STATE_LAST_RUN = 'soccerbet.score_update.last_run';

  /** Minimales Interval außerhalb von Spielzeiten (Sekunden) */
  private const IDLE_INTERVAL = 3600; // 1 Stunde

  public function __construct(
    private readonly ScoreUpdateService $scoreUpdateService,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      // Drupal feuert dieses Event bei jedem Cron-Lauf
      'cron' => ['onCron', 100],
    ];
  }

  /**
   * Cron-Handler: entscheidet ob ein Update sinnvoll ist und führt es durch.
   */
  public function onCron(): void {
    $config = $this->configFactory->get('soccerbet.settings');

    // Feature-Flag: Auto-Update aktiviert?
    if (!$config->get('score_update_enabled')) {
      return;
    }

    $now  = \Drupal::time()->getRequestTime();
    // Nachtruhe nach UTC-Zeit: 23:00–06:00 kein Update
    $hour = (int) gmdate('H', $now);

    // Nachtruhe: 23:00–06:00 kein Update
    if ($hour >= 23 || $hour < 6) {
      return;
    }

    // Aktive Spiele prüfen
    $has_active_games = $this->hasActiveGames($now);

    if (!$has_active_games) {
      // Außerhalb Spielzeiten: Interval einhalten
      $last_run = (int) $this->state->get(self::STATE_LAST_RUN, 0);
      if (($now - $last_run) < self::IDLE_INTERVAL) {
        return;
      }
    }

    // Update starten
    $this->logger()->info('Soccerbet Score-Update via Cron gestartet.');
    $start = microtime(TRUE);

    try {
      $stats = $this->scoreUpdateService->updateAll();
      $duration = round(microtime(TRUE) - $start, 2);

      $this->logger()->info(
        'Score-Update abgeschlossen in @ds. Aktualisiert: @u, Übersprungen: @s, Fehler: @e',
        [
          '@d' => $duration,
          '@u' => $stats['updated'],
          '@s' => $stats['skipped'],
          '@e' => $stats['errors'],
        ]
      );

      $this->state->set(self::STATE_LAST_RUN, $now);
    }
    catch (\Exception $e) {
      $this->logger()->error('Score-Update fehlgeschlagen: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Prüft ob gerade ein Spiel aktiv ist oder in den nächsten 3 Stunden startet.
   */
  private function hasActiveGames(int $now): bool {
    $window_start = gmdate('Y-m-d\TH:i:s', $now - 3 * 3600); // 3h zurück (UTC)
    $window_end   = gmdate('Y-m-d\TH:i:s', $now + 3 * 3600); // 3h voraus (UTC)

    $count = \Drupal::database()
      ->select('soccerbet_games', 'g')
      ->condition('g.game_date', $window_start, '>=')
      ->condition('g.game_date', $window_end,   '<=')
      ->condition('g.published', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    return (int) $count > 0;
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }

}
