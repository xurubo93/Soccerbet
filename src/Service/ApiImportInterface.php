<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

/**
 * Interface für API-Import-Services.
 */
interface ApiImportInterface {

  /**
   * Importiert Teams und Spiele für ein Turnier.
   *
   * @param int    $tournament_id  Ziel-Turnier in der DB
   * @param string $league         Liga-Kürzel (API-spezifisch)
   * @param string $season         Saison (API-spezifisch)
   *
   * @return array{
   *   teams_created: int,
   *   teams_skipped: int,
   *   teams_no_flag: string[],
   *   games_created: int,
   *   games_skipped: int,
   *   errors: string[],
   * }
   */
  public function importAll(int $tournament_id, string $league, string $season, bool $group_only = TRUE): array;

  /**
   * Gibt den Anzeigenamen der API zurück (für das Formular).
   */
  public function getApiName(): string;

  /**
   * Gibt einen Hilfe-Text für das Liga-Feld zurück.
   */
  public function getLeagueHelp(): string;

  /**
   * Gibt einen Hilfe-Text für das Saison-Feld zurück.
   */
  public function getSeasonHelp(): string;
}
