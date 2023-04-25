<?php

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\vgwort\Traits\KernelSetupTrait;
use Drupal\user\Entity\User;

/**
 * Creates the necessary entities for testing VG Wort with entity_test entities.
 *
 * This ensures the module is decoupled from nodes.
 *
 * @group vgwort
 */
abstract class VgWortKernelTestBase extends KernelTestBase {
  use KernelSetupTrait;

  protected const ENTITY_TYPE = 'entity_test';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'field', 'entity_test', 'advancedqueue', 'vgwort'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installVgWort();
    $this->installEntitySchema('user');
    User::create(['name' => 'User 1', 'status' => TRUE])->save();

    $this->installEntitySchema(static::ENTITY_TYPE);

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'vgwort_test',
      'entity_type' => static::ENTITY_TYPE,
      'type' => 'vgwort_participant_info',
      'cardinality' => 4,
    ]);
    $fieldStorage->save();
    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => static::ENTITY_TYPE,
    ]);
    $field->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'vgwort_test2',
      'entity_type' => static::ENTITY_TYPE,
      'type' => 'vgwort_participant_info',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();
    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => static::ENTITY_TYPE,
      'settings' => [
        'involvement' => 'TRANSLATOR',
      ],
    ]);
    $field->save();

    // Create fields and displays for the test entity.
    FieldStorageConfig::create([
      'field_name' => 'text',
      'entity_type' => static::ENTITY_TYPE,
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'text',
      'entity_type' => static::ENTITY_TYPE,
      'bundle' => static::ENTITY_TYPE,
      'label' => 'Text',
    ])->save();

    EntityViewMode::create([
      'id' => static::ENTITY_TYPE . '.full',
      'targetEntityType' => static::ENTITY_TYPE,
      'status' => FALSE,
      'enabled' => TRUE,
      'label' => 'Full',
    ])->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => static::ENTITY_TYPE,
      'bundle' => static::ENTITY_TYPE,
      'mode' => 'full',
      'label' => 'My view mode',
      'status' => TRUE,
    ])
      ->setComponent('text', [
        'type' => 'string',
        'region' => 'content',
      ]);
    $display->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'vgwort_test',
      'entity_type' => 'user',
      'type' => 'vgwort_participant_info',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();
    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'user',
    ]);
    $field->save();
  }

}
