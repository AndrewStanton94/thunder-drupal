<?php

namespace Drupal\media_expire;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaAccessControlHandler;

/**
 * Defines the access control handler for the media entity type.
 *
 * @see \Drupal\media\Entity\Media
 */
class MediaExpireAccessControlHandler extends MediaAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\Core\Access\AccessResult $accessResult */
    $accessResult = parent::checkAccess($entity, $operation, $account);

    switch ($operation) {
      case 'view':
        if (!$accessResult->isAllowed()) {
          $bundle = $this->entityTypeManager
            ->getStorage('media_type')
            ->load($entity->bundle());

          return AccessResult::allowedIf(
            $account->hasPermission('view media') &&
            $bundle->getThirdPartySetting('media_expire', 'enable_expiring') &&
            $bundle->getThirdPartySetting('media_expire', 'fallback_media')
          );
        }
    }

    return $accessResult;
  }

}
