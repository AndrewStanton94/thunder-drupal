<?php

namespace Drupal\xymatic_migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Replace legacy media entities with new ones.
 */
class MigrateService {

  use LoggerChannelTrait;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new MigrateService object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $config_factory->get('xymatic_migrate.settings');
    $this->setLoggerFactory($logger_factory);
  }

  /**
   * Replace legacy media entities with new ones.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The new media entity.
   * @param array $requestBody
   *   The request body.
   *
   * @return bool
   *   TRUE if the legacy media entity was replaced, FALSE otherwise.
   */
  public function migrate(MediaInterface $media, array $requestBody): bool {

    if ($requestBody['type'] !== 'create') {
      return FALSE;
    }

    $legacyMediaType = $this->entityTypeManager->getStorage('media_type')->load($this->config->get('legacy_media_type'));
    $legacyVideoIdJsonPath = $this->config->get('legacy_video_id_jsonpath');
    $legacyId = NestedArray::getValue($requestBody, explode('.', $legacyVideoIdJsonPath), $key_exists);
    if (!$key_exists) {
      $this->getLogger('xymatic_migrate')->error('Could not find legacy video id in request body.');
      return FALSE;
    }

    $entitiesToReplace = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'bundle' => $legacyMediaType->id(),
      $legacyMediaType->getSource()->getSourceFieldDefinition($legacyMediaType)->getName() => $legacyId,
    ]);

    if (empty($entitiesToReplace)) {
      $this->getLogger('xymatic_migrate')->error('Could not find legacy media entity to replace.');
      return FALSE;
    }
    $entityToReplace = reset($entitiesToReplace);

    // Get all entity_reference fields that target the media entity and
    // allow the new and the old media type.
    $query = $this->entityTypeManager->getStorage('field_config')
      ->getQuery()
      ->condition('field_type', [
        'entity_reference',
        'entity_reference_revisions',
      ], 'IN')
      ->condition('settings.handler_settings.target_bundles.' . $media->bundle(), $media->bundle())
      ->condition('settings.handler_settings.target_bundles.' . $legacyMediaType->id(), $legacyMediaType->id());
    $result = $query->execute();

    foreach ($result as $hostFieldName) {
      /** @var \Drupal\field\Entity\FieldConfig $field */
      $field = $this->entityTypeManager->getStorage('field_config')->load($hostFieldName);

      if ($field->getSetting('target_type') !== 'media') {
        continue;
      }

      $hostEntityType = $field->getTargetEntityTypeId();
      $hostFieldName = $field->getName();
      $query = $this->entityTypeManager->getStorage($hostEntityType)->getQuery();
      $hostEntityIds = $query->accessCheck()
        ->condition($hostFieldName . '.target_id', $entityToReplace->id())
        ->execute();

      /** @var \Drupal\Core\Entity\ContentEntityInterface[] $hostEntities */
      $hostEntities = $this->entityTypeManager->getStorage($hostEntityType)->loadMultiple($hostEntityIds);
      foreach ($hostEntities as $entity) {
        foreach ($entity->{$hostFieldName} as $item) {
          if ($item->target_id === $entityToReplace->id()) {
            $item->target_id = $media->id();
          }
        }
        $entity->save();
      }
    }

    return TRUE;
  }

}
