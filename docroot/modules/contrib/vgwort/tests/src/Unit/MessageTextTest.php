<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\MessageText;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\MessageText
 *
 * @group vgwort
 */
class MessageTextTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $text = new MessageText('The <blink>title</blink>', '<strong>The text</strong>');
    $expected_value = <<<JSON
{
    "lyric": false,
    "shorttext": "The title",
    "text": {
        "plainText": "VGhlIHRleHQ="
    }
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($text));

    $text = new MessageText('The title', 'The text 2', 'plainText', TRUE);
    $text->addText('The text 2', 'pdf');
    $expected_value = <<<JSON
{
    "lyric": true,
    "shorttext": "The title",
    "text": {
        "pdf": "VGhlIHRleHQgMg==",
        "plainText": "VGhlIHRleHQgMg=="
    }
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($text));

  }

  public function testInvalidTextType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'nope' is not a valid text type.");
    new MessageText('The title', 'The text', 'nope');
  }

}
