<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Source-Plugin: Teams aus D6/D7.
 *
 * @MigrateSource(
 *   id = "soccerbet_teams",
 *   source_module = "soccerbet"
 * )
 */
final class TeamSource extends SoccerbetSourceBase {

  public function query(): \Drupal\Core\Database\Query\SelectInterface {
    return $this->select('soccerbet_teams', 't')
      ->fields('t', [
        'team_id',
        'tournament_id',
        'team_name',
        'team_flag',
        'team_group',
        'games_played',
        'games_won',
        'games_drawn',
        'games_lost',
        'goals_shot',
        'goals_got',
        'points',
        'c_uid',
        'c_date',
        'mod_uid',
        'mod_date',
      ]);
  }

  public function fields(): array {
    return [
      'team_id'      => 'Primärschlüssel',
      'tournament_id'=> 'Turnier-ID',
      'team_name'    => 'Teamname',
      'team_flag'    => 'Flaggen-Pfad',
      'team_group'   => 'Gruppe (A-Z)',
      'games_played' => 'Gespielte Spiele',
      'games_won'    => 'Siege',
      'games_drawn'  => 'Unentschieden',
      'games_lost'   => 'Niederlagen',
      'goals_shot'   => 'Geschossene Tore',
      'goals_got'    => 'Gegentore',
      'points'       => 'Punkte',
    ];
  }

  public function getIds(): array {
    return [
      'team_id' => ['type' => 'integer', 'alias' => 't'],
    ];
  }

  public function prepareRow(Row $row): bool {
    $row->setSourceProperty('created', $this->datetimeToTimestamp($row->getSourceProperty('c_date')));
    $row->setSourceProperty('changed', $this->datetimeToTimestamp($row->getSourceProperty('mod_date')));
    $row->setSourceProperty('uid', (int) $row->getSourceProperty('c_uid'));

    // Flaggen-Pfad normalisieren:
    // Alte relative Pfade wie "sites/all/modules/soccerbet/images/austria.gif"
    // → neuen Modulpfad setzen
    $flag = (string) $row->getSourceProperty('team_flag');
    if ($flag && !str_starts_with($flag, '/') && !str_starts_with($flag, 'http')) {
      $basename = basename($flag);
      $row->setSourceProperty('team_flag', '/modules/custom/soccerbet/images/' . $basename);
    }

    return parent::prepareRow($row);
  }
}
