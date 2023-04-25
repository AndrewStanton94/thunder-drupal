<?php

namespace Drupal\vgwort\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the publisher key value and url for vgwort_counter_id field.
 *
 * @see https://tom.vgwort.de/portal/showHelp
 */
class CounterIdFieldItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $config = \Drupal::config('vgwort.settings');
    $prefix = $config->get('prefix');
    $publisher_id = $config->get('publisher_id');
    $domain = $config->get('image_domain');

    if (empty($prefix) || empty($publisher_id) || empty($domain)) {
      // VG wort is not configured there is no possible value.
      return;
    }

    $enabled_for_entity = TRUE;
    \Drupal::moduleHandler()->invokeAllWith('vgwort_enable_for_entity', function (callable $hook) use ($entity, &$enabled_for_entity) {
      // Once an implementation has returned false do not call any other
      // implementation.
      if ($enabled_for_entity) {
        $enabled_for_entity = $hook($entity);
      }
    });

    if (!$enabled_for_entity) {
      // An implementation og hook_vgwort_enable_for_entity() has returned
      // false.
      return;
    }

    // @todo Do we need to use base64 because the UUID contains hyphens?
    // Example: vgzm.970-123456789
    $value = "$prefix.$publisher_id-{$entity->uuid()}";

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->createItem(0, $value);
    // Example: domain.met.vgwort.de/na/vgzm.970-123456789
    // There is no protocol so that formatter or front-end can decide whether to
    // go protocol relative or on the protocol to use.
    $item->set('url', "$domain/na/$value");
    $this->list[0] = $item;
  }

}
