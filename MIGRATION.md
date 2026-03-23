# Datenmigration: Drupal 6 → Drupal 11

## Voraussetzungen

```bash
composer require drupal/migrate_plus drupal/migrate_tools
vendor/bin/drush en migrate migrate_plus migrate_tools soccerbet -y
```

---

## Schritt 1 – Quell-Datenbank konfigurieren

In `web/sites/default/settings.php` die alte D6-Datenbank als zweite
Verbindung eintragen:

```php
// Quell-Datenbank (Drupal 6 / altes Soccerbet)
$databases['migrate']['default'] = [
  'driver'    => 'mysql',
  'database'  => 'soccerbet_d6',   // ← Name der alten Datenbank
  'username'  => 'db_user',
  'password'  => 'db_pass',
  'host'      => 'localhost',
  'port'      => '3306',
  'prefix'    => '',                // ← Tabellenpräfix falls vorhanden
  'charset'   => 'utf8',
  'collation' => 'utf8_general_ci',
];
```

---

## Schritt 2 – Migrations-Konfiguration importieren

```bash
vendor/bin/drush cim --partial --source=modules/custom/soccerbet/migrations/
vendor/bin/drush cr
```

---

## Schritt 3 – Migrations-Status prüfen

```bash
vendor/bin/drush migrate:status --group=soccerbet
```

Erwartete Ausgabe:
```
 Group: Soccerbet Migration (D6 → D11) (soccerbet)
 ID                               Status   Total  Imported  Unprocessed  Last imported
 soccerbet_tipper_groups          Idle     3      0         3
 soccerbet_tippers                Idle     12     0         12
 soccerbet_tournaments            Idle     2      0         2
 soccerbet_teams                  Idle     32     0         32
 soccerbet_games                  Idle     48     0         48
 soccerbet_tipps                  Idle     576    0         576
 soccerbet_tournament_tippers     Idle     24     0         24
```

---

## Schritt 4 – Migration ausführen (in Reihenfolge)

```bash
# Einzeln (empfohlen beim ersten Mal)
vendor/bin/drush migrate:import soccerbet_tipper_groups
vendor/bin/drush migrate:import soccerbet_tippers
vendor/bin/drush migrate:import soccerbet_tournaments
vendor/bin/drush migrate:import soccerbet_teams
vendor/bin/drush migrate:import soccerbet_games
vendor/bin/drush migrate:import soccerbet_tipps
vendor/bin/drush migrate:import soccerbet_tournament_tippers

# Oder alle auf einmal
vendor/bin/drush migrate:import --group=soccerbet --execute-dependencies
```

---

## Schritt 5 – Ergebnis prüfen

```bash
# Status nach Migration
vendor/bin/drush migrate:status --group=soccerbet

# Rangliste im Browser aufrufen
# → /soccerbet/standings
```

---

## Fehlerbehebung

### "Source database not found"
→ Datenbankname und Zugangsdaten in `settings.php` prüfen.

### "Table soccerbet_tournament_tippers does not exist"
→ Kein Problem! Das Source-Plugin rekonstruiert die Zuordnungen
  automatisch aus den vorhandenen Tipps.

### Migration zurücksetzen und neu starten
```bash
vendor/bin/drush migrate:rollback --group=soccerbet
vendor/bin/drush migrate:import  --group=soccerbet --execute-dependencies
```

### Einzelne fehlerhafte Zeilen überspringen
```bash
vendor/bin/drush migrate:import soccerbet_tipps --continue-on-failure
```

### Detailliertes Logging
```bash
vendor/bin/drush migrate:import soccerbet_games -vvv 2>&1 | tee migration.log
```

---

## Sonderfall: Zeichensatz-Probleme (Latin1 → UTF-8)

Der D6-Code hatte Encoding-Probleme (Latin1 statt UTF-8). Falls Umlaute
falsch dargestellt werden:

```php
// In settings.php bei der migrate-Verbindung:
$databases['migrate']['default']['charset']   = 'latin1';
$databases['migrate']['default']['collation'] = 'latin1_swedish_ci';
```

Und in der D11-Datenbank alle Tabellen auf UTF-8 prüfen:
```sql
ALTER TABLE soccerbet_teams
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Migration-Reihenfolge (Abhängigkeitsgraph)

```
soccerbet_tipper_groups
        │
        ├──► soccerbet_tippers ──────────────────────┐
        │                                            │
        └──► soccerbet_tournaments                   │
                    │                                │
                    ├──► soccerbet_teams             │
                    │          │                     │
                    │          └──► soccerbet_games ─┤
                    │                     │          │
                    │                     └──────────┴──► soccerbet_tipps
                    │                                              │
                    └──────────────────────────────────────────────┤
                                                                   │
                                                    soccerbet_tournament_tippers
```
