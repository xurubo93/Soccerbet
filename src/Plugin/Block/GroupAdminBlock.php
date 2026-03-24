<?php

declare(strict_types=1);

namespace Drupal\soccerbet\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block mit Gruppen-Links für Teilnehmer und Gruppenadmins.
 *
 * @Block(
 *   id = "soccerbet_group_admin",
 *   admin_label = @Translation("Soccerbet: Meine Gruppen"),
 *   category = @Translation("Soccerbet"),
 * )
 */
final class GroupAdminBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly AccountProxyInterface $currentUser,
    private readonly Connection $db,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('database'),
    );
  }

  public function build(): array {
    $uid = (int) $this->currentUser->id();
    if ($uid === 0) {
      return [];
    }

    // Alle Gruppen laden, in denen der User Tipper ist und die eine URL haben
    $q = $this->db->select('soccerbet_tipper_groups', 'g');
    $q->fields('g', ['tipper_grp_id', 'tipper_grp_name', 'group_slug', 'tipper_admin_id']);
    $q->join('soccerbet_tippers', 't', 't.tipper_grp_id = g.tipper_grp_id AND t.uid = :uid', [':uid' => $uid]);
    $q->condition('g.group_slug', '', '<>');
    $q->orderBy('g.tipper_grp_name');
    $groups = $q->execute()->fetchAll();

    if (empty($groups)) {
      return [];
    }

    $items = [];
    foreach ($groups as $group) {
      $slug     = $group->group_slug;
      $is_admin = (int) $group->tipper_admin_id === $uid;

      $links = [[
        '#type'  => 'link',
        '#title' => $this->t('Rangliste'),
        '#url'   => Url::fromRoute('soccerbet.group.page', ['group_slug' => $slug]),
      ]];

      if ($is_admin) {
        $links[] = [
          '#type'  => 'link',
          '#title' => $this->t('Freunde einladen'),
          '#url'   => Url::fromRoute('soccerbet.group.invite', ['group_slug' => $slug]),
        ];
        $links[] = [
          '#type'  => 'link',
          '#title' => $this->t('Turnier zuordnen'),
          '#url'   => Url::fromRoute('soccerbet.group.tournaments', ['group_slug' => $slug]),
        ];
      }

      $items[] = [
        'label'    => $group->tipper_grp_name,
        'is_admin' => $is_admin,
        'links'    => $links,
      ];
    }

    return [
      '#theme'  => 'soccerbet_group_admin_block',
      '#groups' => $items,
      '#cache'  => [
        'contexts' => ['user'],
        'tags'     => ['soccerbet_tipper_groups'],
      ],
    ];
  }

}
