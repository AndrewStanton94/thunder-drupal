<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\AgencyParticipant;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\AgencyParticipant
 *
 * @group vgwort
 */
class AgencyParticipantTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $participant = new AgencyParticipant('ABC', 'AUTHOR');
    $expected_value = <<<JSON
{
    "code": "ABC",
    "involvement": "AUTHOR"
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($participant));
  }

  public function testInvalidInvolvement(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'WRITER' is not a valid involvement.");
    new AgencyParticipant('ABC', 'WRITER');
  }

}
