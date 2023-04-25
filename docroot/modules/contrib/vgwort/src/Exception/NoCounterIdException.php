<?php

namespace Drupal\vgwort\Exception;

class NoCounterIdException extends NewMessageException {

  /**
   * {@inheritdoc}
   */
  public function retries(): int {
    return 0;
  }

}
