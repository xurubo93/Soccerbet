<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basisklasse für alle Soccerbet-Migrate-Destination-Plugins.
 *
 * Schreibt Datensätze direkt per Drupal Database API in die Ziel-Tabelle.
 * Unterstützt Insert (neu) und Update (bereits migriert / erneut ausgeführt).
 */
abstract class SoccerbetDestinationBase extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * Name der Ziel-Tabelle (von Unterklassen zu setzen).
   */
  protected string $table = '';

  /**
   * Primärschlüssel-Feld(er) der Ziel-Tabelle.
   *
   * @var string[]
   */
  protected array $primaryKey = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    protected readonly Connection $db,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   *
   * Migrate-Destination-Plugins erhalten $migration als 5. Parameter.
   * Die Signatur verzichtet auf Typ-Deklarationen bei $plugin_id und
   * $plugin_definition um Kompatibilität mit PluginBase::create() zu wahren.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    // $migration wird vom Migrate-Framework als 5. Argument übergeben,
    // ist aber nicht Teil der PluginBase-Signatur → aus func_get_args() holen.
    $args      = func_get_args();
    $migration = $args[4] ?? NULL;

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids = [];
    foreach ($this->primaryKey as $key) {
      $ids[$key] = ['type' => 'integer'];
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): array|bool {
    $record = $row->getDestination();

    // Zwischenvariablen (Felder mit _-Präfix) herausfiltern –
    // diese werden nur in der Migrate-Pipeline als temporäre Lookup-Werte
    // verwendet und gehören nicht in die Datenbank.
    foreach (array_keys($record) as $key) {
      if (str_starts_with((string) $key, '_')) {
        unset($record[$key]);
      }
    }

    // Leere Strings bei optionalen Integer-Feldern → NULL
    foreach ($record as $key => &$value) {
      if ($value === '' || $value === []) {
        $value = NULL;
      }
    }
    unset($value);

    // Wenn ein Pflichtfeld (primaryKey oder FK aus Lookup) NULL ist →
    // Datensatz überspringen statt DB-Fehler zu provozieren.
    // Das passiert wenn migration_lookup keinen Treffer findet (no_stub: true).
    foreach ($this->primaryKey as $key) {
      if (array_key_exists($key, $record) && $record[$key] === NULL) {
        return FALSE;
      }
    }
    // Auch andere NOT-NULL-FK-Felder prüfen (tournament_id, tipper_id, game_id)
    $required_fk = ['tournament_id', 'tipper_id', 'game_id', 'team_id_1', 'team_id_2'];
    foreach ($required_fk as $fk) {
      if (array_key_exists($fk, $record) && $record[$fk] === NULL) {
        return FALSE;
      }
    }

    // Prüfen ob der Datensatz bereits existiert
    $existing = $this->recordExists($record);

    if ($existing) {
      $this->db->update($this->table)
        ->fields($record)
        ->condition($this->primaryKey[0], $record[$this->primaryKey[0]])
        ->execute();
    }
    else {
      $this->db->insert($this->table)
        ->fields($record)
        ->execute();
    }

    // ID-Array zurückgeben für die Migrate-ID-Map
    $ids = [];
    foreach ($this->primaryKey as $key) {
      $ids[] = $record[$key];
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier): void {
    $q = $this->db->delete($this->table);
    foreach ($destination_identifier as $key => $value) {
      $q->condition($key, $value);
    }
    $q->execute();
  }

  /**
   * Prüft ob ein Datensatz mit dem angegebenen PK bereits existiert.
   */
  private function recordExists(array $record): bool {
    $q = $this->db->select($this->table, 't');
    foreach ($this->primaryKey as $key) {
      if (isset($record[$key])) {
        $q->condition($key, $record[$key]);
      }
    }
    return (bool) $q->countQuery()->execute()->fetchField();
  }

  /**
   * Zählt alle Datensätze in der Ziel-Tabelle.
   */
  public function getHighestId(): int {
    return (int) $this->db->select($this->table, 't')
      ->addExpression('MAX(t.' . $this->primaryKey[0] . ')')
      ->execute()
      ->fetchField();
  }
}
