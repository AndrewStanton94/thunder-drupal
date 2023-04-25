<?php

/**
 * @file
 * Provide views data for search_api_mark_outdated.module.
 */

use Drupal\search_api\Entity\Index;

/**
 * Implements hook_views_data_alter().
 */
function search_api_mark_outdated_views_data_alter(array &$data): void {

  /** @var \Drupal\search_api\IndexInterface $index */
  foreach (Index::loadMultiple() as $index) {
    $key = 'search_api_index_' . $index->id();
    $data[$key]['search_api_mark_outdated_state_field'] = [
      'title' => t('Search API mark outdated field'),
      'field' => [
        'id' => 'search_api_mark_outdated_state_field',
        'real field' => 'id',
      ],
    ];
  }

}
