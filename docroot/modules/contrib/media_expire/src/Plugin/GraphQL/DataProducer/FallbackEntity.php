<?php

namespace Drupal\media_expire\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\media\MediaInterface;
use Drupal\media_expire\MediaExpireService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads a route with an optional token.
 *
 * @DataProducer(
 *   id = "media_expire_fallback_entity",
 *   name = @Translation("Load fallback entity"),
 *   description = @Translation("Load fallback entity."),
 *   produces = @ContextDefinition("entity",
 *     label = @Translation("Entity")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     )
 *   }
 * )
 */
class FallbackEntity extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Media expire service.
   *
   * @var \Drupal\media_expire\MediaExpireService
   */
  protected $mediaExpireService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_expire.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    MediaExpireService $mediaExpireService
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->mediaExpireService = $mediaExpireService;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(MediaInterface $entity, RefinableCacheableDependencyInterface $metadata) {
    return $this->mediaExpireService->getFallbackMedia($entity);
  }

}
