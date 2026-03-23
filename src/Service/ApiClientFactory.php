<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Factory: gibt den konfigurierten API-Client zurück.
 */
final class ApiClientFactory {

  public const PROVIDER_OPENLIGADB    = 'openligadb';
  public const PROVIDER_FOOTBALLDATA  = 'footballdata';

  public function __construct(
    private readonly OpenLigaDbClient $openligadb,
    private readonly FootballDataClient $footballdata,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Gibt den aktiven API-Client zurück.
   */
  public function getClient(): ApiClientInterface {
    $provider = $this->configFactory
      ->get('soccerbet.settings')
      ->get('api_provider') ?? self::PROVIDER_OPENLIGADB;

    return match($provider) {
      self::PROVIDER_FOOTBALLDATA => $this->footballdata,
      default                     => $this->openligadb,
    };
  }

  public function getActiveProvider(): string {
    return $this->configFactory
      ->get('soccerbet.settings')
      ->get('api_provider') ?? self::PROVIDER_OPENLIGADB;
  }

  /**
   * Gibt alle verfügbaren Provider als Select-Optionen zurück.
   *
   * @return array<string, string>
   */
  public function getProviderOptions(): array {
    return [
      self::PROVIDER_OPENLIGADB   => $this->openligadb->getLabel(),
      self::PROVIDER_FOOTBALLDATA => $this->footballdata->getLabel(),
    ];
  }
}
