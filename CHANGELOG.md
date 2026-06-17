# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.10] - 2026-06-17

### Fixed

- Live standings sort order now matches the regular standings:
  total points → correct results → correct tendencies → name.
  Previously the live view used live_points as second-level
  tiebreaker, which produced a ranking that diverged from the
  standings page whenever total points were equal.

## [1.1.9] - 2026-06-17

### Changed

- Standings step view (round-by-round browse) shows the step's match
  as a scoreboard above the table, styled like the live scoreboard
  (flags + final score).
- The "Correct results" and "Correct tendencies" columns are replaced
  in step mode by a single "Bet" column showing each tipper's bet for
  the step's match plus the total points earned (incl. bonus and KO
  multiplier), with the same colour coding as the live view
  (exact / tendency / wrong).

## [1.1.8] - 2026-06-14

### Added

- Standings heading now shows the number of played matches in
  parentheses, e.g. "WM 2026 (8 matches)".
- Place-bets form: submit button starts disabled and only activates
  when a value changes; active state shows a red "!" hint.
- Place-bets form: jump link to next match now uses smooth scroll
  and centers the target vertically in the viewport.

### Changed

- Place-bets jump link points to the next *untipped* match instead of
  the next open match. Most tippers had already filled the next open
  match before, which made the link confusing.
- Save confirmation on the place-bets form only counts bets that were
  actually changed or newly created. `TipperManager::saveTipp()` now
  returns `bool`.
- Standings round navigation reworked for mobile: round indicator
  ("Round X of Y") moves to its own line above the buttons; the
  buttons themselves are shortened to "Previous" / "Next" and share
  the full width 50/50.
- Standings and live-standings headings drop the redundant
  "Standings" / "Live standings" prefix — the page already has an
  `h1`. Only the tournament name (and match count / live dot) remain.

### Fixed

- Admin overview lists (Games, Teams, Tournaments, Tipper groups,
  admin landing page) had no `#cache.max-age`, so updates landed in
  the DB but the rendered list kept showing the stale state.

## [1.1.7] - 2026-06-13

### Fixed

- Score update never wrote the 0:0 kickoff state. The skip-check in
  `ScoreUpdateService::updateTournament()` cast NULL to 0, so a fresh
  match (DB NULL/NULL) compared equal to the API value 0:0 and was
  treated as unchanged. The score only landed on the first actual
  goal. NULL is now treated as "never set" and triggers a write.

## [1.1.6] - 2026-06-12

### Fixed

- Tournament winner bet: saving the bet form when the winner pick is
  unchanged no longer re-writes the record. Previously every save
  refreshed `phase_index` to the current phase and silently demoted
  the bet to the next-lower point tier even though the tipper had
  only edited match bets.

## [1.1.5] - 2026-06-11

### Fixed

- Bets overview cached the render output indefinitely (no
  `#cache.max-age`). Live score updates landed in the DB but the page
  kept serving stale data, so points were computed from the previous
  result. Set `max-age` to 0 so the view always reflects the current
  scores.

## [1.1.4] - 2026-06-11

### Changed

- The manual "Refresh" button on the live page is now restricted to
  users with the `edit soccerbet scores` permission. Regular users
  still get the automatic refresh, just no manual trigger.

## [1.1.3] - 2026-06-11

### Fixed

- Live page kept matches in the live view too short. The window from
  kickoff is now 180 min (was 120 min), matching the update throttle
  window. Long matches with extra time / extra periods stay marked as
  live until they actually end.

## [1.1.2] - 2026-06-11

### Fixed

- Live scores: the `FootballDataClient` now reads scores not only for
  `FINISHED` matches but also for `IN_PLAY` and `PAUSED` — live results
  actually land in the database now.
- `ScoreUpdateService` no longer skips non-finished matches in the
  update loop. `winner_team_id` is only set once a match is finished
  (relevant for knockout rounds).
- API change-cache shortened from 300 s to 60 s so live goals are not
  delayed by the cache window.
- Fixed an undefined-variable warning that triggered when a match was
  matched directly via api_id.

## [1.1.1] - 2026-06-11

### Changed

- Bets overview (`/soccerbet/tipps`) now only lists matches that have
  already kicked off. Upcoming matches are hidden until kickoff.

## [1.1.0] - 2026-06-10

### Added

- New **Live update interval** setting on the configuration page (visible
  when live scores are enabled). Configurable between 60 and 600 seconds,
  default 120. Throttles automatic score updates from both the live page
  AJAX endpoint and the Drupal cron path.

### Changed

- Automatic score updates now run **only while a match is active**
  (kickoff to kickoff + 180 min). Outside of active matches no API
  calls are made.
- Cron-based score update (`score_update_enabled`) now skips when live
  scores are enabled — the live page handles the refresh, the cron path
  acts as a fallback for sites without live scores.
- Throttle, active-window check and state tracking moved into a single
  `ScoreUpdateService::tryAutomaticUpdate()` shared by both paths.
  Manual updates from the admin form remain unthrottled.

### Upgrade

No schema changes. After updating: clear caches (`drush cr`) and import
translations (`drush locale:import de …`). The new setting defaults to
120 seconds on first access.

## [1.0.1] - 2026-06-10

### Fixed

- **LiveController:** Resolved PHP notice "Only variables should be passed by
  reference" in the `/liveJson` endpoint. `Renderer::renderRoot()` expects the
  render array by reference; the render arrays are now assigned to local
  variables first.

### Upgrade

Pure bugfix release. No schema changes, no `drush updb` required.
Run `lando drush cr` after updating.

## [1.0.0] - 2026-04-11

First stable release after the beta cycle.
