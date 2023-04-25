<?php

namespace Drupal\vgwort\Plugin\vgwort\ParticipantList;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\vgwort\FieldParticipantListBase;

/**
 * Plugin that uses an entity reference's participant fields to generate a list.
 *
 * @ParticipantList(
 *   id = "entity_reference_field",
 *   admin_label = @Translation("Entity reference field"),
 *   deriver = "Drupal\vgwort\Plugin\vgwort\EntityReferenceFieldDeriver"
 * )
 */
class EntityReferenceField extends FieldParticipantListBase {

  /**
   * {@inheritdoc}
   */
  public function getParticipants(EntityInterface $entity): array {
    $definition = $this->getPluginDefinition();
    if (!$entity->get($definition['field_name'])->isEmpty()) {
      return $this->doGetParticipants($entity->get($definition['field_name'])->entity);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(EntityInterface $entity): bool {
    $definition = $this->getPluginDefinition();
    if ($entity->getEntityTypeId() !== $definition['entity_type']) {
      return FALSE;
    }
    if (!($entity instanceof FieldableEntityInterface && $entity->hasField($definition['field_name']))) {
      return FALSE;
    }

    $reference = $entity->get($definition['field_name'])->entity;
    if ($reference instanceof EntityInterface) {
      $entity_type = $reference->getEntityTypeId();
      $bundle = $reference->bundle() ?: $entity_type;
      return !empty($this->getParticipantFields($entity_type, $bundle));
    }

    return FALSE;
  }

}
