<?php

/**
 * @file
 * Database to mimic the installation of the VG Wort module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

// Set the schema version.
$connection->merge('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', 'vgwort')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'vgwort',
    'value' => 'i:20002;',
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
$extensions['module']['advancedqueue'] = 0;
$extensions['module']['vgwort'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Install VG Wort settings are they were for the third beta release.
$config = <<<YAML
# Ideally the password would be stored in a config override in settings.php
username: ''
password: ''
# According to VG Wort the prefix is always the same, but it changed in 2022 so
# leaving in configuration. \Drupal\vgwort\Form\SettingsForm does not support
# changing it.
prefix: vgzm
publisher_id: ''
image_domain: ''
registration_wait_days: 14
queue_retry_time: 86400
entity_types:
  node: [ ]
test_mode: false
legal_rights:
  distribution: false
  public_access: false
  reproduction: false
  declaration_of_granting: false
  other_public_communication: false
YAML;

Yaml::decode($config);
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'vgwort.settings',
    'data' => serialize(Yaml::decode($config)),
  ])
  ->execute();

// Install VG Wort queue are they were for the third beta release.
$config = <<<YAML
uuid: 34e0cd0a-8230-41d2-a724-0717ab69996f
langcode: en
status: true
dependencies:
  enforced:
    module:
      - vgwort
id: vgwort
label: 'VG Wort'
backend: database
backend_configuration:
  lease_time: 300
processor: cron
processing_time: 90
locked: false
threshold:
  type: 0
  limit: 0
  state: all
YAML;

Yaml::decode($config);
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'advancedqueue.advancedqueue_queue.vgwort',
    'data' => serialize(Yaml::decode($config)),
  ])
  ->execute();

$entity_type = 'O:42:"Drupal\Core\Config\Entity\ConfigEntityType":43:{s:5:" * id";s:19:"advancedqueue_queue";s:8:" * class";s:33:"Drupal\advancedqueue\Entity\Queue";s:11:" * provider";s:13:"advancedqueue";s:15:" * static_cache";b:0;s:15:" * render_cache";b:1;s:19:" * persistent_cache";b:1;s:14:" * entity_keys";a:8:{s:2:"id";s:2:"id";s:5:"label";s:5:"label";s:4:"uuid";s:4:"uuid";s:8:"revision";s:0:"";s:6:"bundle";s:0:"";s:8:"langcode";s:8:"langcode";s:16:"default_langcode";s:16:"default_langcode";s:29:"revision_translation_affected";s:29:"revision_translation_affected";}s:16:" * originalClass";s:33:"Drupal\advancedqueue\Entity\Queue";s:11:" * handlers";a:5:{s:6:"access";s:46:"Drupal\advancedqueue\QueueAccessControlHandler";s:12:"list_builder";s:37:"Drupal\advancedqueue\QueueListBuilder";s:4:"form";a:3:{s:3:"add";s:35:"Drupal\advancedqueue\Form\QueueForm";s:4:"edit";s:35:"Drupal\advancedqueue\Form\QueueForm";s:6:"delete";s:35:"Drupal\Core\Entity\EntityDeleteForm";}s:14:"route_provider";a:1:{s:7:"default";s:51:"Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider";}s:7:"storage";s:45:"Drupal\Core\Config\Entity\ConfigEntityStorage";}s:19:" * admin_permission";s:24:"administer advancedqueue";s:25:" * permission_granularity";s:11:"entity_type";s:8:" * links";a:4:{s:8:"add-form";s:31:"/admin/config/system/queues/add";s:9:"edit-form";s:56:"/admin/config/system/queues/manage/{advancedqueue_queue}";s:11:"delete-form";s:63:"/admin/config/system/queues/manage/{advancedqueue_queue}/delete";s:10:"collection";s:27:"/admin/config/system/queues";}s:21:" * bundle_entity_type";N;s:12:" * bundle_of";N;s:15:" * bundle_label";N;s:13:" * base_table";N;s:22:" * revision_data_table";N;s:17:" * revision_table";N;s:13:" * data_table";N;s:11:" * internal";b:0;s:15:" * translatable";b:0;s:19:" * show_revision_ui";b:0;s:8:" * label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:5:"Queue";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:19:" * label_collection";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:6:"Queues";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:17:" * label_singular";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:5:"queue";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:15:" * label_plural";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:6:"queues";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:14:" * label_count";a:3:{s:8:"singular";s:12:"@count queue";s:6:"plural";s:13:"@count queues";s:7:"context";N;}s:15:" * uri_callback";N;s:8:" * group";s:13:"configuration";s:14:" * group_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:13:"Configuration";s:12:" * arguments";a:0:{}s:10:" * options";a:1:{s:7:"context";s:17:"Entity type group";}}s:22:" * field_ui_base_route";N;s:26:" * common_reference_target";b:0;s:22:" * list_cache_contexts";a:0:{}s:18:" * list_cache_tags";a:1:{i:0;s:31:"config:advancedqueue_queue_list";}s:14:" * constraints";a:0:{}s:13:" * additional";a:0:{}s:14:" * _serviceIds";a:0:{}s:18:" * _entityStorages";a:0:{}s:20:" * stringTranslation";N;s:16:" * config_prefix";s:19:"advancedqueue_queue";s:14:" * lookup_keys";a:1:{i:0;s:4:"uuid";}s:16:" * config_export";a:8:{i:0;s:2:"id";i:1;s:5:"label";i:2;s:7:"backend";i:3;s:21:"backend_configuration";i:4;s:9:"processor";i:5;s:15:"processing_time";i:6;s:9:"threshold";i:7;s:6:"locked";}s:21:" * mergedConfigExport";a:0:{}}';
$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'entity.definitions.installed',
    'name' => 'advancedqueue_queue.entity_type',
    'value' => $entity_type,
  ])
  ->execute();
