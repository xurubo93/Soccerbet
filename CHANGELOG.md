# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.15] - 2026-07-20

### Added

- The earned tournament winner bet points are now **shown** where they were
  previously only calculated: the bettor detail table (extra "Tournament
  winner" row, now also included in the grand total), the "All Bets" overview
  (extra row with each bettor's pick and points) and the last step of "browse
  rounds" (winner pick and points beneath the match bet). Only visible from
  the final's kickoff — the pick stays secret before that.
- "All Bets" and the bettor detail table now list the **latest match first**
  so the current round is on top without scrolling.

### Fixed

- **Tournament winner bet scoring after the semifinal:** a bet placed (or
  changed) once the semifinal has been played is now worth **0 points** and
  can no longer be submitted, so it cannot retroactively change the result.
  There are as many point-earning phases as entries in `winner_bet_points`;
  beyond that (from the semifinal on) the achievable points are 0, regardless
  of how many phases were completed before. The place-bets form disables the
  winner bet and shows a "closed" note once the semifinal is played.
- **Standings podium & browse-rounds:** the "Top 3" podium under the standings
  and the last step of "browse rounds" now include the tournament winner bet,
  so they match the actual final ranking (previously computed without it).

### Upgrade

Pure bugfix/UX release. No schema changes, no `drush updb` required. Run
`lando drush cr` and import translations after updating.

## [1.1.14] - 2026-07-18

### Fixed

- **WinnerBetService:** The tournament winner bet is now scored when the
  final is decided in extra time or a penalty shootout. `resolveWinnerTeamId()`
  determined the champion from the score only, so a final that ended level
  after 120 minutes resolved to "no winner" and no tipper received bonus
  points. It now prefers `winner_team_id` (falling back to the score), and
  `loadFinalGame()` loads that field — consistent with `loadEliminatedTeams()`.
- **TippsController:** The "All Bets" overview only filtered games by kickoff,
  not by `published`. Bets of every player for a game excluded from scoring
  (`published = 0`) were therefore still visible. The overview now also
  requires `published = 1`, consistent with the live standings, the ranking
  and the "browse rounds" view.

### Upgrade

Pure bugfix release. No schema changes, no `drush updb` required.
Run `lando drush cr` after updating.

## [1.1.13] - 2026-07-08

### Added

- The bet cell in the live standings, the "browse rounds" step
  table, the tipper detail table and the All Bets overview now
  prefixes the tipper's qualifier bet with the 3-letter team code
  (e.g. `GER 1:1`).
- Locked / already played KO bets show a plain-text line "Your
  qualifier bet: **XYZ**" so tippers can see who they picked even
  after the deadline.
- Penalty shootout result is now also shown next to the score in the
  tipper detail table and in the "browse rounds" scoreboard (was
  already visible in the All Bets overview).
- Live scoreboard shows the penalty shootout score in parentheses
  as soon as the API reports one, so ongoing shootouts are visible
  in real time.
- The qualifier team of a finished KO match is now rendered in bold
  in the live scoreboard, the step scoreboard, the tipper detail
  table and the All Bets overview.

### Changed

- Live update window extended from 180 to 200 minutes so extra time
  plus penalty shootouts stay in the live view until they finish.
- Live score bracket next to each bet now includes the KO qualifier
  bonus (`sonderpunkte`) from `ScoringService`. Previously the total
  in the row footer already contained the bonus, but the per-bet
  bracket only showed base plus live bonus.
- Score update skip check no longer suppresses penalty updates when
  the API reports a new penalty score but the DB already has an
  older one — the shootout progresses live now.

## [1.1.12] - 2026-07-02

### Added

- Tournament form: a checkbox group "Knockout phases in this
  tournament" that lists all KO phases the tournament actually plays
  (`round_of_32`, `round_of_16`, `quarter`, `semi`, `third_place`,
  `final`). Stored in a new `ko_phases` column on
  `soccerbet_tournament` (update 8017).
- API import: new fine-grained "Import scope" dropdown replaces the
  old "group stage only" checkbox. Options: teams only, group stage
  matches, and one entry per KO phase configured for the tournament.
  New public method `ApiImportService::importScope()`.
- Admin lists: "↓ API import" secondary button next to "+ New team"
  and "+ New match" — direct shortcut to the import form from both
  the games and teams overview.
- Standings round-by-round navigation: additional "«" (first round)
  and "»" (last round) edge buttons alongside "Previous" / "Next".

### Changed

- API stage mapping: `LAST_32` → `round_of_32` and `LAST_16` →
  `round_of_16` (were previously not mapped and fell through to the
  group_name fallback). Fixes KO matches being imported as `group`.
- Team import: fallback to `team_flag` match if the team name from
  the API changed (e.g. "Czech Republic" → "Czechia"). The existing
  team is reused and the name is *not* overwritten — no duplicate
  rows are created.
- German translation: "Round of 32" is now "Sechzehntelfinale"
  (was: "Runde der letzten 32").
- Games admin overview: phase details are now collapsed when every
  match in that phase already has a result. Ongoing phases stay
  open.
- Standings step view (`standings/{tid}/step/{limit}`): the Bonus
  tab now also lists the tournament winner bets — they were
  missing because `winner_bets` was never passed to the template
  in step mode.

### Upgrade notes

- After `drush updb` adds the new `ko_phases` column, edit each
  tournament and select the KO phases it plays. Otherwise the API
  import form only offers "Teams only" and "Group stage".

### Added

- New schema columns `penalty_score_1` / `penalty_score_2` on
  `soccerbet_games` for storing penalty shootout results
  (hook_update_N 8016).
- Bets overview shows the penalty shootout score next to a draw
  result, e.g. `1:1 (3:4 i.E.)`.
- Bonus tab now flags an eliminated tournament-winner bet: the team
  name is struck through with an "(eliminated)" hint, and the
  affected tipper sees a red alert badge on the Bonus tab plus a
  banner with a direct "Update bet" link.
- Score edit form (admin) gets an optional penalty shootout result
  field.

### Changed

- Live score update: use the correct 120-minute result for knockout
  matches. `fullTime` is used for `REGULAR`/`EXTRA_TIME`;
  `regularTime + extraTime` is used for `PENALTY_SHOOTOUT` (so the
  penalty goals are not added to the match score). The qualifier for
  the knockout bonus is now taken from the API `score.winner` field,
  which handles penalty shootouts correctly.
- Team-name fallback in `ScoreUpdateService` no longer overwrites
  games that already have a score. Setting a game's `api_id` to NULL
  now acts as an explicit opt-out from automatic updates for that
  match.
- Knockout qualifier bonus is only awarded when the match actually
  ended in a draw after 120 min (previously the bonus could be
  granted on any correct qualifier match, even when the qualifier
  was already obvious from the result).
- `WinnerBetService`: tips on eliminated teams show
  `possible_points = 0` / `actual_points = 0`. Static request cache
  for the eliminated-teams lookup.
- Standings sticky header: the JS ghost header now accounts for the
  Drupal admin toolbar height and runs an initial update so the
  header shows up on desktop and tablet, not just on mobile.
- Translation update: "Weiterkommer" replaced with "Aufsteiger"
  throughout the German translation.

### Fixed

- `GameScoreForm` no longer requires selecting a qualifier when the
  admin saves a draw score. This lets admins record the 1:1 result
  of a running penalty shootout before the qualifier is decided.

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
