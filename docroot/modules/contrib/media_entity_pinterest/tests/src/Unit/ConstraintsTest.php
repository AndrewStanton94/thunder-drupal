<?php

namespace Drupal\Tests\media_entity_pinterest\Unit;

use Drupal\Core\Field\Plugin\Field\FieldType\StringLongItem;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\media_entity_pinterest\Plugin\Validation\Constraint\PinEmbedCodeConstraint;
use Drupal\media_entity_pinterest\Plugin\Validation\Constraint\PinEmbedCodeConstraintValidator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests media_entity_pinterest constrains.
 *
 * @group media
 * @group media_entity_pinterest
 */
class ConstraintsTest extends UnitTestCase {

  /**
   * Tests PinEmbedCode constraints.
   *
   * @covers \Drupal\media_entity_pinterest\Plugin\Validation\Constraint\PinEmbedCodeConstraint
   * @covers \Drupal\media_entity_pinterest\Plugin\Validation\Constraint\PinEmbedCodeConstraintValidator
   *
   * @dataProvider embedCodeProvider
   */
  public function testPinEmbedCodeConstraint($embed_code, $expected_violation_count) {
    // Check message in constraint.
    $constraint = new PinEmbedCodeConstraint();
    $this->assertEquals(addslashes('Not valid Pin URL/embed code.'), addslashes($constraint->message), 'Correct constraint message found.');

    $execution_context = $this->getMockBuilder('\Drupal\Core\TypedData\Validation\ExecutionContext')
      ->disableOriginalConstructor()
      ->getMock();

    if ($expected_violation_count) {
      $execution_context->expects($this->exactly($expected_violation_count))
        ->method('addViolation')
        ->with($constraint->message);
    }
    else {
      $execution_context->expects($this->exactly($expected_violation_count))
        ->method('addViolation');
    }

    $validator = new PinEmbedCodeConstraintValidator();
    $validator->initialize($execution_context);

    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);

    $data = new StringLongItem($definition);
    $data->set('value', $embed_code);
    $validator->validate($data, $constraint);
  }

  /**
   * Provides test data for testPinEmbedCodeConstraint().
   */
  public function embedCodeProvider() {
    return [
      'valid Pin URL' => ['https://www.pinterest.com/pin/15199717473573848/', 0],
      'valid Board URL' => ['https://www.pinterest.com/heathceramics/behind-the-scenes/', 0],
      'valid Board Section URL' => ['https://www.pinterest.com/heathceramics/favorite-places-and-spaces/a-cabin-by-heath/', 0],
      'valid user URL' => ['https://www.pinterest.com/heathceramics/', 0],
    ];
  }

}
