<?php

namespace Drupal\vgwort\Exception;

abstract class NewMessageException extends \RuntimeException {

  /**
   * The number times to retry.
   *
   * @return int
   *   The number times to retry.
   */
  abstract public function retries() :int;

}
