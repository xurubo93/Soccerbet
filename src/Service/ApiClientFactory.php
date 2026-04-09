<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

/**
 * Factory: gibt den konfigurierten API-Client zurück.
 */
final class ApiClientFactory {

  public const PROVIDER_FOOTBALLDATA = 'footballdata';

  public function __construct(
    private readonly FootballDataClient $footballdata,
  ) {}

  /**
   * Gibt den aktiven API-Client zurück.
   */
  public function getClient(): ApiClientInterface {
    return $this->footballdata;
  }

  public function getActiveProvider(): string {
    return self::PROVIDER_FOOTBALLDATA;
  }

  /**
   * Gibt alle verfügbaren Provider als Select-Optionen zurück.
   *
   * @return array<string, string>
   */
  public function getProviderOptions(): array {
    return [
      self::PROVIDER_FOOTBALLDATA => $this->footballdata->getLabel(),
    ];
  }
}
