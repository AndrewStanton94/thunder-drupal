<?php

namespace Drupal\vgwort\Plugin\GraphQL\DataProducer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\vgwort\Plugin\Field\FieldFormatter\CounterIdImage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves vgwort field data.
 *
 * @DataProducer(
 *   id = "vgwort",
 *   name = @Translation("VG Wort"),
 *   description = @Translation("Resolves the vgwort field."),
 *   produces = @ContextDefinition("map",
 *     label = @Translation("VG Wort field values")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("The entity.")
 *     )
 *   }
 * )
 */
class VgWort extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * Constructs a \Drupal\vgwort\Plugin\GraphQL\DataProducer\VgWort object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('renderer'),
    );
  }

  /**
   * Resolve the VG Wort field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity with a vgwort field.
   *
   * @return array|null
   *   The VG Wort field data.
   */
  public function resolve(ContentEntityInterface $entity): ?array {
    // The computed field is not added to this entity type.
    if (!$entity->hasField('vgwort_counter_id')) {
      return NULL;
    }

    // VG Wort is not set up or hook_vgwort_enable_for_entity() has disabled it.
    if ($entity->vgwort_counter_id->isEmpty()) {
      return [
        'counterId' => '',
        'url' => '',
        'rendered' => '',
      ];
    }

    $render_array = CounterIdImage::getRenderArray($entity->vgwort_counter_id->url, $this->configFactory->get('vgwort.settings'));
    return [
      'counterId' => $entity->vgwort_counter_id->value,
      'url' => $entity->vgwort_counter_id->url,
      'rendered' => $this->renderer->renderPlain($render_array),
    ];
  }

}
