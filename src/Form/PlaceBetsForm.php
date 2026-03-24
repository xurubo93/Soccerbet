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
      return ['#markup' => $this->t('Kein aktives Turnier konfiguriert.')];
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
            'Du bist kein Teilnehmer in diesem Turnier. Bitte wende dich an den Administrator.'
          ),
        ];
      }
      $tipper_id = (int) $tipper->tipper_id;
    }
    else {
      // Explizite tipper_id übergeben – nur Admin darf fremde Tipps bearbeiten
      $tipper = $this->tipperManager->loadTipper($tipper_id);
      if (!$tipper) {
        return ['#markup' => $this->t('Tipper nicht gefunden.')];
      }
      $tipper_owner_uid = (int) $tipper->uid;
      if (!$is_admin && $tipper_owner_uid !== $current_uid) {
        return ['#markup' => $this->t('Keine Berechtigung.')];
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
      'group'       => $this->t('Gruppenphase'),
      'round_of_16' => $this->t('Achtelfinale'),
      'quarter'     => $this->t('Viertelfinale'),
      'semi'        => $this->t('Halbfinale'),
      'third_place' => $this->t('Spiel um Platz 3'),
      'final'       => $this->t('Finale'),
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
          . $this->t('Du bearbeitest die Tipps von <strong>@name</strong> als Administrator.', ['@name' => $tipper_name])
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
        '#title'      => $phase_label . ($open_games ? ' (' . count($open_games) . ' ' . $this->t('offen') . ')' : ''),
        '#open'       => !empty($open_games),
        '#attributes' => ['class' => ['soccerbet-phase-group']],
      ];

      // Gespielte/gesperrte Spiele – zugeklappt
      if (!empty($played_games)) {
        $form[$phase]['played_wrapper'] = [
          '#type'       => 'details',
          '#title'      => $this->t('@count gespielte/gesperrte Spiele', ['@count' => count($played_games)]),
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
    $team_options = [0 => $this->t('— bitte wählen —')];
    foreach ($teams as $team) {
      $flag = $team->team_flag ? ($this->flagEmoji($team->team_flag) . ' ') : '';
      $team_options[(int) $team->team_id] = $flag . $team->team_name;
    }
    $existing_bet = $this->winnerBet->loadBet($tournament_id, $tipper_id);
    $phase_index  = $this->winnerBet->getCurrentPhaseIndex($tournament_id);
    $next_points  = $this->winnerBet->getPointsForPhaseIndex($phase_index);

    $form['winner_bet'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Turniersieger-Tipp'),
      '#attributes' => ['class' => ['soccerbet-winner-bet']],
      '#weight'     => -200,
    ];
    $form['winner_bet']['info'] = [
      '#markup' => '<p class="soccerbet-winner-bet__info">'
        . $this->t('Aktuell mögliche Punkte bei richtigem Tipp: <strong>@pts</strong>', ['@pts' => $next_points])
        . ($existing_bet ? ' · ' . $this->t('Dein aktueller Tipp: <strong>@team</strong> (@pts Punkte)', [
            '@team' => $team_options[(int) $existing_bet->team_id] ?? '?',
            '@pts'  => $this->winnerBet->getPointsForPhaseIndex((int) $existing_bet->phase_index),
          ]) : '')
        . '</p>',
    ];
    $form['winner_bet']['winner_team_id'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Welche Mannschaft wird Turniersieger?'),
      '#options'       => $team_options,
      '#default_value' => $existing_bet ? (int) $existing_bet->team_id : 0,
    ];

    // Mobiler Schnell-Link
    if ($first_open_anchor) {
      $form['mobile_jump'] = [
        '#markup' => '<div class="soccerbet-mobile-jump">'
          . '<a href="#' . $first_open_anchor . '" class="soccerbet-mobile-jump__link button button--small">'
          . $this->t('↓ Zum nächsten offenen Spiel')
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
      '#value'      => $this->t('Tipps speichern'),
      '#attributes' => ['class' => ['button', 'button--primary', 'soccerbet-submit']],
    ];
    if (!$has_open_games) {
      $form['no_games'] = [
        '#markup' => '<p class="soccerbet-bets__no-games">'
          . $this->t('Spieltipps sind gesperrt – das Turnier läuft oder ist beendet.')
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
        . ($played ? ' · <span class="soccerbet-game__result">' . $this->t('Ergebnis: @s1:@s2', ['@s1' => $game->team1_score, '@s2' => $game->team2_score]) . '</span>' : '')
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

    // KO-Runden: Aufsteiger-Tipp
    if ($ko_phase && !$played) {
      $container[$game_key]['winner_' . $game_id] = [
        '#type'          => 'select',
        '#title'         => $this->t('Aufsteiger/Sieger'),
        '#options'       => [
          ''               => $this->t('— noch offen —'),
          $game->team_id_1 => $game->team1_name,
          $game->team_id_2 => $game->team2_name,
        ],
        '#default_value' => $saved?->winner_team_id ?? '',
        '#disabled'      => $locked,
        '#attributes'    => ['class' => ['soccerbet-winner-select']],
      ];
    }

    // Gesperrt-Hinweis
    if ($locked && !$played) {
      $container[$game_key]['locked_info'] = [
        '#markup' => '<div class="soccerbet-game__locked-info">'
          . $this->t('Tipp-Abgabe geschlossen (Anpfiff: @date)', ['@date' => $date_str])
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
          $this->t('Bitte auch das Ergebnis für Team 2 eintragen.')
        );
      }
      if ($tipp2 !== '' && $tipp1 === '') {
        $form_state->setErrorByName(
          'tipp1_' . $game_id,
          $this->t('Bitte auch das Ergebnis für Team 1 eintragen.')
        );
      }
    }

    // KO-Spiele: Bei Unentschieden muss ein Aufsteiger gewählt werden
    foreach ($form_state->get('ko_open_game_ids') ?? [] as $game_id) {
      $tipp1  = $values['tipp1_' . $game_id] ?? '';
      $tipp2  = $values['tipp2_' . $game_id] ?? '';
      $winner = $values['winner_' . $game_id] ?? '';
      if ($tipp1 !== '' && $tipp2 !== '' && (int) $tipp1 === (int) $tipp2 && $winner === '') {
        $form_state->setErrorByName(
          'winner_' . $game_id,
          $this->t('Bei Unentschieden muss ein Aufsteiger gewählt werden.')
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

      $winner = isset($values['winner_' . $game_id]) && $values['winner_' . $game_id] !== ''
        ? (int) $values['winner_' . $game_id]
        : NULL;

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
        $this->t('@count Tipp(s) wurden gespeichert.', ['@count' => $saved_count])
      );
    }
    elseif ($winner_team_id === 0) {
      $this->messenger()->addWarning($this->t('Keine Tipps wurden geändert.'));
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
    $upper = strtoupper($code);
    $lower = strtolower($code);
    $base  = '/modules/custom/soccerbet/images/flags';
    $svg   = $base . '/svg/' . $lower . '.svg';
    $png2x = $base . '/PNG/2x/' . $upper . '@2x.png';
    $png1x = $base . '/PNG/1x/' . $upper . '.png';
    $alt_e = htmlspecialchars($alt ?: $upper, ENT_QUOTES);
    return '<img src="' . $svg . '"'
      . ' onerror="this.onerror=null;this.src=\'' . $png2x . '\';this.onerror=function(){this.src=\'' . $png1x . '\'}"'
      . ' alt="' . $alt_e . '"'
      . ' width="28" height="19"'
      . ' class="soccerbet-flag"'
      . ' loading="lazy">';
  }

  /**
   * Konvertiert einen ISO-3166-1-Alpha-2-Code in ein Emoji-Flag (z. B. "de" → 🇩🇪).
   */
  private function flagEmoji(string $code): string {
    $code = strtoupper(trim($code));
    if (strlen($code) !== 2) {
      return '';
    }
    return mb_chr(0x1F1E6 + ord($code[0]) - ord('A'))
         . mb_chr(0x1F1E6 + ord($code[1]) - ord('A'));
  }
}
