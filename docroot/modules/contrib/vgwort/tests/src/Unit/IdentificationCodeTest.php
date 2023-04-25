<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\IdentificationCode;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\IdentificationCode
 *
 * @group vgwort
 */
class IdentificationCodeTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $id = new IdentificationCode('12345678', 'ISNI');
    $expected_value = <<<JSON
{
    "code": "12345678",
    "codeType": "ISNI"
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($id));
  }

  public function testInvalidCodeType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'NOPE' is not a valid code type.");
    new IdentificationCode('12345678', 'NOPE');
  }

}
