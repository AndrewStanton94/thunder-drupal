<?php

namespace Drupal\vgwort\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * VG Wort participant info constraint.
 *
 * @Constraint(
 *   id = "vgwort_participant_info",
 *   label = @Translation("VG Wort participant info", context = "Validation"),
 *   type = { "vgwort_participant_info" }
 * )
 */
class ParticipantInfoConstraint extends Constraint {

  public $requiredFirstnameMessage = 'The Firstname field is required.';
  public $requiredSurnameMessage = 'The Surname field is required.';
  public $onlyAgencyMessage = 'Firstname, Surname and Card number cannot be provided when an Agency code is used.';

}
