<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

/**
 * Interface für externe Fußball-API-Clients.
 *
 * Beide Implementierungen (OpenLigaDB und football-data.org) müssen
 * normalisierte Datenstrukturen zurückgeben damit Import und Score-Update
 * API-unabhängig funktionieren.
 */
interface ApiClientInterface {

  /**
   * Gibt alle Spiele einer Liga/Saison zurück.
   *
   * @return array<int, array{
   *   external_id: int,
   *   date_utc: string,
   *   group_name: string,
   *   group_order: int,
   *   team1_id: int,
   *   team1_name: string,
   *   team1_flag: string,
   *   team2_id: int,
   *   team2_name: string,
   *   team2_flag: string,
   *   score1: int|null,
   *   score2: int|null,
   *   is_finished: bool,
   *   stadium: string,
   * }>
   */
  public function getMatches(string $competition, string $season, string $stage = ''): array;

  /**
   * Gibt die aktuelle Tabelle zurück.
   *
   * @return array<int, array{
   *   team_name: string,
   *   played: int,
   *   won: int,
   *   drawn: int,
   *   lost: int,
   *   goals_for: int,
   *   goals_against: int,
   *   points: int,
   * }>
   */
  public function getTable(string $competition, string $season): array;

  /**
   * Gibt TRUE zurück wenn sich Daten seit dem letzten Abruf geändert haben.
   */
  public function hasChangedSince(string $competition, string $season): bool;

  /**
   * Markiert den aktuellen Stand als gesehen (für hasChangedSince).
   */
  public function markAsSeen(string $competition, string $season): void;

  /**
   * Gibt den Anzeigenamen der API zurück (z.B. für Settings).
   */
  public function getLabel(): string;
}
