<?php

namespace Drupal\Tests\paragraphs_paste\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that Paragraphs paste config test.
 *
 * @group paragraphs_paste
 */
class ParagraphsPasteConfigTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['paragraphs_paste_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that paragraphs_paste can be installed.
   */
  public function testInstall() {}

}
