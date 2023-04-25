<?php

namespace Drupal\paragraphs_paste;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base Paragraphs paste plugin implementation.
 *
 * @see \Drupal\paragraphs_paste\Annotation\ParagraphsPastePlugin
 * @see \Drupal\paragraphs_paste\ParagraphsPastePluginBase
 * @see \Drupal\paragraphs_paste\ParagraphsPastePluginInterface
 * @see \Drupal\paragraphs_paste\ParagraphsPastePluginManager
 * @see plugin_api
 */
abstract class ParagraphsPastePluginBase extends PluginBase implements ContainerFactoryPluginInterface, ParagraphsPastePluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity selection manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $entityReferenceSelectionManager;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a ParagraphsPastePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionPluginManager
   *   The entity selection manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, SelectionPluginManagerInterface $selectionPluginManager, EntityDisplayRepositoryInterface $entityDisplayRepository, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityReferenceSelectionManager = $selectionPluginManager;
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_display.repository'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createParagraphEntity($input) {
    $property_path = explode('.', $this->configuration['property_path']);

    $target_entity_type = array_shift($property_path);
    $target_bundle = array_shift($property_path);

    $entity_type = $this->entityTypeManager->getDefinition($target_entity_type);

    $paragraph_entity = $this->entityTypeManager->getStorage($target_entity_type)
      ->create([
        $entity_type->getKey('bundle') => $target_bundle,
      ]);

    $this->setFieldValue($paragraph_entity, $property_path, $input);

    return $paragraph_entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($input, array $definition) {
    return !empty(trim($input));
  }

  /**
   * Sets value to a property path.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to set the value on.
   * @param array $property_path
   *   Property path.
   * @param mixed $value
   *   The value to set.
   * @param bool $save
   *   Entity needs to be saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setFieldValue(EntityInterface $entity, array $property_path, $value, $save = FALSE) {

    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $path = array_shift($property_path);

    [$fieldName, $bundle] = array_pad(explode(':', $path), 2, NULL);

    if (empty($fields[$fieldName])) {
      return;
    }

    $fieldConfig = $fields[$fieldName];

    // If bundle is defined, it's an ER field.
    if ($bundle) {

      $target_type = $fieldConfig->getFieldStorageDefinition()
        ->getSetting('target_type');

      /** @var \Drupal\Core\Entity\EntityInterface $newEntity */
      $newEntity = $this->entityReferenceSelectionManager->getSelectionHandler($fieldConfig)
        ->createNewEntity($target_type, $bundle, NULL, $this->currentUser->id());

      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $formDisplay */
      $formDisplay = $this->entityDisplayRepository->getFormDisplay($entity->getEntityTypeId(), $entity->bundle());

      $save = $save || (!in_array(
        $formDisplay->getComponent($fieldName)['type'],
        [
          'inline_entity_form_simple',
          'inline_entity_form_complex',
        ]
      ));

      if (!empty($property_path)) {
        $this->setFieldValue($newEntity, $property_path, $value, $save);
      }

      if ($save) {
        $newEntity->save();
      }

      $entity->{$fieldName}[] = $newEntity;
    }
    else {
      $entity->{$fieldName} = $this->formatInput($value, $fieldConfig);
    }
  }

  /**
   * Format the input before assigning the field.
   *
   * @param string $value
   *   The field value.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return string
   *   The formatted field value.
   */
  protected function formatInput($value, FieldDefinitionInterface $fieldDefinition) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSummaryItem() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildGuidelines() {
    return NULL;
  }

}
