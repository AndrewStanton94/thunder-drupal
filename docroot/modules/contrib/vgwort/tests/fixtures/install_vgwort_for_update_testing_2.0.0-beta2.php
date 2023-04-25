<?php

/**
 * @file
 * Database to mimic the installation of the VG Wort module.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set the schema version.
$connection->merge('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', 'vgwort')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'vgwort',
    'value' => 'i:8000;',
  ])
  ->execute();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$extensions['module']['vgwort'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Install VG Wort settings are they were for the second beta release.
$settings = [
  'username' => '',
  'password' => '',
  'prefix' => 'vgzm',
  'publisher_id' => '',
  'image_domain' => '',
  'registration_wait_days' => 14,
  'entity_types' => ['node'],
  // Set test mode to TRUE to prove vgwort_post_update_set_test_mode() does not
  // run.
  'test_mode' => TRUE,
];
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'vgwort.settings',
    'data' => serialize($settings),
  ])
  ->execute();
