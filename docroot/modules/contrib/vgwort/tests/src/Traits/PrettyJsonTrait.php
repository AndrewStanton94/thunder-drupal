<?php

namespace Drupal\Tests\vgwort\Traits;

/**
 * Test helper trait.
 */
trait PrettyJsonTrait {

  /**
   * Pretty prints JSON with the same options as Drupal would use.
   *
   * Makes it easier to read tests.
   *
   * @see \Drupal\Component\Serialization\Json::encode
   */
  public static function jsonEncode($variable) {
    // Encode <, >, ', &, and ".
    return json_encode($variable, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
  }

}
