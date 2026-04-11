# Soccer Bet

Soccer Bet is a football prediction game (Tippspiel) for Drupal 10/11. It allows groups of users to place score predictions on football matches, earn points, and compete on a leaderboard.

## Features

- **Tournaments** — create and manage multiple betting tournaments; one active tournament shown on the public leaderboard at a time
- **Teams & Matches** — manual entry or automatic import via the football-data.org API
- **Score Predictions** — participants place home/away score predictions before each match; configurable lockout window (e.g. 15 minutes before kick-off)
- **KO Round Support** — participants also predict the advancing team when a draw is possible in knockout rounds
- **Tournament Winner Bet** — bonus bet on the overall tournament winner with configurable points
- **Scoring System**
  - Exact result: 3 points (configurable)
  - Correct tendency (win/draw/loss): 1 point (configurable)
  - Bonus points flow between players per match: correct bettors receive points from wrong bettors
  - KO round winner correct: points equal to number of participants
- **Live Leaderboard** — real-time rank updates during matches via automatic score polling (football-data.org API)
- **Round-by-Round History** — step through standings after each match day
- **Bets Overview** — transposed table showing all bets from all participants across all matches, colour-coded by result
- **Payment Tracking** — mark participation fees as paid per tournament
- **Shoutbox** — per-tournament chat
- **Flags** — national team and club flags via circle-flags SVGs (ISO 3166-1 Alpha-3)
- **Multilingual** — all UI strings in English; German translation included

## Requirements

- Drupal 10 or 11
- PHP 8.2+
- A free or paid API key from [football-data.org](https://www.football-data.org) *(optional — required only for automatic match import and live score updates; manual score entry works without it)*

## Installation

```bash
composer require drupal/soccerbet
drush en soccerbet -y
drush updb -y
```

## Configuration

Go to **Administration → Soccer Bet → Settings** (`/admin/config/soccerbet`).

| Setting | Description | Default |
|---|---|---|
| Default tournament | Tournament shown on the public leaderboard | — |
| Points for exact result | Points awarded for a correct score | 3 |
| Points for correct tendency | Points awarded for correct win/draw/loss | 1 |
| Betting closes N minutes before kick-off | 0 = bets close exactly at kick-off | 0 |
| football-data.org API key | Required for API import and live updates | — |
| Enable automatic score updates | Polls the API on every Drupal cron run | off |
| Enable live scores | Enables the live leaderboard page | off |

### Permissions

Assign the following permissions to the appropriate roles:

| Permission | Recommended for |
|---|---|
| `access soccerbet content` | Authenticated users |
| `place soccerbet bets` | Authenticated users |
| `administer soccerbet` | Administrators |
| `edit soccerbet scores` | Scorekeeper role |
| `manage soccerbet payments` | Administrators |

### Tipper–User mapping

Each bettor (*Tipper*) is linked to a Drupal user account. Go to **Admin → Soccer Bet → Participants** to create tippers and link them to user accounts.

Tippers can be organised into groups. A tournament can be restricted to one or more groups, so different groups of friends or colleagues can run separate competitions on the same Drupal site.

## Using the football-data.org API

Soccer Bet integrates with [football-data.org](https://www.football-data.org) for automatic match import and live score updates.

### 1. Get an API key

Register at football-data.org. The free tier covers most major competitions (Bundesliga, Premier League, Champions League, World Cup, Euro, etc.) with a rate limit of 10 requests/minute.

### 2. Enter the API key

Go to **Admin → Soccer Bet → Settings** and enter your API key in the *football-data.org API key* field.

### 3. Import matches

Go to **Admin → Soccer Bet → Settings → Score update** and enter the competition code for your tournament. Common codes:

| Code | Competition |
|---|---|
| `BL1` | Bundesliga (Germany) |
| `PL` | Premier League (England) |
| `CL` | UEFA Champions League |
| `WC` | FIFA World Cup |
| `EC` | UEFA European Championship |
| `SA` | Serie A (Italy) |
| `PD` | La Liga (Spain) |
| `FL1` | Ligue 1 (France) |

A full list of available competitions is available on the football-data.org website.

Select the season (year) and click **Import** to fetch all teams and matches. Existing matches are updated, not duplicated.

### 4. Automatic score updates via cron

Enable **Automatic score updates** in the settings. Soccer Bet hooks into Drupal cron with an adaptive polling strategy:

| Mode | Condition | Behaviour |
|---|---|---|
| Live | A match is within ±3 hours of kick-off (UTC) | Updates on every cron run |
| Idle | No matches within the ±3 hour window | Updates at most every 60 minutes |
| Night | 23:00–06:00 UTC | No updates |

Make sure Drupal cron runs frequently — at least every few minutes during live matches (e.g. via system crontab or the [Ultimate Cron](https://www.drupal.org/project/ultimate_cron) module).

### 5. Live leaderboard

Enable **Live scores** in the settings. The live leaderboard is available at `/soccerbet/live/{tournament_id}` and updates automatically in the browser without page reload.

## Public pages

| Path | Description |
|---|---|
| `/soccerbet/standings/{tournament_id}` | Leaderboard with rank changes |
| `/soccerbet/place-bets/{tournament_id}` | Bet placement form |
| `/soccerbet/tipps/{tournament_id}` | All bets from all participants |
| `/soccerbet/live/{tournament_id}` | Live leaderboard |
| `/soccerbet/tables/{tournament_id}` | Group tables |

## Maintainers

- [Peter Windholz](https://www.drupal.org/u/xurubo93)
