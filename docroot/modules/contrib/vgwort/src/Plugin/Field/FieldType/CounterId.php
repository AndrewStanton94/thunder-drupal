<?php

namespace Drupal\vgwort\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'vgwort_counter_id' entity field type.
 *
 * This is added as a base field to all nodes using
 * vgwort_entity_base_field_info().
 *
 * @FieldType(
 *   id = "vgwort_counter_id",
 *   label = @Translation("VG Wort counter ID"),
 *   description = @Translation("A VG Wort counter ID based on an entity's UUID."),
 *   default_formatter = "vgwort_counter_id_image",
 *   list_class = "\Drupal\vgwort\Plugin\Field\CounterIdFieldItemList",
 *   no_ui = TRUE,
 * )
 *
 * @see vgwort_entity_base_field_info()
 */
class CounterId extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('value')))
      ->setRequired(TRUE)
      ->setComputed(TRUE);
    $properties['url'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('value')))
      ->setRequired(TRUE)
      ->setComputed(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    // This is a computed field.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    // We need to override this because everything is computed.
    return $this->properties['value']->getValue() === NULL;
  }

}
