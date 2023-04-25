<?php

namespace Drupal\vgwort;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\vgwort\Plugin\Field\FieldType\ParticipantInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin class to convert entity fields into participant lists.
 */
abstract class FieldParticipantListBase extends PluginBase implements ParticipantListInterface, ContainerFactoryPluginInterface {

  /**
   * The Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The Entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager')
    );
  }

  /**
   * Gets a list of vgwort_participant_info fields on the entity / bundle.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return string[]
   *   A list of vgwort_participant_info fields on the entity / bundle.
   */
  protected function getParticipantFields(string $entity_type, string $bundle): array {
    $fields = [];
    $map = $this->entityFieldManager->getFieldMapByFieldType('vgwort_participant_info');
    if (!empty($map[$entity_type])) {
      foreach ($map[$entity_type] as $field_name => $info) {
        if (isset($info['bundles'][$bundle])) {
          $fields[] = $field_name;
        }
      }
    }
    return $fields;
  }

  /**
   * Converts vgwort_participant_info fields to a list of participants.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to get VG Wort participants for.
   *
   * @return \Drupal\vgwort\Api\Participant[]
   *   A list of participants.
   */
  protected function doGetParticipants(FieldableEntityInterface $entity): array {
    $participants = [];
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle() ?: $entity_type;
    foreach ($this->getParticipantFields($entity_type, $bundle) as $field_name) {
      foreach ($entity->get($field_name) as $participant) {
        if (!$participant instanceof ParticipantInfo) {
          throw new \LogicException('This plugin can only be used on \'vgwort_participant_info\' fields');
        }
        $participants[] = $participant->toParticipant();
      }
    }
    return $participants;
  }

}
