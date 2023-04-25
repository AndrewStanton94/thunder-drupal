<?php

namespace Drupal\media_expire\Commands;

use Drush\Commands\DrushCommands;
use Drupal\media_expire\MediaExpireService;

/**
 * Defines Drush commands for the media_expire module.
 */
class MediaExpireCommands extends DrushCommands {

  /**
   * The media expire service.
   *
   * @var \Drupal\media_expire\MediaExpireService
   */
  protected $mediaExpireService;

  /**
   * MediaExpireCommands constructor.
   *
   * @param \Drupal\media_expire\MediaExpireService $media_expire_service
   *   The media expire service.
   */
  public function __construct(MediaExpireService $media_expire_service) {
    parent::__construct();
    $this->mediaExpireService = $media_expire_service;
  }

  /**
   * Checks for expired media.
   *
   * @command media:expire-check
   * @aliases mec,media-expire-check
   */
  public function expireCheck() {
    $this->mediaExpireService->unpublishExpiredMedia();
  }

}
