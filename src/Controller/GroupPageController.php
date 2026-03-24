<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\soccerbet\Service\ScoringService;
use Drupal\soccerbet\Service\TipperManager;
use Drupal\soccerbet\Service\TournamentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Öffentliche Gruppen-Seite + Beitritt via Einladungs-Token.
 */
final class GroupPageController extends ControllerBase {

  public function __construct(
    private readonly TipperManager $tipperManager,
    private readonly TournamentManager $tournamentManager,
    private readonly ScoringService $scoring,
    private readonly Connection $db,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soccerbet.tipper_manager'),
      $container->get('soccerbet.tournament_manager'),
      $container->get('soccerbet.scoring'),
      $container->get('database'),
    );
  }

  /**
   * Öffentliche Gruppen-Seite: Infos + Rangliste + Beitreten-Button.
   */
  public function page(string $group_slug): array {
    $group = $this->tipperManager->loadGroupBySlug($group_slug);
    if (!$group) {
      throw new NotFoundHttpException();
    }

    $grp_id  = (int) $group->tipper_grp_id;
    $uid     = (int) $this->currentUser()->id();
    $max     = (int) $group->max_members;
    $members = $this->tipperManager->loadTippersByGroup($grp_id);
    $count   = count($members);

    // Turnier dieser Gruppe ermitteln (neuestes)
    $tournaments    = $this->tournamentManager->loadAll($grp_id);
    $tournament     = $tournaments[0] ?? NULL;
    $tournament_id  = $tournament ? (int) $tournament->tournament_id : 0;

    // Rangliste (nur wenn Turnier vorhanden)
    $ranking = $tournament_id ? $this->scoring->getRanking($tournament_id) : [];

    // Ist der aktuelle User bereits Mitglied?
    $is_member = FALSE;
    if ($uid > 0) {
      foreach ($members as $m) {
        if ((int) $m->tipper_id && $this->tipperManager->loadTipperByUid($uid, $grp_id)) {
          $is_member = TRUE;
          break;
        }
      }
    }

    $can_join = !$is_member && $count < $max && $uid > 0;
    $is_owner = $uid > 0 && (int) $group->tipper_admin_id === $uid;

    return [
      '#theme'        => 'soccerbet_group_page',
      '#group'        => $group,
      '#tournament'   => $tournament,
      '#ranking'      => $ranking,
      '#is_member'    => $is_member,
      '#is_owner'     => $is_owner,
      '#can_join'     => $can_join,
      '#member_count' => $count,
      '#max_members'  => $max,
      '#cache'        => [
        'max-age'  => 60,
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Beitritt via Einladungs-Token.
   */
  public function join(string $group_slug, string $invite_token, Request $request): RedirectResponse {
    $group = $this->tipperManager->loadGroupBySlug($group_slug);
    if (!$group) {
      throw new NotFoundHttpException();
    }

    $grp_id = (int) $group->tipper_grp_id;
    $uid    = (int) $this->currentUser()->id();
    $now    = \Drupal::time()->getRequestTime();

    // Token validieren
    $invitation = $this->db->select('soccerbet_invitations', 'i')
      ->fields('i')
      ->condition('i.invite_token', $invite_token)
      ->condition('i.tipper_grp_id', $grp_id)
      ->condition('i.used', 0)
      ->condition('i.expires', $now, '>')
      ->execute()->fetchObject();

    if (!$invitation) {
      $this->messenger()->addError($this->t('Ungültiger oder abgelaufener Einladungs-Link.'));
      return new RedirectResponse(
        Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString()
      );
    }

    // Prüfen ob bereits Mitglied
    if ($this->tipperManager->loadTipperByUid($uid, $grp_id)) {
      $this->messenger()->addStatus($this->t('Du bist bereits Mitglied dieser Gruppe.'));
      return new RedirectResponse(
        Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString()
      );
    }

    // Gruppenkapazität prüfen
    $count = count($this->tipperManager->loadTippersByGroup($grp_id));
    if ($count >= (int) $group->max_members) {
      $this->messenger()->addError($this->t('Diese Gruppe ist leider voll.'));
      return new RedirectResponse(
        Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString()
      );
    }

    // Tipper anlegen + Turnier-Zuordnung
    $user        = $this->entityTypeManager()->getStorage('user')->load($uid);
    $tipper_name = $user->getDisplayName();
    $tipper_id   = $this->tipperManager->createTipper($uid, $grp_id, $tipper_name);

    $tournaments = $this->tournamentManager->loadAll($grp_id);
    if ($tournaments) {
      $tid = (int) $tournaments[0]->tournament_id;
      $this->tournamentManager->addTipper($tid, $tipper_id);
    }

    // Token als verwendet markieren
    $this->db->update('soccerbet_invitations')
      ->fields(['used' => 1])
      ->condition('invite_token', $invite_token)
      ->execute();

    $this->messenger()->addStatus($this->t(
      'Willkommen bei „@group"! Du kannst jetzt Tipps abgeben.',
      ['@group' => $group->tipper_grp_name]
    ));

    return new RedirectResponse(
      Url::fromRoute('soccerbet.group.page', ['group_slug' => $group_slug])->toString()
    );
  }
}
