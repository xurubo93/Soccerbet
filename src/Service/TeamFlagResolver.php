<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Löst Teamnamen auf ISO 3166-1 Alpha-2 Flaggen-Codes auf.
 *
 * Reihenfolge:
 *  1. Exakter Treffer in data/team-flags.yml
 *  2. Case-insensitiver Treffer
 *  3. Fuzzy-Match (similar_text ≥ 80%)
 *  4. Leerer String (kein Treffer)
 */
final class TeamFlagResolver {

  /** @var array<string, string>|null */
  private ?array $mapping = NULL;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Gibt den ISO-Code für einen Teamnamen zurück oder '' wenn keiner gefunden.
   */
  public function resolve(string $team_name): string {
    $map = $this->getMapping();
    $name = trim($team_name);

    // 1. Exakter Treffer
    if (isset($map[$name])) {
      return $map[$name];
    }

    // 2. Case-insensitiv
    $name_lower = mb_strtolower($name);
    foreach ($map as $key => $code) {
      if (mb_strtolower($key) === $name_lower) {
        return $code;
      }
    }

    // 3. Fuzzy-Match: Teile des Namens prüfen
    // z.B. "FC Bayern München" → "Bayern München" → "DE"
    foreach ($map as $key => $code) {
      similar_text($name_lower, mb_strtolower($key), $pct);
      if ($pct >= 80.0) {
        return $code;
      }
    }

    // 4. Teilstring-Match: enthält der Teamname einen bekannten Schlüssel?
    foreach ($map as $key => $code) {
      if (mb_strlen($key) >= 4 && str_contains($name_lower, mb_strtolower($key))) {
        return $code;
      }
    }

    return '';
  }

  /**
   * Gibt das vollständige Mapping zurück (lazy-loaded, gecacht im Prozess).
   *
   * @return array<string, string>
   */
  public function getMapping(): array {
    if ($this->mapping === NULL) {
      $module_path = $this->moduleHandler->getModule('soccerbet')->getPath();
      $file = $module_path . '/data/team-flags.yml';

      if (file_exists($file)) {
        $data = Yaml::parseFile($file);
        $this->mapping = is_array($data) ? $data : [];
      }
      else {
        $this->mapping = [];
      }
    }
    return $this->mapping;
  }

  /**
   * Löscht den internen Cache (z.B. nach manuellem Update der YAML-Datei).
   */
  public function clearCache(): void {
    $this->mapping = NULL;
  }
}
