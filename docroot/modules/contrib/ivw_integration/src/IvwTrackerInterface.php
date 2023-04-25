<?php

namespace Drupal\ivw_integration;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for the tracking service.
 */
interface IvwTrackerInterface {

  /**
   * Gets all tracking parameters.
   *
   * @return string[]
   *   Array containing 'st', 'mobile_st', 'cp', 'cpm',
   *   'sv', 'mobile_sv' and 'mobile_dn' parameters.
   */
  public function getTrackingInformation(ContentEntityInterface $entity = NULL);

}
