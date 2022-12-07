<?php

namespace Drupal\soccerbet_tournament\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TournamentCollectionLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * @var EntityTypeManagerInterface $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Creates a TournamentCollectionLink instance.
   *
   * @param $base_plugin_id
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }


  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  public function getDerivativeDefinitions($base_plugin_definition) {

    $menu_tree = \Drupal::menuTree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('tools');
    $tree = $menu_tree->load('tools', $parameters);


    $links[$base_plugin_definition['id']] = [
      'title' => 'Tournaments',
      'route_name' => 'entity.soccerbet_tournament.collection',
    ] +$base_plugin_definition;

    return $links;
  }


}