# Soccer Bet – Drupal 11

Fußball-Tippspiel für Drupal 11. Migriert und neu entwickelt aus dem ursprünglichen Drupal-6-Modul.

## Punktesystem

| Situation | Punkte |
|---|---|
| Exaktes Ergebnis getippt | **3 Punkte** (konfigurierbar) |
| Richtige Tendenz (Sieg/Unentschieden/Niederlage) | **1 Punkt** (konfigurierbar) |
| Aufsteiger in KO-Runden richtig getippt | **N Punkte** (= Anzahl Teilnehmer) |
| Exklusiv bestes Tipp-Ergebnis eines Spiels | **+1 Bonuspunkt** |

Rangliste sortiert nach: **Total → Richtige Ergebnisse → Tendenzen**

---

## Installation

### Voraussetzungen
- PHP 8.2+
- Drupal 11
- Composer
- MySQL 8.0+ / MariaDB 10.6+

### Shared Hosting (SSH)

```bash
# 1. In Drupal-Verzeichnis wechseln
cd /var/www/html

# 2. Modul in custom-Verzeichnis ablegen
cp -r path/to/soccerbet web/modules/custom/

# 3. Drupal-Cache leeren und Modul installieren
vendor/bin/drush en soccerbet -y
vendor/bin/drush cr

# 4. Datenbankschema installieren (passiert automatisch via drush en)
# 5. Konfiguration prüfen
vendor/bin/drush cget soccerbet.settings
```

---

## Modulstruktur

```
soccerbet/
├── src/
│   ├── Controller/          # Seiten-Controller
│   │   ├── StandingsController.php   ← Rangliste
│   │   ├── TablesController.php      ← Gruppentabellen
│   │   ├── TournamentController.php  ← Admin Turniere
│   │   ├── TeamController.php        ← Admin Teams
│   │   ├── GameController.php        ← Admin Spiele
│   │   └── TipperGroupController.php ← Admin Gruppen
│   ├── Form/                # Drupal FormBase-Klassen
│   │   ├── PlaceBetsForm.php         ← Tipp-Eingabe
│   │   ├── TournamentForm.php        ← Turnier anlegen/bearbeiten
│   │   ├── GameForm.php              ← Spiel anlegen/bearbeiten
│   │   ├── GameScoreForm.php         ← Ergebnis eintragen
│   │   ├── TeamForm.php              ← Team anlegen/bearbeiten
│   │   ├── TipperGroupForm.php       ← Tippergruppe
│   │   ├── TournamentMembersForm.php ← Teilnehmer zuordnen
│   │   ├── PaymentForm.php           ← Zahlungen verwalten
│   │   └── SettingsForm.php          ← Globale Einstellungen
│   └── Service/
│       ├── ScoringService.php        ← KERNLOGIK: Punkte & Rangliste
│       ├── TournamentManager.php     ← Turnier CRUD
│       └── TipperManager.php         ← Tipper CRUD
├── templates/
│   ├── soccerbet-standings.html.twig
│   ├── soccerbet-place-bets.html.twig
│   ├── soccerbet-tipper-detail.html.twig
│   └── soccerbet-tables.html.twig
├── config/
│   ├── install/soccerbet.settings.yml
│   └── schema/soccerbet.schema.yml
├── css/soccerbet.css
├── js/soccerbet.js
├── soccerbet.info.yml
├── soccerbet.module
├── soccerbet.install        ← Datenbankschema (7 Tabellen)
├── soccerbet.routing.yml    ← Alle Routen
├── soccerbet.permissions.yml
├── soccerbet.services.yml   ← Dependency Injection
├── soccerbet.libraries.yml
└── soccerbet.links.menu.yml
```

---

## Datenbank-Tabellen

| Tabelle | Beschreibung |
|---|---|
| `soccerbet_tournament` | Turniere |
| `soccerbet_teams` | Teams mit Tabellenstand |
| `soccerbet_games` | Spiele mit Ergebnis |
| `soccerbet_tipps` | Tipper-Tipps pro Spiel |
| `soccerbet_tippers` | Teilnehmer (→ Drupal User) |
| `soccerbet_tipper_groups` | Wettgemeinschaften |
| `soccerbet_tournament_tippers` | Turnier ↔ Tipper + Zahlungsstatus |

---

## Berechtigungen

| Permission | Beschreibung |
|---|---|
| `access soccerbet content` | Ranglisten & Spielpläne sehen |
| `place soccerbet bets` | Tipps abgeben |
| `administer soccerbet` | Alles verwalten |
| `edit soccerbet scores` | Ergebnisse eintragen |
| `manage soccerbet payments` | Zahlungen bestätigen |

---

## Nächste Phasen

- **Phase 2**: TournamentManager, TipperManager, weitere Services
- **Phase 3**: Admin-Formulare (TournamentForm, GameForm, TeamForm, ...)
- **Phase 4**: PlaceBetsForm, Twig-Templates für Frontend
- **Phase 5**: Migrate-API-Plugins für Datenmigration vom Altsystem
