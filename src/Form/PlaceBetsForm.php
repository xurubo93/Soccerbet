<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Drupal\soccerbet\Service\WinnerBetService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formular: Tipps abgeben und bearbeiten.
 *
 * Unterstützt:
 * - Normale Benutzer: eigene Tipps für das aktive Turnier
 * - Admins: Tipps für beliebigen Tipper/Turnier (via Route-Parameter)
 * - Tipp-Sperre: X Minuten vor Anpfiff keine Änderung mehr möglich
 * - KO-Runden: Aufsteiger-Tipp zusätzlich zum Ergebnis
 */
final class PlaceBetsForm extends FormBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
    private readonly WinnerBetService $winnerBet,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.winner_bet'),
    );
  }

  public function getFormId(): string {
    return 'soccerbet_place_bets_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    int $tipper_id = 0,
    int $tournament_id = 0,
  ): array {
    $current_uid = (int) $this->currentUser()->id();

    // Turnier auflösen
    if ($tournament_id === 0) {
      $tournament_id = (int) \Drupal::config('soccerbet.settings')->get('default_tournament');
    }
    if ($tournament_id === 0) {
      return ['#markup' => $this->t('No active tournament configured.')];
    }

    $tournament = $this->tournamentManager->load($tournament_id);
    $is_admin   = $this->currentUser()->hasPermission('administer soccerbet');

    // Tipper auflösen: über alle Tippergruppen des Turniers suchen
    if ($tipper_id === 0) {
      $tipper = NULL;
      foreach ($this->tournamentManager->loadTipperGroupIds($tournament_id) as $group_id) {
        $tipper = $this->tipperManager->loadTipperByUid($current_uid, $group_id);
        if ($tipper) {
          break;
        }
      }
      if (!$tipper) {
        return [
          '#markup' => $this->t(
            'You are not a participant in this tournament. Please contact the administrator.'
          ),
        ];
      }
      $tipper_id = (int) $tipper->tipper_id;
    }
    else {
      // Explizite tipper_id übergeben – nur Admin darf fremde Tipps bearbeiten
      $tipper = $this->tipperManager->loadTipper($tipper_id);
      if (!$tipper) {
        return ['#markup' => $this->t('Bettor not found.')];
      }
      $tipper_owner_uid = (int) $tipper->uid;
      if (!$is_admin && $tipper_owner_uid !== $current_uid) {
        return ['#markup' => $this->t('Not authorized.')];
      }
    }

    // Tipper-Name im Formular-Titel anzeigen wenn Admin für anderen bearbeitet
    $tipper_name = $tipper->tipper_name ?? '';
    $form_state->set('is_admin_edit', $is_admin && (int) ($tipper->uid ?? 0) !== $current_uid);
    $form_state->set('tipper_name',   $tipper_name);

    $form_state->set('tipper_id',    $tipper_id);
    $form_state->set('tournament_id', $tournament_id);

    // Konfiguration für Tipp-Sperre
    $lock_minutes = (int) \Drupal::config('soccerbet.settings')
      ->get('betting_closes_minutes_before');

    // Alle (noch nicht gestarteten) Spiele laden
    $games       = $this->tipperManager->loadGamesByTournament($tournament_id);
    $saved_tipps = $this->tipperManager->loadTippsByTipper($tipper_id, $tournament_id);

    $now = \Drupal::time()->getRequestTime();

    $phase_labels = [
      'group'       => $this->t('Group stage'),
      'round_of_32' => $this->t('Round of 32'),
      'round_of_16' => $this->t('Round of 16'),
      'quarter'     => $this->t('Quarter-final'),
      'semi'        => $this->t('Semi-final'),
      'third_place' => $this->t('Third-place match'),
      'final'       => $this->t('Final'),
    ];

    // Nach Phase gruppieren – Phasen-Reihenfolge aus $phase_labels übernehmen
    // Spiele kommen bereits nach game_date sortiert aus der DB
    $games_by_phase = array_fill_keys(array_keys($phase_labels), []);
    foreach ($games as $game) {
      $phase = $game->phase ?? 'group';
      if (!isset($games_by_phase[$phase])) {
        $games_by_phase[$phase] = [];
      }
      $games_by_phase[$phase][] = $game;
    }
    // Leere Phasen entfernen
    $games_by_phase = array_filter($games_by_phase);

    $has_open_games = FALSE;

    // Admin-Hinweis-Banner
    if ($form_state->get('is_admin_edit')) {
      $form['admin_notice'] = [
        '#markup' => '<div class="messages messages--warning soccerbet-admin-edit-notice">'
          . $this->t('You are editing the bets of <strong>@name</strong> as administrator.', ['@name' => $tipper_name])
          . '</div>',
        '#weight' => -99,
      ];
    }

    // Anker-Ziel für "Zum nächsten offenen Spiel" – wird später gesetzt
    $first_open_anchor = NULL;

    foreach ($games_by_phase as $phase => $phase_games) {
      $phase_label = $phase_labels[$phase] ?? $phase;
      $ko_phase    = !in_array($phase, ['group'], TRUE);

      // Spiele in gespielt / offen aufteilen
      // Admin darf alle Spiele bearbeiten – keine Zeitsperre
      $played_games = [];
      $open_games   = [];
      foreach ($phase_games as $game) {
        $kickoff  = $this->utcTimestamp($game->game_date);
        $deadline = $kickoff - ($lock_minutes * 60);
        $locked   = $is_admin ? FALSE : ($now >= $deadline);
        $played   = ($game->team1_score !== NULL) && ($now >= $kickoff);
        if ($played || $locked) {
          $played_games[] = $game;
        }
        else {
          $open_games[] = $game;
          if ($first_open_anchor === NULL) {
            $first_open_anchor = 'open-' . (int) $game->game_id;
          }
        }
      }

      $form[$phase] = [
        '#type'       => 'details',
        '#title'      => $phase_label . ($open_games ? ' (' . count($open_games) . ' ' . $this->t('open') . ')' : ''),
        '#open'       => !empty($open_games),
        '#attributes' => ['class' => ['soccerbet-phase-group']],
      ];

      // Gespielte/gesperrte Spiele – zugeklappt
      if (!empty($played_games)) {
        $form[$phase]['played_wrapper'] = [
          '#type'       => 'details',
          '#title'      => $this->t('@count played/locked matches', ['@count' => count($played_games)]),
          '#open'       => FALSE,
          '#attributes' => ['class' => ['soccerbet-played-games']],
        ];
        foreach ($played_games as $game) {
          $this->buildGameElement($form[$phase]['played_wrapper'], $game, $saved_tipps, $lock_minutes, $now, $ko_phase);
        }
      }

      // Offene Spiele – direkt sichtbar
      foreach ($open_games as $game) {
        $has_open_games = TRUE;
        if ($ko_phase) {
          $ko_open_game_ids[] = (int) $game->game_id;
        }
        $this->buildGameElement($form[$phase], $game, $saved_tipps, $lock_minutes, $now, $ko_phase, 'open-' . (int) $game->game_id);
      }
    }
    $form_state->set('ko_open_game_ids', $ko_open_game_ids ?? []);

    // Turniersieger-Tipp – immer ganz oben, immer offen
    $teams = $this->tipperManager->loadTeamsByTournament($tournament_id);
    usort($teams, fn($a, $b) => strcmp($a->team_name, $b->team_name));
    $team_options = [0 => $this->t('— please select —')];
    foreach ($teams as $team) {
      $flag = $team->team_flag ? ($this->flagEmoji($team->team_flag) . ' ') : '';
      $team_options[(int) $team->team_id] = $flag . $team->team_name;
    }
    $existing_bet = $this->winnerBet->loadBet($tournament_id, $tipper_id);
    $phase_index  = $this->winnerBet->getCurrentPhaseIndex($tournament_id);
    $next_points  = $this->winnerBet->getPointsForPhaseIndex($phase_index);

    $form['winner_bet'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Tournament winner bet'),
      '#attributes' => ['class' => ['soccerbet-winner-bet']],
      '#weight'     => -200,
    ];
    $form['winner_bet']['info'] = [
      '#markup' => '<p class="soccerbet-winner-bet__info">'
        . $this->t('Currently possible points for correct bet: <strong>@pts</strong>', ['@pts' => $next_points])
        . ($existing_bet ? ' · ' . $this->t('Your current bet: <strong>@team</strong> (@pts points)', [
            '@team' => $team_options[(int) $existing_bet->team_id] ?? '?',
            '@pts'  => $this->winnerBet->getPointsForPhaseIndex((int) $existing_bet->phase_index),
          ]) : '')
        . '</p>',
    ];
    $form['winner_bet']['winner_team_id'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Which team will win the tournament?'),
      '#options'       => $team_options,
      '#default_value' => $existing_bet ? (int) $existing_bet->team_id : 0,
    ];

    // Mobiler Schnell-Link
    if ($first_open_anchor) {
      $form['mobile_jump'] = [
        '#markup' => '<div class="soccerbet-mobile-jump">'
          . '<a href="#' . $first_open_anchor . '" class="soccerbet-mobile-jump__link button button--small">'
          . $this->t('↓ To the next open match')
          . '</a></div>',
        '#weight' => -100,
      ];
    }

    // Submit-Button immer anzeigen (für Turniersieger-Tipp auch wenn alle Spiele gesperrt)
    $form['submit_wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['soccerbet-submit-sticky']],
    ];
    $form['submit_wrapper']['submit'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Save bets'),
      '#attributes' => ['class' => ['button', 'button--primary', 'soccerbet-submit']],
    ];
    if (!$has_open_games) {
      $form['no_games'] = [
        '#markup' => '<p class="soccerbet-bets__no-games">'
          . $this->t('Match bets are locked – the tournament is running or has ended.')
          . '</p>',
      ];
    }

    return $form;
  }

  /**
   * Baut ein einzelnes Spiel-Element in das Formular-Array ein.
   */
  private function buildGameElement(
    array &$container,
    object $game,
    array $saved_tipps,
    int $lock_minutes,
    int $now,
    bool $ko_phase,
    ?string $anchor_id = NULL,
  ): void {
    $game_id  = (int) $game->game_id;
    $kickoff  = $this->utcTimestamp($game->game_date);
    $deadline = $kickoff - ($lock_minutes * 60);
    $locked   = ($now >= $deadline);
    $played   = ($game->team1_score !== NULL) && ($now >= $kickoff);
    $saved    = $saved_tipps[$game_id] ?? NULL;
    $game_key = 'game_' . $game_id;
    $date_str = \Drupal::service('date.formatter')->format($kickoff, 'custom', 'd.m.Y H:i');

    $container[$game_key] = [
      '#type'       => 'fieldset',
      '#attributes' => [
        'class' => array_filter([
          'soccerbet-game',
          $locked ? 'soccerbet-game--locked' : 'soccerbet-game--open',
          $played ? 'soccerbet-game--played' : NULL,
        ]),
        // Anker-ID für Scroll-Target
        'id' => $anchor_id ?? ('game-' . $game_id),
      ],
    ];

    $container[$game_key]['info'] = [
      '#markup' => '<div class="soccerbet-game__meta">'
        . '<span class="soccerbet-game__date">' . $date_str . '</span>'
        . ($game->game_stadium ? ' · <span class="soccerbet-game__stadium">' . htmlspecialchars($game->game_stadium) . '</span>' : '')
        . ($played ? ' · <span class="soccerbet-game__result">' . $this->t('Result: @s1:@s2', ['@s1' => $game->team1_score, '@s2' => $game->team2_score]) . '</span>' : '')
        . '</div>',
    ];

    // Matchup-Wrapper öffnen
    $container[$game_key]['matchup_open'] = [
      '#markup' => '<div class="soccerbet-game__matchup">',
    ];

    // Team 1 Karte
    $container[$game_key]['team1_card_open'] = [
      '#markup' => '<div class="soccerbet-game__team-card soccerbet-game__team-card--home">'
        . '<div class="soccerbet-game__flag">' . $this->flagHtml($game->team1_flag ?? '', $game->team1_name) . '</div>'
        . '<div class="soccerbet-game__team-name">' . htmlspecialchars($game->team1_name) . '</div>',
    ];
    $container[$game_key]['tipp1_' . $game_id] = [
      '#type'          => 'number',
      '#min'           => 0,
      '#max'           => 99,
      '#default_value' => $saved?->team1_tipp ?? '',
      '#disabled'      => $locked,
      '#attributes'    => ['class' => ['soccerbet-tipp-input'], 'placeholder' => '—'],
      '#title'         => $game->team1_name,
      '#title_display' => 'invisible',
    ];
    $container[$game_key]['team1_card_close'] = ['#markup' => '</div>'];

    // Trennzeichen
    $container[$game_key]['vs'] = [
      '#markup' => '<div class="soccerbet-game__vs"><span class="soccerbet-game__separator">:</span></div>',
    ];

    // Team 2 Karte
    $container[$game_key]['team2_card_open'] = [
      '#markup' => '<div class="soccerbet-game__team-card soccerbet-game__team-card--away">'
        . '<div class="soccerbet-game__flag">' . $this->flagHtml($game->team2_flag ?? '', $game->team2_name) . '</div>'
        . '<div class="soccerbet-game__team-name">' . htmlspecialchars($game->team2_name) . '</div>',
    ];
    $container[$game_key]['tipp2_' . $game_id] = [
      '#type'          => 'number',
      '#min'           => 0,
      '#max'           => 99,
      '#default_value' => $saved?->team2_tipp ?? '',
      '#disabled'      => $locked,
      '#attributes'    => ['class' => ['soccerbet-tipp-input'], 'placeholder' => '—'],
      '#title'         => $game->team2_name,
      '#title_display' => 'invisible',
    ];
    $container[$game_key]['team2_card_close'] = ['#markup' => '</div>'];

    // Matchup-Wrapper schließen
    $container[$game_key]['matchup_close'] = ['#markup' => '</div>'];

    // KO-Runden: Aufsteiger-Tipp (nur sichtbar wenn Unentschieden getippt)
    if ($ko_phase && !$played) {
      $t1 = $saved?->team1_tipp;
      $t2 = $saved?->team2_tipp;
      $show_winner = ($t1 !== NULL && $t2 !== NULL && (int) $t1 === (int) $t2);
      $container[$game_key]['winner_' . $game_id] = [
        '#type'               => 'select',
        '#title'              => $this->t('Qualifier/Winner'),
        '#options'            => [
          $game->team_id_1 => $game->team1_name,
          $game->team_id_2 => $game->team2_name,
        ],
        '#default_value'      => $saved?->winner_team_id ?? $game->team_id_1,
        '#disabled'           => $locked,
        '#attributes'         => [
          'class'         => ['soccerbet-winner-select'],
          'data-game-id'  => $game_id,
        ],
        '#wrapper_attributes' => [
          'class' => ['soccerbet-winner-wrap'],
          'style' => $show_winner ? '' : 'display: none',
        ],
      ];
    }

    // Gesperrt-Hinweis
    if ($locked && !$played) {
      $container[$game_key]['locked_info'] = [
        '#markup' => '<div class="soccerbet-game__locked-info">'
          . $this->t('Bet closed (kickoff: @date)', ['@date' => $date_str])
          . '</div>',
      ];
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values      = $form_state->getValues();

    // Alle tipp1_*-Keys durchsuchen und Eingaben validieren
    foreach ($values as $key => $value) {
      if (!str_starts_with($key, 'tipp1_')) {
        continue;
      }
      $game_id = (int) substr($key, 6);

      $tipp1 = $values['tipp1_' . $game_id];
      $tipp2 = $values['tipp2_' . $game_id];

      if ($tipp1 !== '' && $tipp2 === '') {
        $form_state->setErrorByName(
          'tipp2_' . $game_id,
          $this->t('Please also enter the result for team 2.')
        );
      }
      if ($tipp2 !== '' && $tipp1 === '') {
        $form_state->setErrorByName(
          'tipp1_' . $game_id,
          $this->t('Please also enter the result for team 1.')
        );
      }
    }

    // KO-Spiele: Bei Unentschieden muss ein Aufsteiger gewählt werden
    foreach ($form_state->get('ko_open_game_ids') ?? [] as $game_id) {
      $tipp1  = $values['tipp1_' . $game_id] ?? '';
      $tipp2  = $values['tipp2_' . $game_id] ?? '';
      $winner = $values['winner_' . $game_id] ?? '';
      if ($tipp1 !== '' && $tipp2 !== '' && (int) $tipp1 === (int) $tipp2 && (int) $winner === 0) {
        $form_state->setErrorByName(
          'winner_' . $game_id,
          $this->t('In case of a draw, a qualifier must be selected.')
        );
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values       = $form_state->getValues();
    $tipper_id    = $form_state->get('tipper_id');
    $tournament_id = $form_state->get('tournament_id');
    $lock_minutes = (int) \Drupal::config('soccerbet.settings')
      ->get('betting_closes_minutes_before');
    $now = \Drupal::time()->getRequestTime();

    $saved_count = 0;

    foreach ($values as $key => $value) {
      if (!str_starts_with($key, 'tipp1_')) {
        continue;
      }
      $game_id = (int) substr($key, 6);
      $tipp1   = $values['tipp1_' . $game_id];
      $tipp2   = $values['tipp2_' . $game_id];

      // Leere Tipps überspringen
      if ($tipp1 === '' || $tipp2 === '') {
        continue;
      }

      // Deadline nochmals prüfen (Defense-in-depth)
      try {
        $game    = $this->tipperManager->loadGame($game_id);
        $kickoff = $this->utcTimestamp($game->game_date);
        if ($now >= ($kickoff - $lock_minutes * 60)) {
          continue; // Spiel bereits gestartet
        }
      }
      catch (\Exception) {
        continue;
      }

      // Winner nur bei Unentschieden berücksichtigen (Select ist sonst ausgeblendet)
      $winner = NULL;
      if ((int) $tipp1 === (int) $tipp2 && isset($values['winner_' . $game_id])) {
        $w = (int) $values['winner_' . $game_id];
        $winner = $w > 0 ? $w : NULL;
      }

      $this->tipperManager->saveTipp(
        $tipper_id,
        $game_id,
        (int) $tipp1,
        (int) $tipp2,
        $winner,
      );
      $saved_count++;
    }

    // Turniersieger-Tipp speichern
    $winner_team_id = (int) ($values['winner_team_id'] ?? 0);
    if ($winner_team_id > 0) {
      $this->winnerBet->saveBet($tournament_id, $tipper_id, $winner_team_id);
    }

    if ($saved_count > 0) {
      $this->messenger()->addStatus(
        $this->t('@count bet(s) have been saved.', ['@count' => $saved_count])
      );
    }
    elseif ($winner_team_id === 0) {
      $this->messenger()->addWarning($this->t('No bets were changed.'));
    }

    // Admin zurück zur Teilnehmer-Liste, normaler User zur Rangliste
    if ($form_state->get('is_admin_edit')) {
      $form_state->setRedirectUrl(
        Url::fromRoute('soccerbet.admin.tournament.members', ['tournament_id' => $tournament_id])
      );
    }
    else {
      $form_state->setRedirectUrl(
        Url::fromRoute('soccerbet.standings', ['tournament_id' => $tournament_id])
      );
    }
  }

  /**
   * Parst einen UTC-Datums-String aus der DB als UTC-Timestamp.
   * game_date ist immer UTC in der DB gespeichert.
   */
  private function utcTimestamp(string $game_date): int {
    return (int) (new \DateTimeImmutable($game_date, new \DateTimeZone('UTC')))->getTimestamp();
  }

  /**
   * Erzeugt HTML für eine Länderflagge – konsistent mit soccerbet-flag.html.twig.
   * SVG mit PNG@2x/PNG@1x Fallback via onerror.
   */
  private function flagHtml(string $code, string $alt = ''): string {
    if ($code === '') {
      return '';
    }
    $module_path = \Drupal::service('extension.list.module')->getPath('soccerbet');
    $svg   = '/' . $module_path . '/images/flags/svg/' . $code . '.svg';
    $alt_e = htmlspecialchars($alt ?: $code, ENT_QUOTES);
    return '<img src="' . $svg . '"'
      . ' alt="' . $alt_e . '"'
      . ' width="28" height="28"'
      . ' class="soccerbet-flag"'
      . ' loading="lazy">';
  }

  /**
   * Konvertiert einen Alpha-3-Code in ein Emoji-Flag über Alpha-2-Mapping.
   */
  private function flagEmoji(string $code): string {
    $map = [
      // Europa
      'AUT' => 'AT', 'DEU' => 'DE', 'CHE' => 'CH', 'FRA' => 'FR',
      'ESP' => 'ES', 'ITA' => 'IT', 'PRT' => 'PT', 'NLD' => 'NL',
      'BEL' => 'BE', 'DNK' => 'DK', 'SWE' => 'SE', 'NOR' => 'NO',
      'FIN' => 'FI', 'POL' => 'PL', 'CZE' => 'CZ', 'SVK' => 'SK',
      'HUN' => 'HU', 'ROU' => 'RO', 'BGR' => 'BG', 'HRV' => 'HR',
      'SRB' => 'RS', 'SVN' => 'SI', 'BIH' => 'BA', 'MKD' => 'MK',
      'ALB' => 'AL', 'MNE' => 'ME', 'GRC' => 'GR', 'TUR' => 'TR',
      'RUS' => 'RU', 'UKR' => 'UA', 'BLR' => 'BY', 'MDA' => 'MD',
      'GEO' => 'GE', 'ARM' => 'AM', 'AZE' => 'AZ', 'KAZ' => 'KZ',
      'ISL' => 'IS', 'IRL' => 'IE', 'GBR' => 'GB', 'CYP' => 'CY',
      'MLT' => 'MT', 'LUX' => 'LU', 'ISR' => 'IL', 'FRO' => 'FO',
      'AND' => 'AD', 'LIE' => 'LI', 'GIB' => 'GI', 'SMR' => 'SM',
      // Amerika
      'BRA' => 'BR', 'ARG' => 'AR', 'URY' => 'UY', 'COL' => 'CO',
      'CHL' => 'CL', 'PRY' => 'PY', 'ECU' => 'EC', 'VEN' => 'VE',
      'PER' => 'PE', 'BOL' => 'BO', 'MEX' => 'MX', 'USA' => 'US',
      'CAN' => 'CA', 'CRI' => 'CR', 'HND' => 'HN', 'PAN' => 'PA',
      'SLV' => 'SV', 'GTM' => 'GT', 'JAM' => 'JM', 'HTI' => 'HT',
      'CUB' => 'CU', 'TTO' => 'TT', 'CUW' => 'CW', 'KNA' => 'KN',
      'VCT' => 'VC', 'LCA' => 'LC',
      // Asien
      'JPN' => 'JP', 'KOR' => 'KR', 'CHN' => 'CN', 'AUS' => 'AU',
      'NZL' => 'NZ', 'IRN' => 'IR', 'SAU' => 'SA', 'QAT' => 'QA',
      'ARE' => 'AE', 'IRQ' => 'IQ', 'SYR' => 'SY', 'JOR' => 'JO',
      'OMN' => 'OM', 'BHR' => 'BH', 'KWT' => 'KW', 'UZB' => 'UZ',
      'PHL' => 'PH', 'IDN' => 'ID', 'IND' => 'IN', 'MYS' => 'MY',
      'VNM' => 'VN', 'THA' => 'TH', 'TWN' => 'TW', 'FJI' => 'FJ',
      'PNG' => 'PG', 'PYF' => 'PF', 'NCL' => 'NC',
      // Afrika
      'NGA' => 'NG', 'GHA' => 'GH', 'CMR' => 'CM', 'SEN' => 'SN',
      'MAR' => 'MA', 'EGY' => 'EG', 'TUN' => 'TN', 'DZA' => 'DZ',
      'MLI' => 'ML', 'BFA' => 'BF', 'ZAF' => 'ZA', 'CIV' => 'CI',
      'ZMB' => 'ZM', 'ZWE' => 'ZW', 'TZA' => 'TZ', 'GMB' => 'GM',
      'GIN' => 'GN', 'GNB' => 'GW', 'GNQ' => 'GQ', 'COG' => 'CG',
      'COD' => 'CD', 'LSO' => 'LS', 'MDG' => 'MG', 'MOZ' => 'MZ',
      'AGO' => 'AO', 'RWA' => 'RW', 'UGA' => 'UG', 'KEN' => 'KE',
      'ETH' => 'ET', 'LBR' => 'LR', 'SLE' => 'SL', 'BEN' => 'BJ',
      'NER' => 'NE', 'CPV' => 'CV', 'COM' => 'KM', 'MRT' => 'MR',
      'SDN' => 'SD', 'SSD' => 'SS', 'LBY' => 'LY',
    ];
    $upper = strtoupper(trim($code));

    // UK sub-national flags use Unicode tag sequences, not regional indicators.
    $tag_flags = [
      'ENG' => "\u{1F3F4}\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F}",
      'SCO' => "\u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}",
      'WAL' => "\u{1F3F4}\u{E0067}\u{E0062}\u{E0077}\u{E006C}\u{E0073}\u{E007F}",
      'NIR' => "\u{1F1EC}\u{1F1E7}",  // No standard emoji → GB flag
    ];
    if (isset($tag_flags[$upper])) {
      return $tag_flags[$upper];
    }

    $alpha2 = $map[$upper] ?? '';
    if (strlen($alpha2) !== 2) {
      return '';
    }
    return mb_chr(0x1F1E6 + ord($alpha2[0]) - ord('A'))
         . mb_chr(0x1F1E6 + ord($alpha2[1]) - ord('A'));
  }
}
