<?php

namespace Drupal\Tests\paragraphs_paste\FunctionalJavascript;

/**
 * Test trait with helper functions.
 */
trait ParagraphsPasteJavascriptTestTrait {

  /**
   * Simulate paste event.
   *
   * @param string $field_name
   *   The original field name.
   * @param string $text
   *   Text to copy.
   */
  public function simulatePasteEvent($field_name, $text) {
    $this->click("[data-paragraphs-paste-target=\"{$field_name}\"]");
    $area_selector = ".ui-dialog .paragraphs-paste-form [name=\"" . $field_name . "_paste_area\"]";
    $this->getSession()->executeScript("document.querySelector('{$area_selector}').value = '{$text}';");
    $this->click(".ui-dialog .paragraphs-paste-form [name=\"" . $field_name . "_paste_submit\"]");
  }

  /**
   * Wait for element to be present.
   *
   * @param string $selector
   *   The CSS selector.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 1000.
   * @param string $message
   *   (Optional) Message to pass to assertJsCondition().
   */
  public function waitForElementPresent($selector, $timeout = 1000, $message = '') {
    $this->assertJsCondition("document.querySelector('{$selector}')", $timeout, $message);
  }

}
