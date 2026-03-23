<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\soccerbet\Service\ScoringService;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ranglisten-Seiten.
 */
final class StandingsController extends ControllerBase {

  public function __construct(
    private readonly ScoringService $scoring,
    private readonly TournamentManager $tournamentManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.scoring'),
      $container->get('soccerbet.tournament_manager'),
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

    return [
      '#theme'        => 'soccerbet_standings',
      '#rows'         => $rows,
      '#tournament'   => $tournament,
      '#played_games' => $played_games,
      '#cache'        => [
        'tags'    => ['soccerbet_standings:' . $tournament_id],
        'max-age' => 60,
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

    return [
      '#theme'        => 'soccerbet_standings',
      '#rows'         => $rows,
      '#tournament'   => $tournament,
      '#played_games' => $limit,
      '#step_mode'    => TRUE,
      '#step_limit'   => $limit,
      '#max_games'    => $max_games,
      '#cache'        => ['max-age' => 300],
    ];
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

    return [
      '#theme'      => 'soccerbet_tipper_detail',
      '#tipper'     => $tipper_data,
      '#tournament' => $tournament,
      '#cache'      => ['max-age' => 60],
    ];
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
            'Kein aktives Turnier konfiguriert. Bitte zuerst ein <a href=":url">Turnier anlegen und als Standard setzen</a>.',
            [':url' => \Drupal\Core\Url::fromRoute('soccerbet.admin.tournament.create')->toString()]
          )
        . '</p></div>',
    ];
  }

}
