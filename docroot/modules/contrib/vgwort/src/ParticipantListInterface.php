<?php

namespace Drupal\vgwort;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface defining participant list plugins.
 *
 * @see \Drupal\vgwort\ParticipantListManager
 */
interface ParticipantListInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets a list of participants from a node using the plugin.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node to get the list of participants of.
   *
   * @return \Drupal\vgwort\Api\Participant[]
   *   A list of participants.
   */
  public function getParticipants(EntityInterface $entity): array;

  /**
   * Checks whether the plugin can work on the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the plugin can fetch participants from this entity.
   */
  public function isApplicable(EntityInterface $entity): bool;

}
