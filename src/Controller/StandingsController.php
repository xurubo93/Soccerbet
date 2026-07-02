<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\soccerbet\Service\ScoringService;
use Drupal\soccerbet\Service\TournamentManager;
use Drupal\soccerbet\Service\WinnerBetService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ranglisten-Seiten.
 */
final class StandingsController extends ControllerBase {

  public function __construct(
    private readonly ScoringService $scoring,
    private readonly TournamentManager $tournamentManager,
    private readonly WinnerBetService $winnerBet,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.scoring'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.winner_bet'),
    );
  }

  /**
   * Aktuelle Rangliste eines Turniers.
   */
  public function standings(int $tournament_id = 0): array {
    $tournament_id = $this->resolveTournamentId($tournament_id);

    if ($tournament_id === 0) {
      return $this->noTournamentMessage();
    }

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return $this->noTournamentMessage();
    }

    $rows         = $this->scoring->getRanking($tournament_id);
    $played_games = $this->scoring->getPlayedGamesCount($tournament_id);

    // Frühere Turniere derselben Tippergruppen ermitteln
    $past_tournaments = $this->loadPastTournaments($tournament_id);
    $winner_bets      = $this->winnerBet->loadBetsForTournament($tournament_id);

    // Bonus-Punkte zum Gesamtscore addieren (nur wenn Turnier beendet)
    $bonus_by_tipper = [];
    foreach ($winner_bets as $bet) {
      if ($bet->display_points !== NULL) {
        $bonus_by_tipper[(int) $bet->tipper_id] = (int) $bet->display_points;
      }
    }
    if (!empty($bonus_by_tipper)) {
      foreach ($rows as &$row) {
        if (isset($bonus_by_tipper[$row['tipper_id']])) {
          $row['total'] += $bonus_by_tipper[$row['tipper_id']];
        }
      }
      unset($row);
      // Nach neuem Total neu sortieren und Ränge vergeben
      usort($rows, fn($a, $b) => $b['total'] - $a['total']);
      $rank = 1;
      foreach ($rows as $i => &$row) {
        if ($i > 0 && $row['total'] < $rows[$i - 1]['total']) {
          $rank = $i + 1;
        }
        $row['rank'] = $rank;
      }
      unset($row);
    }

    $avatars = $this->loadAvatarUrls($rows);
    foreach ($rows as &$row) {
      $row['avatar_url'] = $avatars[$row['uid']] ?? NULL;
    }
    unset($row);

    // Eigenen ausgeschiedenen WM-Tipp erkennen — für rotes Rufzeichen am Bonus-Tab.
    $own_bet_eliminated = FALSE;
    $current_uid = (int) $this->currentUser()->id();
    if ($current_uid > 0) {
      foreach ($winner_bets as $bet) {
        if (!$bet->is_eliminated) {
          continue;
        }
        $row = array_values(array_filter($rows, fn($r) => $r['tipper_id'] === $bet->tipper_id))[0] ?? NULL;
        if ($row && (int) $row['uid'] === $current_uid) {
          $own_bet_eliminated = TRUE;
          break;
        }
      }
    }

    return [
      '#theme'              => 'soccerbet_standings',
      '#rows'               => $rows,
      '#tournament'         => $tournament,
      '#played_games'       => $played_games,
      '#past_tournaments'   => $past_tournaments,
      '#winner_bets'        => $winner_bets,
      '#own_bet_eliminated' => $own_bet_eliminated,
      '#cache'              => [
        'tags'     => ['soccerbet_standings:' . $tournament_id],
        'contexts' => ['user'],
        'max-age'  => 60,
      ],
    ];
  }

  /**
   * Rangliste nach N gespielten Spielen (historische Rückschau).
   */
  public function standingsStep(int $tournament_id, int $limit): array {
    $tournament_id = (int) $tournament_id;
    $limit         = (int) $limit;

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return $this->noTournamentMessage();
    }

    $rows      = $this->scoring->getRanking($tournament_id, $limit);
    $max_games = $this->scoring->getPlayedGamesCount($tournament_id);

    $avatars = $this->loadAvatarUrls($rows);
    foreach ($rows as &$row) {
      $row['avatar_url'] = $avatars[$row['uid']] ?? NULL;
    }
    unset($row);

    $step_game   = $this->loadStepGame($tournament_id, $limit);
    $step_tipps  = $step_game ? $this->loadStepTipps($step_game, $tournament_id, $limit) : [];
    $winner_bets = $this->winnerBet->loadBetsForTournament($tournament_id);

    return [
      '#theme'        => 'soccerbet_standings',
      '#rows'         => $rows,
      '#tournament'   => $tournament,
      '#played_games' => $limit,
      '#step_mode'    => TRUE,
      '#step_limit'   => $limit,
      '#max_games'    => $max_games,
      '#step_game'    => $step_game,
      '#step_tipps'   => $step_tipps,
      '#winner_bets'  => $winner_bets,
      '#cache'        => ['max-age' => 300],
    ];
  }

  /**
   * Lädt das N-te gespielte Spiel eines Turniers (sortiert nach Anpfiff).
   */
  private function loadStepGame(int $tournament_id, int $limit): ?object {
    $q = \Drupal::database()->select('soccerbet_games', 'g');
    $q->fields('g', ['game_id', 'team_id_1', 'team_id_2', 'game_date', 'team1_score', 'team2_score']);
    $q->addField('t1', 'team_name', 'team1_name');
    $q->addField('t1', 'team_flag', 'team1_flag');
    $q->addField('t2', 'team_name', 'team2_name');
    $q->addField('t2', 'team_flag', 'team2_flag');
    $q->join('soccerbet_teams', 't1', 'g.team_id_1 = t1.team_id');
    $q->join('soccerbet_teams', 't2', 'g.team_id_2 = t2.team_id');
    $q->condition('g.tournament_id', $tournament_id);
    $q->condition('g.published', 1);
    $q->isNotNull('g.team1_score');
    $q->condition('g.game_date', gmdate('Y-m-d\TH:i:s'), '<');
    $q->orderBy('g.game_date', 'ASC');
    $q->range($limit - 1, 1);
    $game = $q->execute()->fetchObject();
    if (!$game) {
      return NULL;
    }
    // Alias score1/score2 für Scoreboard-Wiederverwendung im Template.
    $game->score1 = (int) $game->team1_score;
    $game->score2 = (int) $game->team2_score;
    return $game;
  }

  /**
   * Liefert pro Tipper den Tipp für das Step-Spiel sowie die
   * Gesamtpunkte (inkl. Bonus) und einen Status (exact/tendency/wrong).
   *
   * @return array<int, array{tipp: string, status: string, points: int}>
   */
  private function loadStepTipps(object $game, int $tournament_id, int $limit): array {
    $tipper_points = $this->scoring->getTipperPoints($tournament_id, $limit);
    $config        = $this->config('soccerbet.settings');
    $pts_exact     = (int) $config->get('points_exact');
    $pts_tendency  = (int) $config->get('points_tendency');
    $game_id       = (int) $game->game_id;

    $result = [];
    foreach ($tipper_points as $tipper_id => $data) {
      $tipp = $data['tipp'][$game_id] ?? 'N.A.';
      if ($tipp === 'N.A.') {
        continue;
      }
      $base  = (int) ($data['basispunkte'][$game_id]  ?? 0);
      $total = (int) ($data['totalpergame'][$game_id] ?? 0);

      $status = match (TRUE) {
        $base === $pts_exact    => 'exact',
        $base === $pts_tendency => 'tendency',
        default                 => 'wrong',
      };

      $result[(int) $tipper_id] = [
        'tipp'   => str_replace(' : ', ':', $tipp),
        'status' => $status,
        'points' => $total,
      ];
    }
    return $result;
  }

  /**
   * Detail-Ansicht eines einzelnen Tippers.
   */
  public function tipperDetail(int $tournament_id, int $tipper_id): array {
    $tournament_id = (int) $tournament_id;
    $tipper_id     = (int) $tipper_id;

    try {
      $tournament = $this->tournamentManager->load($tournament_id);
    }
    catch (\Exception) {
      return $this->noTournamentMessage();
    }

    $tipper_points = $this->scoring->getTipperPoints($tournament_id);
    $tipper_data   = $tipper_points[$tipper_id] ?? NULL;

    if (!$tipper_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $avatars    = $this->loadAvatarUrls([$tipper_data]);
    $avatar_url = $avatars[$tipper_data['uid']] ?? NULL;
    $stars      = $this->scoring->getStarsForTipper($tipper_id);

    return [
      '#theme'       => 'soccerbet_tipper_detail',
      '#tipper'      => $tipper_data,
      '#tournament'  => $tournament,
      '#avatar_url'  => $avatar_url,
      '#stars'       => $stars,
      '#cache'       => ['max-age' => 60],
    ];
  }

  /**
   * Gibt frühere Turniere derselben Tippergruppen zurück (ohne das aktuelle).
   *
   * @return array<int, object>  Turnier-Objekte mit zusätzlichem `url`-Property
   */
  private function loadPastTournaments(int $tournament_id): array {
    $group_ids = $this->tournamentManager->loadTipperGroupIds($tournament_id);
    if (empty($group_ids)) {
      return [];
    }

    $seen = [];
    $result = [];
    foreach ($group_ids as $grp_id) {
      foreach ($this->tournamentManager->loadAll($grp_id) as $t) {
        $tid = (int) $t->tournament_id;
        if ($tid === $tournament_id || isset($seen[$tid])) {
          continue;
        }
        $seen[$tid] = TRUE;
        $t->url = \Drupal\Core\Url::fromRoute('soccerbet.standings', ['tournament_id' => $tid])->toString();

        $cid = 'soccerbet:past_top3:' . $tid;
        if ($cached = \Drupal::cache()->get($cid)) {
          $t->top3 = $cached->data;
        }
        else {
          $t->top3 = array_slice($this->scoring->getRanking($tid), 0, 3);
          \Drupal::cache()->set($cid, $t->top3, Cache::PERMANENT, ['soccerbet_standings:' . $tid]);
        }
        $result[] = $t;
      }
    }

    // Neueste zuerst (loadAll liefert bereits DESC, aber nach Merge neu sortieren)
    usort($result, fn($a, $b) => strcmp((string) $b->start_date, (string) $a->start_date));
    return $result;
  }

  /**
   * Gibt ein Array [uid => avatar_url_or_null] für die übergebenen Rows zurück.
   *
   * @param array<int, array> $rows  Ranking-Rows mit 'uid'-Key
   * @return array<int, string|null>
   */
  private function loadAvatarUrls(array $rows): array {
    $uids = array_unique(array_filter(array_column($rows, 'uid')));
    if (empty($uids)) {
      return [];
    }
    $users = $this->entityTypeManager()->getStorage('user')->loadMultiple($uids);
    $result = [];
    foreach ($users as $uid => $user) {
      if (!$user->user_picture->isEmpty() && $user->user_picture->entity) {
        $uri = $user->user_picture->entity->getFileUri();
        $result[(int) $uid] = \Drupal::service('file_url_generator')->generateString($uri);
      }
      else {
        $result[(int) $uid] = NULL;
      }
    }
    return $result;
  }

  /**
   * Löst tournament_id = 0 zum konfigurierten Standard-Turnier auf.
   */
  private function resolveTournamentId(int $tournament_id): int {
    if ($tournament_id > 0) {
      return $tournament_id;
    }
    return (int) $this->config('soccerbet.settings')->get('default_tournament');
  }

  /**
   * Gibt eine freundliche Meldung zurück wenn kein Turnier konfiguriert ist.
   */
  private function noTournamentMessage(): array {
    return [
      '#markup' => '<div class="soccerbet-no-tournament">'
        . '<p>' . $this->t(
            'No active tournament configured. Please first <a href=":url">create a tournament and set it as default</a>.',
            [':url' => \Drupal\Core\Url::fromRoute('soccerbet.admin.tournament.create')->toString()]
          )
        . '</p></div>',
    ];
  }

}
