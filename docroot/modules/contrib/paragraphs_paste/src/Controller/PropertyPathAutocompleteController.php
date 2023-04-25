<?php

namespace Drupal\paragraphs_paste\Controller;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for watches autocomplete form elements.
 */
class PropertyPathAutocompleteController extends ControllerBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $controller = parent::create($container);
    $controller->setEntityFieldManager($container->get('entity_field.manager'));
    return $controller;
  }

  /**
   * Set the entity field manager.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  protected function setEntityFieldManager(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request) {
    $string = $request->query->get('q');
    $types = $request->query->all()['allowed_field_types'] ?? [];
    if (substr_count($string, '.') == 0) {
      $searchKeyword = "";
      $matches = [
        [
          'value' => 'paragraph.',
          'label' => "paragraph (Paragraph)",
          'keyword' => "paragraph Paragraph",
        ],
      ];
    }
    elseif (substr_count($string, '.') == 1) {
      [$searchKeyword, $matches] = $this->matchBundle($string);
    }
    else {
      [
        $searchKeyword,
        $matches,
      ] = $this->matchField($string, $types);
    }

    if ($searchKeyword) {
      $matches = array_filter($matches, function ($value) use ($searchKeyword) {
        return strpos(strtolower($value['keyword']), strtolower($searchKeyword)) !== FALSE;
      });
    }

    usort($matches, [$this, 'sortByLabelElement']);
    return new JsonResponse($matches);
  }

  /**
   * Sorts a structured array by 'label' element.
   *
   * Callback for uasort().
   *
   * @param array $a
   *   First item for comparison. The compared items should be associative
   *   arrays that optionally include a '#title' key.
   * @param array $b
   *   Second item for comparison.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public static function sortByLabelElement(array $a, array $b) {
    return SortArray::sortByKeyString($a, $b, 'label');
  }

  /**
   * Matches the current bundle.
   *
   * @param string $string
   *   Current search string.
   *
   * @return array
   *   Possible suggestions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function matchBundle($string) {
    list($entityType, $bundle) = explode('.', $string);
    $definition = $this->entityTypeManager()->getDefinition($entityType);

    $targetBundles = $this->entityTypeManager()
      ->getStorage($definition->getBundleEntityType())
      ->loadMultiple();

    $matches = [];
    foreach ($targetBundles as $targetBundle) {
      $name = $entityType . '.' . $targetBundle->id();
      $matches[] = [
        'value' => "$name.",
        'label' => "$name ({$targetBundle->label()})",
        'keyword' => "{$targetBundle->id()} {$targetBundle->label()}",
      ];
    }
    return [$bundle, $matches];
  }

  /**
   * Matches the current field.
   *
   * @param string $string
   *   Current search string.
   * @param array $types
   *   Allowed field types.
   *
   * @return array
   *   Possible suggestions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function matchField($string, array $types = []) {
    $matches = [];

    $parts = explode('.', $string);

    $entityType = array_shift($parts);
    $bundle = array_shift($parts);

    $definitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);

    $value = "$entityType.$bundle.";
    $targetBundles = [];
    $reference = TRUE;
    foreach ($parts as $part) {

      if (strpos($part, ':') !== FALSE) {
        $reference = TRUE;

        list($fieldName, $bundle) = array_pad(explode(':', $part), 2, NULL);
        $entityType = $definitions[$fieldName]->getFieldStorageDefinition()
          ->getSetting('target_type');
        $definition = $this->entityTypeManager()->getDefinition($entityType);

        $entityBundles = $this->entityTypeManager()
          ->getStorage($definition->getBundleEntityType())
          ->loadMultiple();

        $target_bundles = $definitions[$fieldName]->getSetting('handler_settings')['target_bundles'] ?? [];
        $targetBundles = array_filter($entityBundles, function ($bundle) use ($target_bundles) {
          return isset($target_bundles[$bundle->id()]);
        });

        if ($bundle && isset($targetBundles[$bundle])) {
          $definitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
          $value .= "$fieldName:$bundle.";
        }
        else {
          $value .= "$fieldName:";
        }
      }
      else {
        if (!$reference) {
          $definitions = [];
        }
        $reference = FALSE;
      }
    }
    if (strrpos($string, '.') > strrpos($string, ':')) {
      $searchKeyword = end($parts);

      foreach ($definitions as $definition) {
        if (!$definition instanceof FieldConfigInterface) {
          continue;
        }
        if (in_array($definition->getType(), $types)) {
          $name = $value . $definition->getName();
          $matches[] = [
            'value' => "$name",
            'label' => "$name ({$definition->getLabel()})",
            'keyword' => "{$definition->getName()} {$definition->getLabel()}",
          ];
        }
        elseif (in_array($definition->getType(), [
          'entity_reference',
          'entity_reference_revisions',
        ])) {
          $name = $value . $definition->getName();
          $matches[] = [
            'value' => "$name:",
            'label' => "$name ({$definition->getLabel()})",
            'keyword' => "{$definition->getName()} {$definition->getLabel()}",
          ];
        }
      }
    }
    else {
      $searchKeyword = $bundle;

      foreach ($targetBundles as $definition) {
        $name = $value . $definition->id();
        $matches[] = [
          'value' => "$name.",
          'label' => "$name ({$definition->label()})",
          'keyword' => "{$definition->id()} {$definition->label()}",
        ];
      }
    }
    return [$searchKeyword, $matches];
  }

}
