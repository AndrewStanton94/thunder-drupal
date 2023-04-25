<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Assertion\Inspector;

/**
 * A participant in a text. For example the author or publisher.
 */
class PersonParticipant extends Participant {

  /**
   * The participant's card number from VG Wort.
   *
   * @var int|null
   */
  private ?int $cardNumber;

  /**
   * First name (2-40 characters).
   *
   * @var string
   */
  private string $firstName;

  /**
   * Surname (2-255 characters).
   *
   * @var string
   */
  private string $surName;

  /**
   * Participant identification codes.
   *
   * @var \Drupal\vgwort\Api\IdentificationCode[]
   */
  private array $identificationCodes;

  /**
   * @param int|null $cardNumber
   *   The participant's card number from VG Wort.
   * @param string $firstName
   *   First name.
   * @param string $surName
   *   Surname.
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   * @param \Drupal\vgwort\Api\IdentificationCode[] $identificationCodes
   *   (optional) The participant's identification codes.
   */
  public function __construct(?int $cardNumber, string $firstName, string $surName, string $involvement, array $identificationCodes = []) {
    parent::__construct($involvement);
    assert(Inspector::assertAllObjects($identificationCodes, IdentificationCode::class));

    $this->cardNumber = $cardNumber;
    $this->firstName = $firstName;
    $this->identificationCodes = $identificationCodes;
    $this->surName = $surName;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    $values = [];
    if ($this->cardNumber !== NULL) {
      $values['cardNumber'] = $this->cardNumber;
    }
    $values['firstName'] = $this->firstName;
    if (!empty($this->identificationCodes)) {
      $values['identificationCodes'] = $this->identificationCodes;
    }
    $values['surName'] = $this->surName;
    $values += parent::jsonSerialize();
    return $values;
  }

  /**
   * Helper callback for usort().
   */
  public static function personSort(PersonParticipant $a, PersonParticipant $b): int {
    if ($a->surName === $b->surName) {
      if ($a->firstName === $b->firstName) {
        return $a->cardNumber <=> $b->cardNumber;
      }
      return $a->firstName <=> $b->firstName;
    }
    return $a->surName <=> $b->surName;
  }

}
