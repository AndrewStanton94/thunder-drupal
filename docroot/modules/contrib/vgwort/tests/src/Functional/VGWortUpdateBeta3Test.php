<?php

namespace Drupal\Tests\vgwort\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Tests the VG Wort module.
 *
 * @group vgwort
 */
class VGWortUpdateBeta3Test extends UpdatePathTestBase {
  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('update.post_update_registry')->registerInvokedUpdates([
      'vgwort_post_update_add_node_to_entity_types',
      'vgwort_post_update_set_test_mode',
      'vgwort_post_update_create_queue',
      'vgwort_post_update_add_legal_rights_to_config',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles[] = $this->root . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz';
    $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install_vgwort_for_update_testing_2.0.0-beta3.php';
  }

  public function testVgWortUpdates(): void {
    // Ensure install_vgwort_for_update_testing.php is working.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('vgwort'));
    $this->assertSame(['node' => []], $this->config('vgwort.settings')->get('entity_types'));

    // Run updates and test them.
    $this->runUpdates();

    $this->assertSame(['node' => ['view_mode' => 'full']], $this->config('vgwort.settings')->get('entity_types'));
    $this->assertSame('daemon', Queue::load('vgwort')->getProcessor());
  }

  public function testVgWortUpdatesEntityFields(): void {
    $settings = Database::getConnection()->select('config')
      ->fields('config', ['data'])
      ->condition('collection', '')
      ->condition('name', 'vgwort.settings')
      ->execute()
      ->fetchField();
    $settings = unserialize($settings, ['allowed_classes' => FALSE]);
    $settings['entity_types']['node'] = ['field_test1', 'field_test2'];
    $settings['entity_types']['taxonomy'] = [];
    Database::getConnection()->update('config')
      ->fields([
        'data' => serialize($settings),
      ])
      ->condition('collection', '')
      ->condition('name', 'vgwort.settings')
      ->execute();

    // Run updates and test them.
    $this->runUpdates();

    $this->assertSame(['node' => ['view_mode' => 'full', 'fields' => ['field_test1', 'field_test2']], 'taxonomy' => ['view_mode' => 'full']], $this->config('vgwort.settings')->get('entity_types'));
  }

}
