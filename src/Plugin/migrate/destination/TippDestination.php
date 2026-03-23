<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

/**
 * @MigrateDestination(id = "soccerbet_tipps_destination")
 */
final class TippDestination extends SoccerbetDestinationBase {
  protected string $table = 'soccerbet_tipps';
  protected array $primaryKey = ['tipp_id'];
}
