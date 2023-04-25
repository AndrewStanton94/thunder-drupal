<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\IdentificationCode;
use Drupal\vgwort\Api\PersonParticipant;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\PersonParticipant
 *
 * @group vgwort
 */
class PersonParticipantTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $id = new IdentificationCode('12345678', 'ISNI');
    $id2 = new IdentificationCode('87654321', 'IPI');
    $participant = new PersonParticipant(435236412, 'Octavia', 'Butler', 'AUTHOR', [$id, $id2]);
    $expected_value = <<<JSON
{
    "cardNumber": 435236412,
    "firstName": "Octavia",
    "identificationCodes": [
        {
            "code": "12345678",
            "codeType": "ISNI"
        },
        {
            "code": "87654321",
            "codeType": "IPI"
        }
    ],
    "surName": "Butler",
    "involvement": "AUTHOR"
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($participant));
  }

  public function testNoCardNumber(): void {
    $participant = new PersonParticipant(NULL, 'Octavia', 'Butler', 'AUTHOR');
    $expected_value = <<<JSON
{
    "firstName": "Octavia",
    "surName": "Butler",
    "involvement": "AUTHOR"
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($participant));
  }

  public function testInvalidInvolvement(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'WRITER' is not a valid involvement.");
    new PersonParticipant(435236412, 'Octavia', 'Butler', 'WRITER', []);
  }

  public function testInvalidIdentificationCode(): void {
    $this->expectError();
    new PersonParticipant(435236412, 'Octavia', 'Butler', 'AUTHOR', ['test']);
  }

}
