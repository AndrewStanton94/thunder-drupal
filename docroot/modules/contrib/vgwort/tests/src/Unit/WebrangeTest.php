<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\Webrange;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\Webrange
 *
 * @group vgwort
 */
class WebrangeTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $webrange = new Webrange(['http://example.com/node/1', 'http://example.com/node/2']);
    $expected_value = <<<JSON
{
    "urls": [
        "http:\/\/example.com\/node\/1",
        "http:\/\/example.com\/node\/2"
    ]
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($webrange));
  }

  public function testInternalUrl(): void {
    $this->expectError();
    new Webrange(['http://example.com/node/1', 'node/2']);
  }

  public function testInvalidUrl(): void {
    $this->expectError();
    new Webrange(['http://example.com/node/1', 'http://ex^ample.com/node/1']);
  }

}
