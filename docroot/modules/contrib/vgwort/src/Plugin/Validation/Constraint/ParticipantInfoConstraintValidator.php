<?php

namespace Drupal\vgwort\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the vgwort_participant_info constraint.
 *
 * The possible values for a participant info field are:
 * - firstname + surname
 * - firstname + surname + card number
 * - agency abbreviation.
 */
class ParticipantInfoConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $card_number = $value->card_number;
    $firstname = $value->firstname;
    $surname = $value->surname;
    $agency_abbr = $value->agency_abbr;

    if ($agency_abbr !== NULL && $agency_abbr !== '') {
      if ($card_number !== NULL || $firstname !== NULL || $surname !== NULL) {
        $this->context->buildViolation($constraint->onlyAgencyMessage)->addViolation();
      }
    }
    else {
      if ($firstname === NULL || $firstname === '') {
        $this->context->buildViolation($constraint->requiredFirstnameMessage)->atPath('firstname')->addViolation();
      }
      if ($surname === NULL || $surname === '') {
        $this->context->buildViolation($constraint->requiredSurnameMessage)->atPath('surname')->addViolation();
      }
    }
  }

}
