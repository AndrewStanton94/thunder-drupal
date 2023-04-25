<?php

namespace Drupal\Tests\vgwort\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\vgwort\EntityJobMapper;

/**
 * Tests the VG Wort module.
 *
 * @group vgwort
 */
class VGWortUpdateTest extends UpdatePathTestBase {
  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles[] = $this->root . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz';
    $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/install_vgwort_for_update_testing.php';
  }

  /**
   * Tests update functions.
   */
  public function testVgWortUpdates(): void {
    // Ensure install_vgwort_for_update_testing.php is working.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('vgwort'));
    $this->assertEmpty($this->config('vgwort.settings')->get('entity_types'));
    $this->assertNull($this->config('vgwort.settings')->get('test_mode'));
    $this->assertNull($this->config('vgwort.settings')->get('queue_retry_time'));
    $this->assertSame('vgzm', $this->config('vgwort.settings')->get('prefix'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('advancedqueue'));
    $this->assertFalse(\Drupal::database()->schema()->tableExists(EntityJobMapper::TABLE));

    // Run updates and test them.
    $this->runUpdates();
    $this->assertSame(['node' => ['view_mode' => 'full']], $this->config('vgwort.settings')->get('entity_types'));
    $this->assertFalse($this->config('vgwort.settings')->get('test_mode'));
    $this->assertSame(86400, $this->config('vgwort.settings')->get('queue_retry_time'));
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('advancedqueue'));
    $queue = Queue::load('vgwort');
    $this->assertInstanceOf(Queue::class, $queue);
    $this->assertSame('VG Wort', $queue->label());
    $this->assertTrue(\Drupal::database()->schema()->tableExists(EntityJobMapper::TABLE));
    $expected_legal_rights = [
      'distribution' => FALSE,
      'public_access' => FALSE,
      'reproduction' => FALSE,
      'declaration_of_granting' => FALSE,
      'other_public_communication' => FALSE,
    ];
    $this->assertSame($expected_legal_rights, $this->config('vgwort.settings')->get('legal_rights'));
    $this->assertSession()->linkExists('Visit the VG Wort settings form');
    $links = $this->getSession()->getPage()->findAll('named', ['link', 'Visit the VG Wort settings form']);
    $url = $links[0]->getAttribute('href');
    // Login as we run the test using update free access in settings.php.
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(VGWortTest::LEGAL_MESSAGE);
  }

}
