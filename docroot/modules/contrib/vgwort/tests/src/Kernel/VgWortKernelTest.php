<?php

namespace Drupal\Tests\vgwort\Kernel;

/**
 * Tests the vgwort participant field validation.
 *
 * @group vgwort
 */
class VgWortKernelTest extends VgWortKernelTestBase {

  public function testValidation() {
    $entity_type = 'entity_test';
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'Jones',
          'agency_abbr' => 'BBC',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('Firstname, Surname and Card number cannot be provided when an Agency code is used.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'B',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('This value is too short. It should have <em class="placeholder">2</em> characters or more.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.firstname', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => str_repeat('B', 41),
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('This value is too long. It should have <em class="placeholder">40</em> characters or less.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.firstname', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'surname' => 'Jones',
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('The Firstname field is required.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.firstname', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => 'J',
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('This value is too short. It should have <em class="placeholder">2</em> characters or more.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.surname', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'surname' => str_repeat('J', 256),
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('This value is too long. It should have <em class="placeholder">255</em> characters or less.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.surname', $violations->get(0)->getPropertyPath());

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create([
        'vgwort_test' => [
          'card_number' => '123123123',
          'firstname' => 'Bob',
          'agency_abbr' => '',
        ],
      ]);
    $violations = $entity->validate();
    $this->assertSame('The Surname field is required.', (string) $violations->get(0)->getMessage());
    $this->assertSame('vgwort_test.0.surname', $violations->get(0)->getPropertyPath());
  }

  public function testVgwortEnableForEntityHook() {
    $entity_storage = $this->container->get('entity_type.manager')->getStorage(static::ENTITY_TYPE);
    /** @var \Drupal\entity_test\Entity\EntityTestRevPub $entity */
    $entity = $entity_storage->create([
      'text' => 'Some text',
      'name' => 'A title',
    ]);
    $entity->save();

    $another_entity = $entity_storage->create([
      'text' => 'Another text',
      'name' => 'Anoter title',
    ]);
    $another_entity->save();

    $this->assertSame('vgzm.123456-' . $entity->uuid(), $entity->vgwort_counter_id->value);
    $this->assertSame('vgzm.123456-' . $another_entity->uuid(), $another_entity->vgwort_counter_id->value);
    $this->enableModules(['vgwort_test']);
    $this->container->get('state')->set('vgwort_test_vgwort_enable_for_entity', [$another_entity->id()]);

    $entity_storage->resetCache();
    $entity = $entity_storage->load($entity->id());
    $another_entity = $entity_storage->load($another_entity->id());
    $this->assertSame('vgzm.123456-' . $entity->uuid(), $entity->vgwort_counter_id->value);
    $this->assertNull($another_entity->vgwort_counter_id->value);

    $this->container->get('state')->set('vgwort_test_vgwort_enable_for_entity', [$entity->id()]);
    $entity_storage->resetCache();
    $entity = $entity_storage->load($entity->id());
    $another_entity = $entity_storage->load($another_entity->id());
    $this->assertNull($entity->vgwort_counter_id->value);
    $this->assertSame('vgzm.123456-' . $another_entity->uuid(), $another_entity->vgwort_counter_id->value);
  }

}
