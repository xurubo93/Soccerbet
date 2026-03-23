<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

/**
 * @MigrateDestination(id = "soccerbet_teams_destination")
 */
final class TeamDestination extends SoccerbetDestinationBase {
  protected string $table = 'soccerbet_teams';
  protected array $primaryKey = ['team_id'];
}
