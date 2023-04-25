<?php

namespace Drupal\Tests\paragraphs_paste\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the creation of paragraphs by pasting random data.
 *
 * @group paragraphs_paste
 */
class ParagraphsPasteTest extends ParagraphsPasteJavascriptTestBase {

  /**
   * Test paste functionality.
   */
  public function testPaste() {
    $content_type = 'article';
    $this->loginAsAdmin();
    // Check that paste functionality is working with default config.
    $text = 'Lorem ipsum dolor sit amet.';
    $this->drupalGet("node/add/$content_type");
    usleep(50000);
    $this->assertTrue($this->getSession()->getDriver()->isVisible('//*[@data-paragraphs-paste-target="field_paragraphs"]'), 'Paragraphs Paste should be visible.');

    $this->simulatePasteEvent('field_paragraphs', $text);

    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->assertEquals(sprintf('<p>%s</p>', $text), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');
  }

  /**
   * Test multiline text with video functionality.
   */
  public function testMultilineTextPaste() {
    $content_type = 'article';
    $this->loginAsAdmin();

    // Check that paste functionality is working with default config.
    $text = [
      'Spicy jalapeno bacon ipsum dolor amet short ribs ribeye chislic, turkey shank chuck cupim bacon bresaola.',
      'https://www.youtube.com/watch?v=3pX4iPEPA9A',
      'Picanha porchetta cupim, salami jerky alcatra doner strip steak pork loin short loin pork belly tail ham hock cow shoulder.',
    ];
    $text = implode('\n\n\n', $text);
    $this->drupalGet("node/add/$content_type");
    usleep(50000);

    $this->simulatePasteEvent('field_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->assertEquals(sprintf('<p>%s</p>', "Spicy jalapeno bacon ipsum dolor amet short ribs ribeye chislic, turkey shank chuck cupim bacon bresaola."), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');
    $this->assertEquals("Drupal Rap - Monster (remix) feat. A.Hughes and D.Stagg (1)", $this->getSession()->getPage()->find('xpath', '//input[@data-drupal-selector="edit-field-paragraphs-1-subform-field-video-0-target-id"]')->getValue(), 'Video should be connected to the paragraph subform.');
    $this->assertEquals(sprintf('<p>%s</p>', "Picanha porchetta cupim, salami jerky alcatra doner strip steak pork loin short loin pork belly tail ham hock cow shoulder."), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-2-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');
  }

  /**
   * Test multiline text with oembed last element.
   */
  public function testAnotherMultilineTextPaste() {
    $content_type = 'article';
    $this->drupalLogin($this->rootUser);

    // Trailing newline and oembed as last element.
    $text = '_Takimata eos At_ odio consequat iusto imperdiet Dicunt, abhorreant adipisci. Pro quis ut nec meis brute nunc.\n\n\nhttps://twitter.com/drupalcon/status/1520055603183464450?cxt=HHwWhMC9qY-hqZgqAAAA\n';

    $this->drupalGet("node/add/$content_type");
    usleep(50000);

    $this->simulatePasteEvent('field_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-1-subform-field-twitter-0-target-id"]', 10000, 'Twitter field in paragraph form should be present.');
  }

  /**
   * Verify that the paste area stays after a first paste.
   */
  public function testPastingTwice() {
    $this->testPaste();

    $text = 'Bacon ipsum dolor amet cow picanha andouille strip steak tongue..';
    $this->simulatePasteEvent('field_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-1-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->assertEquals(sprintf('<p>%s</p>', $text), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-1-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');
  }

  /**
   * Test paste functionality with two paste areas in the form.
   */
  public function testPastingInTwoAreas() {
    $content_type = 'article';

    $field_name = 'field_second_paragraphs';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => '-1',
      'settings' => ['target_type' => 'paragraph'],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $content_type,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => NULL],
      ],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
    $display = EntityFormDisplay::load("node.$content_type.default");
    $display->setComponent($field_name, $display->getComponent('field_paragraphs'));
    $display->save();

    $this->loginAsAdmin();

    // Check that paste functionality is working with default config.
    $this->drupalGet("node/add/$content_type");

    $this->assertTrue($this->getSession()->getDriver()->isVisible('//*[@data-paragraphs-paste-target="field_paragraphs"]'), 'Paragraphs Paste area should be visible.');
    $this->assertTrue($this->getSession()->getDriver()->isVisible('//*[@data-paragraphs-paste-target="field_second_paragraphs"]'), 'Second Paragraphs Paste area should be visible.');
    $text = 'Lorem ipsum dolor sit amet.';
    $this->simulatePasteEvent('field_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->assertEquals(sprintf('<p>%s</p>', $text), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');

    $text = 'Bacon ipsum dolor amet cow picanha andouille strip steak tongue..';
    $this->simulatePasteEvent('field_second_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-second-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in second paragraph form should be present.');
    $this->assertEquals(sprintf('<p>%s</p>', $text), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-second-paragraphs-0-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into the second paragraph subform.');
  }

}
