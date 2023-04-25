<?php

/**
 * @file
 * Post updates for the VG Wort module.
 *
 * Note: never use 'vgwort_post_update_add_node_to_entity_types' as a post
 * update name as it was used temporarily during the 2.0.0 beta cycle.
 */

use Drupal\advancedqueue\Entity\Queue;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Url;

/**
 * Set VG Wort test_mode setting.
 */
function vgwort_post_update_set_test_mode(): void {
  $config = \Drupal::service('config.factory')->getEditable('vgwort.settings');
  $config->set('test_mode', FALSE)->save();
}

/**
 * Install the Advanced Queue module and create the VG Wort queue.
 */
function vgwort_post_update_create_queue(): void {
  \Drupal::service('module_installer')->install(['advancedqueue']);

  $queue = Queue::load('vgwort');
  if (!$queue instanceof Queue) {
    // Read the file from the module's config.
    $config = (new FileStorage(\Drupal::service('extension.list.module')->getPath('vgwort') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY))->read('advancedqueue.advancedqueue_queue.vgwort');
    $queue = Queue::create($config);
    $queue->save();
  }
  $config = \Drupal::service('config.factory')->getEditable('vgwort.settings');
  $config->set('queue_retry_time', 86400)->save();
}

/**
 * Add legal rights to VG Wort settings.
 */
function vgwort_post_update_add_legal_rights_to_config() {
  \Drupal::service('config.factory')->getEditable('vgwort.settings')
    ->set('legal_rights', [
      'distribution' => FALSE,
      'public_access' => FALSE,
      'reproduction' => FALSE,
      'declaration_of_granting' => FALSE,
      'other_public_communication' => FALSE,
    ])
    ->save();
  return t('<a href=":url">Visit the VG Wort settings form</a> to confirm the right of reproduction (§ 16 UrhG), right of distribution (§ 17 UrhG), right of public access (§ 19a UrhG) and the declaration of granting rights.', [':url' => Url::fromRoute('vgwort.settings', [], ['base_url' => ''])->toString()]);
}

/**
 * Change VG Wort queue to daemon/drush processing.
 */
function vgwort_post_update_daemon_queue() {
  Queue::load('vgwort')->setProcessor('daemon')->save();
}
