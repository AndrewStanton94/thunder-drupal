<?php

namespace Drupal\vgwort\Api;

class AgencyParticipant extends Participant {

  /**
   * The content agency's abbreviation. 2-4 characters.
   *
   * @var string
   */
  private string $code;

  /**
   * @param string $code
   *   The agency's abbreviation. 2-4 characters.
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   */
  public function __construct(string $code, string $involvement) {
    parent::__construct($involvement);
    $this->code = $code;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'code' => $this->code,
    ] + parent::jsonSerialize();
  }

  /**
   * Helper callback for usort().
   */
  public static function agencySort(AgencyParticipant $a, AgencyParticipant $b): int {
    return $a->code <=> $b->code;
  }

}
