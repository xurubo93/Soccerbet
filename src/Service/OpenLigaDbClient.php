<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HTTP-Client für die OpenLigaDB REST-API.
 *
 * Basis-URL: https://api.openligadb.de
 *
 * Relevante Endpunkte:
 *  GET /getmatchdata/{league}/{season}          → alle Spiele einer Saison
 *  GET /getmatchdata/{league}/{season}/{group}  → Spiele eines Spieltags
 *  GET /getlastchangedate/{league}/{season}     → letztes Änderungsdatum
 *  GET /getbltable/{league}/{season}            → Tabelle (Punkte, Tore …)
 *  GET /getavailableleagues                     → alle verfügbaren Ligen
 *
 * Smarte Poll-Strategie:
 *  Vor jedem vollständigen Abruf wird /getlastchangedate abgefragt.
 *  Nur wenn sich etwas geändert hat, werden die Spieldaten neu geholt.
 *  Das schont die Rate-Limits (1000 Req/h per IP).
 */
final class OpenLigaDbClient implements ApiClientInterface {

  private const BASE_URL = 'https://api.openligadb.de';

  /** Cache-Prefix für Last-Change-Timestamps */
  private const CACHE_LAST_CHANGE = 'soccerbet_oldb_lastchange_';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  // ================================================================== //
  // ApiClientInterface                                                   //
  // ================================================================== //

  public function getLabel(): string {
    return 'OpenLigaDB';
  }

  /**
   * Normalisierte Matches – konvertiert OpenLigaDB-Format in das
   * gemeinsame Format des ApiClientInterface.
   */
  public function getMatches(string $competition, string $season, string $stage = ''): array {
    $raw = $this->getMatchData($competition, $season);
    $result = [];
    foreach ($raw as $m) {
      $score1 = NULL;
      $score2 = NULL;
      if ($m['matchIsFinished'] ?? FALSE) {
        foreach ($m['matchResults'] ?? [] as $r) {
          if ((int) ($r['resultTypeID'] ?? 0) === 2) {
            $score1 = (int) $r['pointsTeam1'];
            $score2 = (int) $r['pointsTeam2'];
          }
        }
        if ($score1 === NULL && !empty($m['matchResults'])) {
          $last = end($m['matchResults']);
          $score1 = (int) $last['pointsTeam1'];
          $score2 = (int) $last['pointsTeam2'];
        }
      }

      $date_utc = '';
      try {
        $dt = new \DateTimeImmutable($m['matchDateTime'] ?? '', new \DateTimeZone('Europe/Berlin'));
        $date_utc = $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
      }
      catch (\Exception) {}

      $result[] = [
        'external_id' => (int) ($m['matchId'] ?? 0),
        'date_utc'    => $date_utc,
        'group_name'  => (string) ($m['group']['groupName'] ?? ''),
        'group_order' => (int) ($m['group']['groupOrderID'] ?? 0),
        'team1_id'    => (int) ($m['team1']['teamId'] ?? 0),
        'team1_name'  => (string) ($m['team1']['teamName'] ?? ''),
        'team1_flag'  => '',
        'team2_id'    => (int) ($m['team2']['teamId'] ?? 0),
        'team2_name'  => (string) ($m['team2']['teamName'] ?? ''),
        'team2_flag'  => '',
        'score1'      => $score1,
        'score2'      => $score2,
        'is_finished' => (bool) ($m['matchIsFinished'] ?? FALSE),
        'stadium'     => (string) ($m['location']['locationCity'] ?? ''),
        'stage'       => 'GROUP_STAGE',
      ];
    }
    return $result;
  }

  /**
   * Normalisierte Tabelle.
   */
  public function getTable(string $competition, string $season): array {
    $raw = $this->fetchRawOldbTable($competition, $season);
    $result = [];
    foreach ($raw as $row) {
      $result[] = [
        'team_name'     => (string) ($row['teamName'] ?? ($row['shortName'] ?? '')),
        'played'        => (int) ($row['matches']       ?? 0),
        'won'           => (int) ($row['won']           ?? 0),
        'drawn'         => (int) ($row['draw']          ?? 0),
        'lost'          => (int) ($row['lost']          ?? 0),
        'goals_for'     => (int) ($row['goals']         ?? 0),
        'goals_against' => (int) ($row['opponentGoals'] ?? 0),
        'points'        => (int) ($row['points']        ?? 0),
      ];
    }
    return $result;
  }

  // ================================================================== //
  // OpenLigaDB-spezifische Methoden (bleiben für Rückwärtskompatibilität)//
  // ================================================================== //

  /**
   * Gibt alle Spiele einer Liga-Saison zurück.
   *
   * @return array<int, array{
   *   matchId: int,
   *   matchDateTime: string,
   *   group: array{groupOrderID: int, groupName: string},
   *   team1: array{teamId: int, teamName: string},
   *   team2: array{teamId: int, teamName: string},
   *   matchIsFinished: bool,
   *   matchResults: array<int, array{resultTypeID: int, pointsTeam1: int, pointsTeam2: int}>,
   * }>
   */
  public function getMatchData(string $league, string $season): array {
    return $this->get("/getmatchdata/{$league}/{$season}") ?? [];
  }

  /**
   * Gibt Spiele eines einzelnen Spieltags zurück (schneller als alle).
   *
   * @return array<int, mixed>
   */
  public function getMatchDataByGroup(string $league, string $season, int $group): array {
    return $this->get("/getmatchdata/{$league}/{$season}/{$group}") ?? [];
  }

  /**
   * Gibt das letzte Änderungsdatum einer Liga-Saison zurück.
   * Gibt NULL zurück wenn die API nicht erreichbar ist.
   */
  public function getLastChangeDate(string $league, string $season): ?\DateTimeImmutable {
    $raw = $this->getRaw("/getlastchangedate/{$league}/{$season}");
    if ($raw === NULL) {
      return NULL;
    }
    // API liefert einen ISO-String z.B. "2024-06-15T20:45:00" in Europe/Berlin
    $cleaned = trim($raw, '"');
    try {
      return new \DateTimeImmutable($cleaned, new \DateTimeZone('Europe/Berlin'));
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Gibt das letzte Änderungsdatum eines Spieltags zurück.
   */
  public function getLastChangeDateByGroup(string $league, string $season, int $group): ?\DateTimeImmutable {
    $raw = $this->getRaw("/getlastchangedate/{$league}/{$season}/{$group}");
    if ($raw === NULL) {
      return NULL;
    }
    // API liefert einen ISO-String in Europe/Berlin
    $cleaned = trim($raw, '"');
    try {
      return new \DateTimeImmutable($cleaned, new \DateTimeZone('Europe/Berlin'));
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Gibt die aktuelle Tabelle einer Liga-Saison zurück.
   *
   * @return array<int, array{
   *   teamInfoId: int,
   *   shortName: string,
   *   teamName: string,
   *   points: int,
   *   won: int,
   *   lost: int,
   *   draw: int,
   *   goals: int,
   *   opponentGoals: int,
   *   goalDiff: int,
   *   matches: int,
   * }>
   */
  public function fetchRawOldbTable(string $league, string $season): array {
    return $this->get("/getbltable/{$league}/{$season}") ?? [];
  }

  /**
   * Gibt alle verfügbaren Ligen zurück (für das Konfigurations-Formular).
   *
   * @return array<int, array{leagueId: int, leagueName: string, leagueShortcut: string, leagueSeason: string}>
   */
  public function getAvailableLeagues(): array {
    return $this->get('/getavailableleagues') ?? [];
  }

  /**
   * Prüft ob sich die Daten einer Liga seit dem letzten bekannten Stand geändert haben.
   * Nutzt den Drupal-Cache um überflüssige Prüf-Requests zu vermeiden.
   */
  public function hasChangedSince(string $league, string $season, string $cache_key_suffix = ''): bool {
    $cache_key = self::CACHE_LAST_CHANGE . $league . '_' . $season . $cache_key_suffix;

    $last_change = $this->getLastChangeDate($league, $season);
    if ($last_change === NULL) {
      // API nicht erreichbar → sicherheitshalber als "geändert" werten
      return TRUE;
    }

    $cached = $this->cache->get($cache_key);
    if ($cached && $cached->data >= $last_change->getTimestamp()) {
      $this->logger()->info(
        'OpenLigaDB [@league/@season]: Keine Änderungen seit @date, Update übersprungen.',
        ['@league' => $league, '@season' => $season, '@date' => $last_change->format('d.m.Y H:i')]
      );
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Speichert den aktuellen Last-Change-Timestamp im Cache.
   * Aufruf nach erfolgreichem Update.
   */
  public function markAsSeen(string $league, string $season, string $cache_key_suffix = ''): void {
    $cache_key = self::CACHE_LAST_CHANGE . $league . '_' . $season . $cache_key_suffix;
    $this->cache->set($cache_key, time(), time() + 86400 * 7); // 7 Tage
  }

  // ================================================================== //
  // Private Hilfsmethoden                                                //
  // ================================================================== //

  /**
   * Führt einen GET-Request aus und gibt das dekodierte JSON zurück.
   */
  private function get(string $path): mixed {
    $raw = $this->getRaw($path);
    if ($raw === NULL) {
      return NULL;
    }
    try {
      return Json::decode($raw);
    }
    catch (\Exception $e) {
      $this->logger()->error('OpenLigaDB JSON-Fehler für @path: @msg', [
        '@path' => $path,
        '@msg'  => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Führt einen GET-Request aus und gibt den rohen Response-Body zurück.
   */
  private function getRaw(string $path): ?string {
    $url = self::BASE_URL . $path;
    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout'         => 10,
        'connect_timeout' => 5,
        'headers'         => [
          'Accept'     => 'application/json',
          'User-Agent' => 'Soccerbet-Drupal11/2.0 (contact@example.com)',
        ],
      ]);

      if ($response->getStatusCode() !== 200) {
        $this->logger()->warning('OpenLigaDB HTTP @status für @url', [
          '@status' => $response->getStatusCode(),
          '@url'    => $url,
        ]);
        return NULL;
      }

      return (string) $response->getBody();
    }
    catch (GuzzleException $e) {
      $this->logger()->error('OpenLigaDB Verbindungsfehler (@url): @msg', [
        '@url' => $url,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }

}
