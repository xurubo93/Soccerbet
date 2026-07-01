<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HTTP-Client für die football-data.org REST-API v4.
 *
 * Basis-URL: https://api.football-data.org/v4
 *
 * Relevante Endpunkte:
 *  GET /competitions/{code}/matches              → alle Spiele
 *  GET /competitions/{code}/standings            → Tabelle
 *
 * API-Key wird in den Einstellungen konfiguriert.
 * Free-Tier: 10 Req/min.
 *
 * Competition-Codes: z.B. CL, PL, BL1, SA, PD, FL1, EC, WC
 * Saison: z.B. 2024 (entspricht Saison 2024/25)
 */
final class FootballDataClient implements ApiClientInterface {

  private const BASE_URL    = 'https://api.football-data.org/v4';
  private const CACHE_SEEN  = 'soccerbet_fdorg_seen_';

  /**
   * Mapping football-data.org stage → soccerbet phase
   */
  private const STAGE_MAP = [
    'GROUP_STAGE'          => 'group',
    'ROUND_OF_16'          => 'round_of_16',
    'QUARTER_FINALS'       => 'quarter',
    'SEMI_FINALS'          => 'semi',
    'THIRD_PLACE'          => 'third_place',
    'FINAL'                => 'final',
    // Bundesliga hat keine Stages → alles 'group'
  ];

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** {@inheritdoc} */
  public function getLabel(): string {
    return 'football-data.org';
  }

  /** {@inheritdoc} */
  public function getMatches(string $competition, string $season, string $stage = ''): array {
    $query = ['season' => $season];
    if ($stage !== '') {
      $query['stage'] = $stage;
    }
    $data = $this->get("/competitions/{$competition}/matches", $query);
    if (empty($data['matches'])) {
      return [];
    }

    $result = [];
    foreach ($data['matches'] as $m) {
      $score1 = NULL;
      $score2 = NULL;
      // Für das Tippspiel gilt der 120-Minuten-Stand: bei Elfmeterschießen
      // liefert `fullTime` die Elfer-Tore mit — dort müssen wir regularTime
      // und extraTime addieren, um den Stand nach der Verlängerung zu
      // erhalten. Bei EXTRA_TIME entspricht fullTime schon dem 120-Min-Stand,
      // bei REGULAR dem 90-Min-Stand.
      if (in_array($m['status'], ['FINISHED', 'IN_PLAY', 'PAUSED'], TRUE)) {
        $duration = $m['score']['duration'] ?? 'REGULAR';
        if ($duration === 'PENALTY_SHOOTOUT') {
          $reg1 = $m['score']['regularTime']['home'] ?? NULL;
          $reg2 = $m['score']['regularTime']['away'] ?? NULL;
          $et1  = $m['score']['extraTime']['home']   ?? 0;
          $et2  = $m['score']['extraTime']['away']   ?? 0;
          if ($reg1 !== NULL && $reg2 !== NULL) {
            $score1 = (int) $reg1 + (int) $et1;
            $score2 = (int) $reg2 + (int) $et2;
          }
        }
        else {
          $score1 = $m['score']['fullTime']['home'] ?? NULL;
          $score2 = $m['score']['fullTime']['away'] ?? NULL;
        }
      }

      // Aufsteiger aus der API übernehmen — wichtig bei Elfmeterschießen,
      // wo der 120-Min-Stand unentschieden ist, der Sieger aber feststeht.
      $api_winner_side = match ($m['score']['winner'] ?? NULL) {
        'HOME_TEAM' => 'team1',
        'AWAY_TEAM' => 'team2',
        default     => NULL,
      };

      // Elfmeter-Tore nur bei entsprechendem Ausgang.
      $penalty_score1 = NULL;
      $penalty_score2 = NULL;
      if (($m['score']['duration'] ?? '') === 'PENALTY_SHOOTOUT') {
        $penalty_score1 = $m['score']['penalties']['home'] ?? NULL;
        $penalty_score2 = $m['score']['penalties']['away'] ?? NULL;
        $penalty_score1 = $penalty_score1 !== NULL ? (int) $penalty_score1 : NULL;
        $penalty_score2 = $penalty_score2 !== NULL ? (int) $penalty_score2 : NULL;
      }

      // Datum ist UTC in ISO 8601
      $date_utc = '';
      if (!empty($m['utcDate'])) {
        try {
          $dt = new \DateTimeImmutable($m['utcDate'], new \DateTimeZone('UTC'));
          $date_utc = $dt->format('Y-m-d\TH:i:s');
        }
        catch (\Exception) {}
      }

      // Flag code: football-data.org provides area.code as FIFA 3-letter code.
      // Map to ISO 3166-1 Alpha-3 (stored directly, matches SVG filename).
      $team1_flag = $this->fifaToAlpha3($m['homeTeam']['area']['code'] ?? '');
      $team2_flag = $this->fifaToAlpha3($m['awayTeam']['area']['code'] ?? '');

      // Matchday / Stage / Gruppe
      // football-data.org liefert:
      //   stage: "GROUP_STAGE", "ROUND_OF_16", "QUARTER_FINALS" etc.
      //   group: "GROUP_A", "GROUP_B", ... oder null (bei KO-Runden)
      $stage = $m['stage'] ?? 'GROUP_STAGE';

      // group_name: für Gruppenphase "GROUP_A" etc., für KO den Stage-Wert
      $group_name = '';
      if (!empty($m['group']) && $m['group'] !== null) {
        // Direktes Gruppenfeld: "GROUP_A", "GROUP_B", ...
        $group_name = (string) $m['group'];
      }
      elseif ($stage === 'GROUP_STAGE') {
        // Fallback: Matchday-Nummer als Gruppenidentifier
        $group_name = 'GROUP_STAGE';
      }
      else {
        $group_name = $stage;
      }

      $result[] = [
        'external_id' => (int) $m['id'],
        'date_utc'    => $date_utc,
        'group_name'  => (string) $group_name,
        'group_order' => (int) ($m['matchday'] ?? 0),
        'team1_id'    => (int) $m['homeTeam']['id'],
        'team1_name'  => (string) ($m['homeTeam']['name'] ?? ''),
        'team1_flag'  => $team1_flag,
        'team2_id'    => (int) $m['awayTeam']['id'],
        'team2_name'  => (string) ($m['awayTeam']['name'] ?? ''),
        'team2_flag'  => $team2_flag,
        'score1'         => $score1 !== NULL ? (int) $score1 : NULL,
        'score2'         => $score2 !== NULL ? (int) $score2 : NULL,
        'penalty_score1' => $penalty_score1,
        'penalty_score2' => $penalty_score2,
        'is_finished'    => $m['status'] === 'FINISHED',
        'winner_side'    => $api_winner_side,
        'stadium'        => (string) ($m['venue'] ?? ''),
        'stage'          => $stage,
      ];
    }
    return $result;
  }

  /** {@inheritdoc} */
  public function getTable(string $competition, string $season): array {
    $data = $this->get("/competitions/{$competition}/standings", [
      'season' => $season,
    ]);
    if (empty($data['standings'])) {
      return [];
    }

    $result = [];
    // Ersten Standings-Block nehmen (TOTAL, nicht HOME/AWAY)
    foreach ($data['standings'] as $standing) {
      if (($standing['type'] ?? '') !== 'TOTAL') {
        continue;
      }
      foreach ($standing['table'] ?? [] as $row) {
        $result[] = [
          'team_name'     => (string) ($row['team']['name'] ?? ''),
          'played'        => (int) ($row['playedGames'] ?? 0),
          'won'           => (int) ($row['won']         ?? 0),
          'drawn'         => (int) ($row['draw']        ?? 0),
          'lost'          => (int) ($row['lost']        ?? 0),
          'goals_for'     => (int) ($row['goalsScored'] ?? 0),
          'goals_against' => (int) ($row['goalsConceded'] ?? 0),
          'points'        => (int) ($row['points']      ?? 0),
        ];
      }
    }
    return $result;
  }

  /** {@inheritdoc} */
  public function hasChangedSince(string $competition, string $season): bool {
    $key    = self::CACHE_SEEN . $competition . '_' . $season;
    $cached = $this->cache->get($key);
    // Kein smarter Change-Endpoint bei football-data.org → nach 60s neu laden
    // (kombiniert mit livescores_interval-Throttle in ScoreUpdateService).
    if ($cached && (time() - $cached->data) < 60) {
      return FALSE;
    }
    return TRUE;
  }

  /** {@inheritdoc} */
  public function markAsSeen(string $competition, string $season): void {
    $key = self::CACHE_SEEN . $competition . '_' . $season;
    $this->cache->set($key, time(), time() + 86400);
  }

  /**
   * Maps football-data.org FIFA codes to ISO 3166-1 Alpha-3.
   * Most FIFA codes already equal Alpha-3; only exceptions need mapping.
   */
  private function fifaToAlpha3(string $fifa): string {
    if (empty($fifa)) {
      return '';
    }
    $exceptions = [
      // Europe
      'GER' => 'DEU',  // Germany
      'SUI' => 'CHE',  // Switzerland
      'POR' => 'PRT',  // Portugal
      'NED' => 'NLD',  // Netherlands
      'DEN' => 'DNK',  // Denmark
      'CRO' => 'HRV',  // Croatia
      'BUL' => 'BGR',  // Bulgaria
      'GRE' => 'GRC',  // Greece
      // Americas
      'URU' => 'URY',  // Uruguay
      'CHI' => 'CHL',  // Chile
      'PAR' => 'PRY',  // Paraguay
      'HON' => 'HND',  // Honduras
      'TRI' => 'TTO',  // Trinidad and Tobago
      'HAI' => 'HTI',  // Haiti
      'SKN' => 'KNA',  // Saint Kitts and Nevis
      'VIN' => 'VCT',  // Saint Vincent and the Grenadines
      'LCA' => 'LCA',  // Saint Lucia (same)
      // Asia/Oceania
      'PHI' => 'PHL',  // Philippines
      'TAH' => 'PYF',  // Tahiti (French Polynesia)
      // Africa
      'RSA' => 'ZAF',  // South Africa
      'ZAM' => 'ZMB',  // Zambia
      'ZIM' => 'ZWE',  // Zimbabwe
      'TAN' => 'TZA',  // Tanzania
      'GAM' => 'GMB',  // Gambia
      'GUI' => 'GIN',  // Guinea
      'EQG' => 'GNQ',  // Equatorial Guinea
      'CGO' => 'COG',  // Congo
      'LES' => 'LSO',  // Lesotho
      'MAD' => 'MDG',  // Madagascar
      'SWZ' => 'SWZ',  // Eswatini (same)
      // UK sub-national (no ISO-3, use football codes)
      'ENG' => 'ENG',
      'SCO' => 'SCO',
      'WAL' => 'WAL',
      'NIR' => 'NIR',
    ];
    $upper = strtoupper($fifa);
    return $exceptions[$upper] ?? $upper;
  }

  /**
   * HTTP-GET mit API-Key-Header.
   */
  private function get(string $path, array $query = []): ?array {
    $api_key = $this->configFactory->get('soccerbet.settings')->get('footballdata_api_key') ?? '';
    if (empty($api_key)) {
      $this->logger()->error('football-data.org API-Key fehlt. Bitte in den Einstellungen konfigurieren.');
      return NULL;
    }

    $url = self::BASE_URL . $path;
    if (!empty($query)) {
      $url .= '?' . http_build_query($query);
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout'         => 15,
        'connect_timeout' => 5,
        'headers'         => [
          'X-Auth-Token' => $api_key,
          'Accept'       => 'application/json',
        ],
      ]);

      if ($response->getStatusCode() === 429) {
        $this->logger()->warning('football-data.org Rate-Limit erreicht.');
        return NULL;
      }
      if ($response->getStatusCode() !== 200) {
        $this->logger()->warning('football-data.org HTTP @s für @u', [
          '@s' => $response->getStatusCode(), '@u' => $url,
        ]);
        return NULL;
      }

      return Json::decode((string) $response->getBody()) ?: NULL;
    }
    catch (GuzzleException $e) {
      $this->logger()->error('football-data.org Verbindungsfehler: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  private function logger(): \Psr\Log\LoggerInterface {
    return $this->loggerFactory->get('soccerbet');
  }
}
