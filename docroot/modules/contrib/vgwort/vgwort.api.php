<?php

/**
 * @file
 * Describes hooks provided by the VG Wort module.
 */

/**
 * Determines if an entity should be sent to VG Wort.
 *
 * This hook can be used to exclude entities that the site does not have the
 * rights to send to VG Wort. See \Drupal\vgwort\Api\NewMessage for more
 * information.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity to check.
 *
 * @return bool
 *   TRUE if the entity should contain the VG Wort web bug and be sent to
 *   VG Wort, FALSE if not.
 */
function hook_vgwort_enable_for_entity(\Drupal\Core\Entity\EntityInterface $entity): bool {
  return $entity->bundle() !== 'not_my_content';
}

/**
 * Alters the information sent about an entity to VG Wort.
 *
 * @param array $data
 *   Data to alter. This consists of the following data with the keys:
 *   - webranges: An array of \Drupal\vgwort\Api\Webrange objects. Defaults to
 *     a webrange containing the canonical URL of the entity.
 *   - legal_rights: An array of booleans with the same structure as
 *     vgwort.settings:legal_rights. Defaults to the values set in
 *     configuration.
 *   - without_own_participation: A boolean that indicates whether the publisher
 *     is involved. Defaults FALSE that indicates the publisher is involved.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity that is being used to generate the data to send to VG Wort.
 */
function hook_vgwort_new_message_alter(array &$data, \Drupal\Core\Entity\EntityInterface $entity): void {
  $data['without_own_participation'] = TRUE;
  $data['webranges'] = [new \Drupal\vgwort\Api\Webrange(['http://decoupled_site.com/' . $entity->id()])];
  $data['legal_rights']['other_public_communication'] = TRUE;
}
