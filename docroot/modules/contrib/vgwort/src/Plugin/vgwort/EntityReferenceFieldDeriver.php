<?php

namespace Drupal\vgwort\Plugin\vgwort;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\EntityOwnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives EntityReferenceField participant list plugins.
 *
 * @see \Drupal\vgwort\Plugin\vgwort\ParticipantList\EntityReferenceField
 */
class EntityReferenceFieldDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @param \Drupal\Core\Config\Config $config
   *   VG Wort settings config.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(Config $config, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, LoggerInterface $logger) {
    $this->config = $config;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory')->get('vgwort.settings'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory')->get('vgwort')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // Reset the discovered definitions.
    $this->derivatives = [];
    $entity_reference_fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');
    foreach ($this->config->get('entity_types') as $entity_type_id => $info) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
        if ($owner = $entity_type->getKey('owner')) {
          $this->addDerivative($entity_type, $owner, $base_plugin_definition);
        }
      }
      foreach ($info['fields'] ?? [] as $field_name) {
        if (isset($entity_reference_fields[$entity_type_id][$field_name])) {
          $this->addDerivative($entity_type, $field_name, $base_plugin_definition);
        }
        else {
          $this->logger->warning(
            'The field @entity_type:@field is not an entity reference field and cannot be used to derive a VG Wort EntityReferenceField plugin',
            ['@entity_type' => $entity_type_id, '@field' => $field_name]
          );
        }
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  private function addDerivative(EntityTypeInterface $entityType, string $field_name, array $base_plugin_definition) {
    $id = $entityType->id() . '|' . $field_name;
    $this->derivatives[$id] = $base_plugin_definition;
    $this->derivatives[$id]['admin_label'] = $this->t("Entity reference field @entity_type:@field_name", ['@entity_type' => $entityType->id(), '@field_name' => $field_name]);
    $this->derivatives[$id]['entity_type'] = $entityType->id();
    $this->derivatives[$id]['field_name'] = $field_name;
  }

}
