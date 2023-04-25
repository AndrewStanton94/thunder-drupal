<?php

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests adding counter ID to entity types.
 *
 * @group vgwort
 */
class EntityTypesConfigTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'field', 'entity_test', 'vgwort'];

  /**
   * Tests adding the counter ID field to an entity type.
   */
  public function testEntityTypes() {
    $this->installEntitySchema('entity_test');
    $this->assertArrayNotHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
    $this->config('vgwort.settings')->set('entity_types', ['entity_test' => []])->save();
    $this->assertArrayHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
    $this->config('vgwort.settings')->set('entity_types', [])->save();
    $this->assertArrayNotHasKey('vgwort_counter_id', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('entity_test'));
  }

}
