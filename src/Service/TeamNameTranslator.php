<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Übersetzt englische Teamnamen aus der API ins Deutsche (oder eine andere Sprache).
 *
 * Liest die PO-Datei unter translations/{langcode}.po direkt aus
 * und cached das Mapping im Prozessspeicher.
 */
final class TeamNameTranslator {

  /** @var array<string, string>|null  msgid → msgstr */
  private ?array $map = NULL;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Gibt den übersetzten Teamnamen zurück, oder den Original-Namen wenn keine
   * Übersetzung gefunden wurde.
   */
  public function translate(string $name): string {
    $map = $this->getMap();
    return $map[$name] ?? $name;
  }

  /**
   * Gibt das vollständige Mapping zurück (lazy-loaded).
   *
   * @return array<string, string>
   */
  public function getMap(): array {
    if ($this->map !== NULL) {
      return $this->map;
    }

    $langcode   = $this->languageManager->getCurrentLanguage()->getId();
    $module_path = $this->moduleHandler->getModule('soccerbet')->getPath();
    $po_file    = $module_path . '/translations/' . $langcode . '.po';

    // Fallback auf 'de' wenn aktuelle Sprache keine PO hat
    if (!file_exists($po_file) && $langcode !== 'de') {
      $po_file = $module_path . '/translations/de.po';
    }

    $this->map = file_exists($po_file) ? $this->parsePo($po_file) : [];
    return $this->map;
  }

  /**
   * Parst eine PO-Datei und gibt ein msgid→msgstr Array zurück.
   *
   * @return array<string, string>
   */
  private function parsePo(string $file): array {
    $map     = [];
    $content = file_get_contents($file);
    if ($content === FALSE) {
      return $map;
    }

    // Einfacher Regex-Parser für msgid/msgstr Paare
    preg_match_all(
      '/^msgid\s+"((?:[^"\\\\]|\\\\.)*)"\s*\nmsgstr\s+"((?:[^"\\\\]|\\\\.)*)"/m',
      $content,
      $matches,
      PREG_SET_ORDER
    );

    foreach ($matches as $match) {
      $msgid  = stripslashes($match[1]);
      $msgstr = stripslashes($match[2]);
      if ($msgid !== '' && $msgstr !== '') {
        $map[$msgid] = $msgstr;
      }
    }

    return $map;
  }

  /**
   * Cache leeren (z.B. nach manuellem Update der PO-Datei).
   */
  public function clearCache(): void {
    $this->map = NULL;
  }
}
