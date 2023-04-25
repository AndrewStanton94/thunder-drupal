<?php

namespace Drupal\xymatic_migrate\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class XymaticMigrateCommands extends DrushCommands {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * XymaticMigrateCommands constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory) {
    parent::__construct();

    $this->settings = $config_factory->get('xymatic_migrate.settings');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * List all media items to migrate.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, etc.
   *
   * @field-labels
   *   id: Id
   *   label: Label
   *   legacy_id: Legacy Id
   *
   * @command xymatic_migrate:legacy_items
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The media items.
   */
  public function legacyItems(array $options = ['format' => 'table']) {
    /** @var \Drupal\media\MediaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('media');
    $items = $storage->getQuery()
      ->accessCheck()
      ->condition('bundle', $this->settings->get('legacy_media_type'))
      ->execute();

    $items = $storage->loadMultiple($items);

    $rows = [];
    /** @var \Drupal\media\MediaInterface $item */
    foreach ($items as $item) {
      $rows[] = [
        'id' => $item->id(),
        'label' => $item->label(),
        'legacy_id' => $item->getSource()->getSourceFieldValue($item),
      ];
    }

    return new RowsOfFields($rows);
  }

}
