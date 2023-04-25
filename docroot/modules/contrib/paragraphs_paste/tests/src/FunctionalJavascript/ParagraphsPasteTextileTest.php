<?php

namespace Drupal\Tests\paragraphs_paste\FunctionalJavascript;

/**
 * Tests the creation of paragraphs by pasting random data.
 *
 * @group paragraphs_paste
 */
class ParagraphsPasteTextileTest extends ParagraphsPasteJavascriptTestBase {

  /**
   * Test textile markup.
   */
  public function testTextileMarkup() {
    $content_type = 'article';
    $this->loginAsAdmin();

    $text = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.\nAt vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.\n\nAnother paragraph.\n\nh3. Headline\n\nAdditional paragraph.\n\n* unordered list item\n* unordered list item\n\n# ordered list item\n# ordered list item\n';
    $expected = <<<EOF
<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.<br />
At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>

<p>Another paragraph.</p>

<h3>Headline</h3>

<p>Additional paragraph.</p>

<ul>
	<li>unordered list item</li>
	<li>unordered list item</li>
</ul>

<ol>
	<li>ordered list item</li>
	<li>ordered list item</li>
</ol>
EOF;

    $this->drupalGet("node/add/$content_type");
    usleep(50000);

    $this->simulatePasteEvent('field_paragraphs', $text);
    $this->waitForElementPresent('[data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]', 10000, 'Text field in paragraph form should be present.');
    $this->assertEquals(sprintf($expected, $text), $this->getSession()->getPage()->find('xpath', '//textarea[@data-drupal-selector="edit-field-paragraphs-0-subform-field-text-0-value"]')->getValue(), 'Text should be pasted into paragraph subform.');
  }

}
