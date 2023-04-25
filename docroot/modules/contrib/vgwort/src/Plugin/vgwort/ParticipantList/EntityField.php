<?php

namespace Drupal\vgwort\Plugin\vgwort\ParticipantList;

use Drupal\Core\Entity\EntityInterface;
use Drupal\vgwort\FieldParticipantListBase;

/**
 * Plugin that uses the entity's participant fields to generate a list.
 *
 * @ParticipantList(
 *   id = "entity_field",
 *   admin_label = @Translation("Entity field"),
 * )
 */
class EntityField extends FieldParticipantListBase {

  /**
   * {@inheritdoc}
   */
  public function getParticipants(EntityInterface $entity): array {
    return $this->doGetParticipants($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(EntityInterface $entity): bool {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle() ?: $entity_type;
    return !empty($this->getParticipantFields($entity_type, $bundle));
  }

}
