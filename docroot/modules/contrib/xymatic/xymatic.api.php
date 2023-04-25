<?php

/**
 * @file
 * API documentation for the xymatic module.
 */

use Drupal\media\MediaInterface;

/**
 * Allows modules to react to a webhook.
 *
 * @param \Drupal\media\MediaInterface $media
 *   The newly created media entity of source type 'xymatic'.
 * @param array $requestBody
 *   The request body of the webhook.
 */
function hook_xymatic_webhook(MediaInterface $media, array $requestBody) {
}
