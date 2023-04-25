<?php

namespace Drupal\vgwort\Api;

/**
 * A participant in a text. For example the author or publisher.
 */
abstract class Participant implements \JsonSerializable {

  public const AUTHOR = 'AUTHOR';

  public const TRANSLATOR = 'TRANSLATOR';

  public const PUBLISHER = 'PUBLISHER';

  /**
   * Type of participant AUTHOR, TRANSLATOR, PUBLISHER.
   *
   * @var string
   */
  private string $involvement;

  /**
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   */
  public function __construct(string $involvement) {
    if (!in_array($involvement, [static::AUTHOR, static::PUBLISHER, static::TRANSLATOR], TRUE)) {
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid involvement.", $involvement));
    }
    $this->involvement = $involvement;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'involvement' => $this->involvement,
    ];
  }

  /**
   * Helper callback for uasort() to sort participants.
   */
  public static function sort(Participant $a, Participant $b): int {
    if ($a instanceof AgencyParticipant && $b instanceof AgencyParticipant) {
      $comp = AgencyParticipant::agencySort($a, $b);
    }
    if ($a instanceof PersonParticipant && $b instanceof PersonParticipant) {
      $comp = PersonParticipant::personSort($a, $b);
    }
    if (isset($comp)) {
      if ($comp === 0) {
        return $a->involvement <=> $b->involvement;
      }
      return $comp;
    }
    // Person participants before agency participants.
    return $b instanceof PersonParticipant ? 1 : -1;
  }

}
