# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

**Soccer Bet** is a Drupal 11 custom module implementing a football betting game (Tippspiel) in German. It manages tournaments, teams, games, predictions (Tipps), and live leaderboards with API-driven score updates. It was migrated from a Drupal 6 original.

- PHP 8.2+ with `declare(strict_types=1)` in every class
- All UI strings in code are **English**; German translations live in `translations/soccerbet.de.po`
- Drupal root: `/Users/peterwindholz/Sites/Soccerbet`
- Local dev runs via **Lando** — use `lando drush` instead of `vendor/bin/drush`

## Common Commands

All commands run from the Drupal root (`/Users/peterwindholz/Sites/Soccerbet`) via Lando:

```bash
# Clear Drupal caches (most common after code changes)
lando drush cr

# Enable the module
lando drush en soccerbet -y

# Run database updates (after schema changes in soccerbet.install)
lando drush updb -y

# Check current configuration
lando drush cget soccerbet.settings

# Import German translations (PO file is the leading source — never edit via Drupal UI)
lando drush locale:import de /app/web/modules/custom/soccerbet/translations/soccerbet.de.po --type=customized --override=all

# Import/export config
vendor/bin/drush cim -y
vendor/bin/drush cex -y

# Run data migration (D6 → D11, requires migrate_plus + migrate_tools)
vendor/bin/drush migrate:import soccerbet_tipper_groups
vendor/bin/drush migrate:import soccerbet_tippers
vendor/bin/drush migrate:import soccerbet_tournaments
vendor/bin/drush migrate:import soccerbet_teams
vendor/bin/drush migrate:import soccerbet_games
vendor/bin/drush migrate:import soccerbet_tipps
vendor/bin/drush migrate:import soccerbet_tournament_tippers
```

There are no automated tests in this module.

## Architecture

### Service Layer (src/Service/)

Business logic is fully decoupled into services, injected via constructor DI. Key services:

- **ScoringService** — Core scoring logic. Calculates points per tipper using optimized JOINs (3 queries total). Scoring: exact result = 3pts, correct tendency = 1pt, KO round = N pts (tipper count), exclusive best bet = +1 bonus.
- **TournamentManager** — Tournament CRUD and active tournament resolution. The active tournament is stored in `soccerbet.settings` → `default_tournament`.
- **TipperManager** — Manages tippers, groups, teams, games. Handles Drupal user↔tipper mapping with duplicate prevention.
- **ApiImportService** — Polymorphic import via `ApiClientFactory`. Supports OpenLigaDB (free, German) and football-data.org (requires API key). Switch via `soccerbet.settings` → `api_provider`.
- **ScoreUpdateService** — Fetches live scores and recalculates standings.
- **SoccerbetCronSubscriber** — Event listener on Drupal cron. Adaptive polling: live mode (game ±3h) = every cron run; idle mode = every 60min; nighttime (23:00–06:00 UTC) = skip.
- **ShoutboxService** — Per-tournament chat messages.
- **TeamFlagResolver** — Maps team names to ISO country codes via `data/team-flags.yml`.

All services are defined in `soccerbet.services.yml`.

### Controllers & Forms

Controllers render pages and pass data to Twig templates via `hook_theme()`. Forms extend Drupal's `FormBase`. The main user-facing flow is:

1. `StandingsController` → `soccerbet-standings.html.twig` — public leaderboard with rank changes and payment icons
2. `PlaceBetsForm` → `soccerbet-place-bets.html.twig` — bet placement with lockdown enforcement (configurable minutes before kickoff)
3. `LiveController` — live leaderboard + `/liveJson` endpoint polled by `js/soccerbet-live.js`

Admin CRUD follows the pattern: `{Entity}Controller` lists records, separate `{Entity}Form` / `{Entity}DeleteForm` handle create/edit/delete.

### Database

8 custom tables (no Drupal entity system used — raw `\Drupal::database()` queries throughout):

| Table | Purpose |
|---|---|
| `soccerbet_tournament` | Tournament metadata |
| `soccerbet_tournament_groups` | Tournament ↔ tipper group N:M |
| `soccerbet_teams` | Teams per tournament with league stats |
| `soccerbet_games` | Matches with results and kickoff timestamps |
| `soccerbet_tipps` | Per-tipper predictions per game |
| `soccerbet_tippers` | Participants linked to Drupal users |
| `soccerbet_tipper_groups` | Betting consortiums |
| `soccerbet_tournament_tippers` | Participation + payment status |

Schema is defined in `soccerbet.install`. Changes require a `hook_update_N()` function and `drush updb`.

### Routing

27 routes in `soccerbet.routing.yml`. Public routes under `/soccerbet/`, admin routes under `/soccerbet/admin/` (require `administer soccerbet`) and `/admin/config/soccerbet` (SettingsForm).

### Data Migration (D6 → D11)

Migration uses Drupal's Migrate API with custom source/destination plugins in `src/Plugin/migrate/`. The alternate database connection key `migrate` must be configured in `settings.php`. Run migrations in dependency order (see commands above). See `MIGRATION.md` for full setup including encoding fixes and troubleshooting.

### Configuration

Module settings live in `config/install/soccerbet.settings.yml` and are managed through `SettingsForm`. Key settings: `default_tournament`, `points_exact` (3), `points_tendency` (1), `api_provider`, `footballdata_api_key`, `betting_closes_minutes_before`, `score_update_enabled`.

### JavaScript Libraries

Three libraries defined in `soccerbet.libraries.yml`:
- `global` — attached to all `soccerbet.*` routes via `hook_page_attachments()`
- `admin` — admin pages only
- `live` — live leaderboard polling (loaded in footer)
