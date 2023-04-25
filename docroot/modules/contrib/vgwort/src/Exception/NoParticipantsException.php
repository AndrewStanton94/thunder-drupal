<?php

namespace Drupal\vgwort\Exception;

class NoParticipantsException extends NewMessageException {

  /**
   * {@inheritdoc}
   */
  public function retries(): int {
    return 6;
  }

}
