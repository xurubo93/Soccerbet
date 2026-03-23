<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

/**
 * @MigrateDestination(id = "soccerbet_games_destination")
 */
final class GameDestination extends SoccerbetDestinationBase {
  protected string $table = 'soccerbet_games';
  protected array $primaryKey = ['game_id'];
}
