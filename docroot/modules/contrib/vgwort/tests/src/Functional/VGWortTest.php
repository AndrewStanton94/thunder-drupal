<?php

namespace Drupal\Tests\vgwort\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Tests the VG Wort module.
 *
 * @group vgwort
 */
class VGWortTest extends BrowserTestBase {
  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'taxonomy', 'vgwort', 'field_ui', 'page_cache'];

  /**
   * @see \Drupal\vgwort\Form\SettingsForm::buildForm()
   */
  private const TEST_MODE_MESSAGE = 'The test mode is enabled. The 1x1 pixel will be added as HTML comment to the selected entity_types.';

  /**
   * @see \Drupal\vgwort\Form\SettingsForm::buildForm()
   */
  public const LEGAL_MESSAGE = 'The right of reproduction (§ 16 UrhG), right of distribution (§ 17 UrhG), right of public access (§ 19a UrhG) and the declaration of granting rights must be confirmed.';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'article']);
    $this->enablePageCaching();
  }

  /**
   * Tests the normal operation of the module.
   */
  public function testVgWortHappyPath(): void {
    // Login as an admin user to set up VG Wort, use the field UI and create
    // content.
    $admin_user = $this->createUser([], 'site admin', TRUE);
    $this->drupalLogin($admin_user);

    // Module settings.
    $this->drupalGet('admin/config');
    $this->clickLink('VG Wort settings');
    $this->assertSession()->pageTextContains(self::LEGAL_MESSAGE);
    $this->assertSession()->pageTextNotContains(self::TEST_MODE_MESSAGE);
    $this->assertSession()->fieldValueEquals('username', '');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSession()->fieldValueEquals('publisher_id', '');
    $this->assertSession()->fieldValueEquals('domain', '');
    $this->assertSession()->fieldExists('username')->setValue('aaaBBB');
    $this->assertSession()->fieldExists('password')->setValue('t3st');
    $this->assertSession()->fieldExists('publisher_id')->setValue('1234567');
    $this->assertSession()->fieldExists('domain')->setValue('example.com');
    // Ensure only publishable entity types with canonical links are listed.
    $options = $this->assertSession()->elementExists('css', '#edit-entity-types')->findAll('css', 'input');
    $this->assertCount(2, $options);
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[node][enabled]')->isChecked());
    $this->assertFalse($this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->isChecked());
    $this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->check();
    $this->assertSession()->fieldExists('entity_types[node][view_mode]')->selectOption('search_index');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    // The legal fields cause a message to be set.
    $this->assertSession()->pageTextContains(self::LEGAL_MESSAGE);
    $this->assertSession()->fieldExists('legal_rights[distribution]')->check();
    $this->assertSession()->fieldExists('legal_rights[public_access]')->check();
    $this->assertSession()->fieldExists('legal_rights[reproduction]')->check();
    $this->assertSession()->fieldExists('legal_rights[declaration_of_granting]')->check();
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextNotContains(self::LEGAL_MESSAGE);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('username', 'aaaBBB');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSame('t3st', $this->config('vgwort.settings')->get('password'));
    $this->assertSession()->fieldValueEquals('publisher_id', '1234567');
    $this->assertSession()->fieldValueEquals('domain', 'example.com');
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[node][enabled]')->isChecked());
    $this->assertTrue($this->assertSession()->fieldExists('entity_types[taxonomy_term][enabled]')->isChecked());
    $this->assertSame('search_index', $this->assertSession()->fieldExists('entity_types[node][view_mode]')->getValue());

    // Ensure saving without checking the "delete password" field does not change
    // the password.
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSame('t3st', $this->config('vgwort.settings')->get('password'));
    // Ensure password can be changed.
    $this->assertSession()->buttonExists('Reset')->press();
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSession()->fieldExists('password')->setValue('t3ster');
    $this->submitForm([], 'Save configuration');
    $this->assertSame('t3ster', $this->config('vgwort.settings')->get('password'));

    // Test counter domain and publisher ID validation.
    $this->assertSession()->fieldExists('publisher_id')->setValue('12345678');
    $this->assertSession()->fieldExists('domain')->setValue('http://example.com');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Publisher ID (Karteinummer) cannot be longer than 7 characters but is currently 8 characters long.');
    $this->assertSession()->pageTextContains('Counter domain must be a valid counter domain. For example, vg07.met.vgwort.de.');
    $this->assertSession()->fieldExists('publisher_id')->setValue('a test');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Publisher ID (Karteinummer) must be a numeric ID between 10 and 9999999.');

    // Field UI.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->fieldValueEquals('fields[vgwort_counter_id][region]', 'hidden');
    $this->assertSession()->fieldExists('fields[vgwort_counter_id][region]')->setValue('content');
    $this->submitForm([], 'Save');

    // Create a node and ensure the image is displayed as expected.
    $this->drupalGet('node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Test node');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('node/1');
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('\\\\example.com/na/vgzm.1234567-', '#') . '#', $src);
    preg_match('#^' . preg_quote('\\\\example.com/na/vgzm.1234567-', '#') . '(.*)$#', $src, $matches);
    $node = $this->drupalGetNodeByTitle('Test node');
    $this->assertSame($matches[1], $node->uuid());
    $this->assertSame('1', $node->id());
    $this->assertSame('Test node', $node->label());
    $this->assertSame('vgzm.1234567-' . $matches[1], $node->vgwort_counter_id->value);

    $this->drupalLogout();

    // Ensure that loading a node from the cache still propagates the cache tag
    // added by vgwort_node_storage_load().
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertContains('config:vgwort.settings', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    // Change the settings and ensure the node is cleared from cache.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/vgwort');
    $this->assertSession()->fieldExists('publisher_id')->setValue('8765432');
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalGet('node/1');
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('\\\\example.com/na/vgzm.8765432-', '#') . '#', $src);

    // Ensure the page cache has been cleared and is still cached correctly.
    $this->drupalLogout();
    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $element = $this->assertSession()->elementExists('css', 'main img');
    $src = $element->getAttribute('src');
    $this->assertMatchesRegularExpression('#^' . preg_quote('\\\\example.com/na/vgzm.8765432-', '#') . '#', $src);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
    $this->assertContains('config:vgwort.settings', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));

    // Test the test mode.
    $settings['config']['vgwort.settings']['test_mode'] = (object) [
      'value' => TRUE,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildAll();
    $this->drupalGet('node/1');
    $this->assertSession()->elementNotExists('css', 'main img');
    $this->assertSession()->responseContains('<!-- <img src="\\\\example.com/na/vgzm.8765432-');
    $this->assertSession()->pageTextNotContains('<!-- <img src="\\\\example.com/na/vgzm.8765432-');
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config');
    $this->clickLink('VG Wort settings');
    $this->assertSession()->pageTextContains(self::TEST_MODE_MESSAGE);

    // Ensure the module can be uninstalled without everything breaking.
    $this->drupalGet('admin/modules/uninstall');
    $this->assertSession()->fieldExists('uninstall[vgwort]')->check();
    $this->submitForm([], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('uninstall[vgwort]');
    $this->drupalLogout();

    $this->drupalGet('node/1', ['query' => ['cache-breaking-string']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'main img');

    // Install the module again to ensure it can be.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/modules');
    $this->assertSession()->fieldExists('modules[vgwort][enable]')->check();
    $this->submitForm([], 'Install');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Module VG Wort has been enabled.');
  }

}
