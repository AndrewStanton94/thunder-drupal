<?php

namespace Drupal\Tests\vgwort\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Tests the VG Wort module with JS.
 *
 * @group vgwort
 */
class VGWortTest extends WebDriverTestBase {
  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vgwort'];

  /**
   * Tests the password changing.
   */
  public function testVgWortPasswordChange(): void {
    // Login as an admin user to set up VG Wort, use the field UI and create
    // content.
    $admin_user = $this->createUser([], 'site admin', TRUE);
    $this->drupalLogin($admin_user);

    // Module settings.
    $this->drupalGet('admin/config');
    $this->clickLink('VG Wort settings');
    $this->assertSession()->fieldValueEquals('username', '');
    $this->assertSession()->fieldValueEquals('password', '');
    $this->assertSession()->fieldValueEquals('publisher_id', '');
    $this->assertSession()->fieldValueEquals('domain', '');
    $this->assertSession()->fieldExists('username')->setValue('aaaBBB');
    $this->assertSession()->fieldExists('password')->setValue('t3st');
    $this->assertSession()->buttonNotExists('Reset');
    $this->assertSession()->fieldExists('publisher_id')->setValue('1234567');
    $this->assertSession()->fieldExists('domain')->setValue('example.com');
    $this->submitForm([], 'Save configuration');

    // Ensure password can be changed.
    $this->assertSession()->fieldDisabled('password');

    $this->assertSession()->buttonExists('Reset')->press();
    // Wait for the button to be removed and the password field to be enabled.
    $this->getSession()->getPage()->waitFor(2, function () {
      return !$this->getSession()->getPage()->findButton('Reset') && !$this->assertSession()->fieldExists('password')->hasAttribute('disabled');
    });
    $this->assertSession()->fieldExists('password')->setValue('t3ster');
    $this->submitForm([], 'Save configuration');
    $this->assertSame('t3ster', $this->config('vgwort.settings')->get('password'));
  }

}
