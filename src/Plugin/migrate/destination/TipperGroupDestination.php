<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\destination;

/**
 * @MigrateDestination(id = "soccerbet_tipper_groups_destination")
 */
final class TipperGroupDestination extends SoccerbetDestinationBase {
  protected string $table = 'soccerbet_tipper_groups';
  protected array $primaryKey = ['tipper_grp_id'];
}
