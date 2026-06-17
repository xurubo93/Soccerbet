<?php

namespace Drupal\soccerbet\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for soccerbet.
 */
class SoccerbetHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_help().
     */
    #[Hook('help')]
    public function help(string $route_name, \Drupal\Core\Routing\RouteMatchInterface $route_match): string
    {
        return match ($route_name) {
            'help.page.soccerbet' => '<p>' . $this->t('Fußball-Tippspiel: Verwalte Turniere, Teams, Spiele und Teilnehmer-Ranglisten.') . '</p>',
            default => '',
        };
    }
    /**
     * Implements hook_theme().
     */
    #[Hook('theme')]
    public static function theme(): array
    {
        return [
            'soccerbet_standings' => [
                'variables' => [
                    'rows' => [
                    ],
                    'tournament' => NULL,
                    'played_games' => 0,
                    'past_tournaments' => [
                    ],
                    'step_mode' => FALSE,
                    'step_limit' => 0,
                    'max_games' => 0,
                    'winner_bets' => [
                    ],
                    'step_game' => NULL,
                    'step_tipps' => [
                    ],
                ],
                'template' => 'soccerbet-standings',
            ],
            'soccerbet_place_bets' => [
                'variables' => [
                    'form' => NULL,
                    'games' => [
                    ],
                    'tournament' => NULL,
                ],
                'template' => 'soccerbet-place-bets',
            ],
            'soccerbet_tipper_detail' => [
                'variables' => [
                    'tipper' => NULL,
                    'points' => [
                    ],
                    'tournament' => NULL,
                    'avatar_url' => NULL,
                    'stars' => 0,
                ],
                'template' => 'soccerbet-tipper-detail',
            ],
            'soccerbet_tables' => [
                'variables' => [
                    'groups' => [
                    ],
                    'tournament' => NULL,
                ],
                'template' => 'soccerbet-tables',
            ],
            'soccerbet_live' => [
                'variables' => [
                    'tournament' => NULL,
                    'is_live' => FALSE,
                    'tournament_id' => 0,
                    'scoreboard' => [
                    ],
                    'ranking_content' => [
                    ],
                    'can_refresh' => FALSE,
                ],
                'template' => 'soccerbet-live',
            ],
            'soccerbet_live_scoreboard' => [
                'variables' => [
                    'live_games' => [
                    ],
                ],
                'template' => 'soccerbet-live-scoreboard',
            ],
            'soccerbet_live_ranking' => [
                'variables' => [
                    'live_games' => [
                    ],
                    'ranking' => [
                    ],
                    'final_started' => FALSE,
                    'tournament_id' => 0,
                ],
                'template' => 'soccerbet-live-ranking',
            ],
            'soccerbet_shoutbox' => [
                'variables' => [
                    'messages' => [
                    ],
                    'form' => NULL,
                    'can_delete' => FALSE,
                    'tournament_id' => 0,
                ],
                'template' => 'soccerbet-shoutbox',
            ],
        ];
    }
    /**
     * Implements hook_migrate_import_MIGRATION_ID_finish().
     *
     * Nach der Tournament-Migration: aktives Turnier in soccerbet.settings setzen.
     */
    #[Hook('migrate_import_soccerbet_tournaments_finish')]
    public static function migrateImportSoccerbetTournamentsFinish(string $migration_id, array $context): void
    {
        // Das aktive Turnier (is_active = 1) als default_tournament konfigurieren
        $active_id = \Drupal::database()->select('soccerbet_tournament', 't')->fields('t', [
            'tournament_id',
        ])->condition('t.is_active', 1)->orderBy('t.tournament_id', 'DESC')->range(0, 1)->execute()->fetchField();
        if ($active_id) {
            \Drupal::configFactory()->getEditable('soccerbet.settings')->set('default_tournament', (int) $active_id)->save();
            \Drupal::logger('soccerbet')->info('Standard-Turnier nach Migration auf ID @id gesetzt.', [
                '@id' => $active_id,
            ]);
        }
    }
    /**
     * Implements hook_preprocess_HOOK() for soccerbet_live_scoreboard.
     */
    #[Hook('preprocess_soccerbet_live_scoreboard')]
    public static function preprocessSoccerbetLiveScoreboard(array &$variables): void
    {
        $variables['soccerbet_module_path'] = \Drupal::service('extension.list.module')->getPath('soccerbet');
    }
    /**
     * Implements hook_preprocess_HOOK() for soccerbet_standings.
     */
    #[Hook('preprocess_soccerbet_standings')]
    public static function preprocessSoccerbetStandings(array &$variables): void
    {
        $variables['soccerbet_module_path'] = \Drupal::service('extension.list.module')->getPath('soccerbet');
    }
    /**
     * Implements hook_preprocess_HOOK() for soccerbet_tables.
     */
    #[Hook('preprocess_soccerbet_tables')]
    public static function preprocessSoccerbetTables(array &$variables): void
    {
        $variables['soccerbet_module_path'] = \Drupal::service('extension.list.module')->getPath('soccerbet');
    }
    /**
     * Implements hook_preprocess_HOOK() for soccerbet_tipper_detail.
     */
    #[Hook('preprocess_soccerbet_tipper_detail')]
    public static function preprocessSoccerbetTipperDetail(array &$variables): void
    {
        $variables['soccerbet_module_path'] = \Drupal::service('extension.list.module')->getPath('soccerbet');
    }
}
