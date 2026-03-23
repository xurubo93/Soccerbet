<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Gemeinsame Basisklasse für alle Soccerbet-Migrate-Source-Plugins.
 *
 * Stellt die Verbindung zur Quell-Datenbank (D6/D7) bereit und
 * normalisiert Datums-Felder von DATETIME → Unix-Timestamp.
 */
abstract class SoccerbetSourceBase extends SqlBase {

  /**
   * Konvertiert einen MySQL-DATETIME-String zu einem Unix-Timestamp.
   * D6 speicherte Zeiten in der Server-Zeitzone (Europe/Vienna).
   * Gibt 0 zurück wenn der Wert NULL oder leer ist.
   */
  protected function datetimeToTimestamp(?string $value): int {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
      return 0;
    }
    try {
      $dt = new \DateTimeImmutable($value, new \DateTimeZone('Europe/Vienna'));
      return $dt->getTimestamp();
    }
    catch (\Exception) {
      return 0;
    }
  }

  /**
   * Konvertiert einen MySQL-DATETIME-String zu ISO-8601 UTC (für game_date).
   * D6 speicherte Zeiten in Europe/Vienna → wird nach UTC konvertiert.
   */
  protected function datetimeToIso(?string $value): string {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
      return '';
    }
    try {
      $dt = new \DateTimeImmutable($value, new \DateTimeZone('Europe/Vienna'));
      return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }
    catch (\Exception) {
      return '';
    }
  }

}
