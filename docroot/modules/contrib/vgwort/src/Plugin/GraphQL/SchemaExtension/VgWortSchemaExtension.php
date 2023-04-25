<?php

namespace Drupal\vgwort\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * The menu schema extension.
 *
 * @SchemaExtension(
 *   id = "vgwort",
 *   name = "VG Wort Extension",
 *   description = "VG Wort mappings.",
 *   schema = "composable"
 * )
 */
class VgWortSchemaExtension extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $fields = [
      'counterId',
      'url',
      'rendered',
    ];

    foreach ($fields as $field) {
      $registry->addFieldResolver('VgWort', $field,
        $builder->callback(function ($arr) use ($field) {
          return $arr[$field];
        })
      );
    }
  }

}
