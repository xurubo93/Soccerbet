<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service für die Shoutbox.
 */
final class ShoutboxService {

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Lädt die letzten N Nachrichten eines Turniers.
   *
   * @return object[]
   */
  public function loadMessages(int $tournament_id, int $limit = 30): array {
    $q = $this->db->select('soccerbet_shoutbox', 's')
      ->fields('s')
      ->condition('s.tournament_id', $tournament_id)
      ->orderBy('s.created', 'DESC')
      ->range(0, $limit);
    return $q->execute()->fetchAll();
  }

  /**
   * Speichert eine neue Nachricht.
   */
  public function postMessage(int $tournament_id, string $tipper_name, string $message): void {
    $message = trim($message);
    if ($message === '') {
      return;
    }
    // Max. 500 Zeichen
    $message = mb_substr($message, 0, 500);

    $this->db->insert('soccerbet_shoutbox')
      ->fields([
        'uid'           => (int) $this->currentUser->id(),
        'tipper_name'   => mb_substr($tipper_name, 0, 64),
        'message'       => $message,
        'tournament_id' => $tournament_id,
        'created'       => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Löscht eine Nachricht (nur Admin).
   */
  public function deleteMessage(int $shout_id): void {
    $this->db->delete('soccerbet_shoutbox')
      ->condition('shout_id', $shout_id)
      ->execute();
  }
}
