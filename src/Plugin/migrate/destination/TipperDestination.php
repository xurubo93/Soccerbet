<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

/**
 * @MigrateDestination(id = "soccerbet_tippers_destination")
 */
final class TipperDestination extends SoccerbetDestinationBase {
  protected string $table = 'soccerbet_tippers';
  protected array $primaryKey = ['tipper_id'];
}
