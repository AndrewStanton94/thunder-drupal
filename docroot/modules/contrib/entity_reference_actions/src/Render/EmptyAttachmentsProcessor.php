<?php

namespace Drupal\entity_reference_actions\Render;

use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\AttachmentsInterface;

class EmptyAttachmentsProcessor implements AttachmentsResponseProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    return $response;
  }

}
