<?php

namespace Drupal\vgwort;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\vgwort\Api\Participant;

/**
 * @defgroup vgwort_api VG Wort API
 * @{
 * Information about the classes and interfaces that make up the VG Wort API.
 *
 * VG Wort participant plugins allow a plugin to determine a list of
 * participants to send to VG Wort for a node.
 *
 * To define a VG Wort Participant list plugin in a module you need to:
 * - Define a VG Wort Participant list plugin by creating a new class that
 *   implements the \Drupal\vgwort\ParticipantListInterface, in namespace
 *   Plugin\vgwort\ParticipantList under your module namespace. For more
 *   information about creating plugins, see the @link plugin_api Plugin API topic. @endlink
 * - VG Wort participant list plugins use the annotations defined by
 *  \Drupal\vgwort\Annotation\ParticipantList. See the
 * @link annotation Annotations topic @endlink for more information about
 *   annotations.
 * @}
 */
class ParticipantListManager extends DefaultPluginManager {

  /**
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    assert($namespaces instanceof \ArrayAccess, '$namespaces can be accessed like an array');

    parent::__construct('Plugin/vgwort/ParticipantList', $namespaces, $module_handler, 'Drupal\vgwort\ParticipantListInterface', 'Drupal\vgwort\Annotation\ParticipantList');

    $this->alterInfo('vgwort_participant_list');
    $this->setCacheBackend($cache_backend, 'vgwort_participant_list');
  }

  /**
   * Gets a list of participants based on the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get participants for.
   *
   * @return \Drupal\vgwort\Api\Participant[]
   *   The sorted list of participants.
   */
  public function getParticipants(EntityInterface $entity): array {
    $participants = [];
    foreach (array_keys($this->getDefinitions()) as $plugin_id) {
      /** @var \Drupal\vgwort\ParticipantListInterface $plugin */
      $plugin = $this->createInstance($plugin_id);
      if ($plugin->isApplicable($entity)) {
        $participants = array_merge($participants, $plugin->getParticipants($entity));
      }
    }

    $participants = array_unique($participants, SORT_REGULAR);
    usort($participants, [Participant::class, 'sort']);
    return $participants;
  }

}
