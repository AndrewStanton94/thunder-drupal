<?php

namespace Drupal\Tests\vgwort\Traits;

use Drupal\vgwort\EntityJobMapper;

/**
 * Installs VG Wort correctly in kernel tests.
 */
trait KernelSetupTrait {

  /**
   * Installs VGWort schemas and configuration correctly.
   */
  private function installVgWort(): void {
    $this->installSchema('advancedqueue', ['advancedqueue']);
    $this->installSchema('vgwort', [EntityJobMapper::TABLE]);
    $this->installConfig('vgwort');
    $entity_types = $this->config('vgwort.settings')->get('entity_types');
    if (!$this->container->get('module_handler')->moduleExists('node')) {
      $entity_types = [];
    }
    $this->config('vgwort.settings')
      ->set('publisher_id', 123456)
      ->set('image_domain', 'http://example.com')
      ->set('entity_types', $entity_types)
      ->set('legal_rights', [
        'distribution' => TRUE,
        'public_access' => TRUE,
        'reproduction' => TRUE,
        'declaration_of_granting' => TRUE,
        'other_public_communication' => FALSE,
      ])
      ->save();

    if (array_key_exists('ENTITY_TYPE', (new \ReflectionClass($this))->getConstants())) {
      // In lieu of a specific test for this helper function, use it for test
      // set up.
      _vgwort_add_entity_reference_to_participant_map($this::ENTITY_TYPE, 'user_id');
    }
  }

}
