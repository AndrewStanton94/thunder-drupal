<?php

namespace Drupal\ivw_integration\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\ivw_integration\IvwTrackerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the IVW attributes for an entity.
 *
 * @DataProducer(
 *   id = "ivw_call",
 *   name = @Translation("IVW"),
 *   description = @Translation("Resolves the IVW attributes for an entity."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("IVW attributes")
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Root value")
 *     )
 *   }
 * )
 */
class IvwCall extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The rendering service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The ivw tracker service.
   *
   * @var \Drupal\ivw_integration\IvwTrackerInterface
   */
  protected $ivwTracker;

  /**
   * The ivw config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('renderer'),
      $container->get('ivw_integration.tracker'),
      $container->get('config.factory')
    );
  }

  /**
   * IVW constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\ivw_integration\IvwTrackerInterface $ivwTracker
   *   The ivw tracker service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    $pluginDefinition,
    RendererInterface $renderer,
    IvwTrackerInterface $ivwTracker,
    ConfigFactoryInterface $configFactory
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->renderer = $renderer;
    $this->ivwTracker = $ivwTracker;
    $this->config = $configFactory->get('ivw_integration.settings');
  }

  /**
   * Resolve the IVW data attributes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *   The cacheable dependency interface.
   *
   * @return mixed
   *   The IVW attributes.
   */
  public function resolve(EntityInterface $entity, RefinableCacheableDependencyInterface $metadata) {
    if (!($entity instanceof ContentEntityInterface)) {
      return '';
    }
    $context = new RenderContext();
    $result = $this->renderer->executeInRenderContext($context, function () use ($entity) {
      $tracker = $this->ivwTracker->getTrackingInformation($entity);

      // Site is missing, do not render tag.
      if (empty($tracker['st'])) {
        return [];
      }

      $mobile_width = $this->config->get("mobile_width") ? $this->config->get("mobile_width") : '';
      $mobile_site = $this->config->get("mobile_site") ? $this->config->get("mobile_site") : '';

      return [
        'st' => $tracker['st'],
        'cp' => $tracker['cp'],
        'sv' => $tracker['sv'],
        'dn' => $tracker['dn'],
        // Not yet configurable.
        'co' => '',
        'dg' => $tracker['dg'],
        'pt' => $tracker['pt'],
        'dc' => $tracker['dc'],
        'bfe' => $tracker['bfe'],
        'mobile_cp' => $tracker['cpm'],
        'mobile_st' => $mobile_site,
        'mobile_sv' => $tracker['mobile_sv'],
        'mobile_dn' => $tracker['mobile_dn'],
        'mobile_width' => $mobile_width,
        'legacy_mode' => $tracker['legacy_mode'],
      ];
    });

    if (!$context->isEmpty()) {
      $metadata->addCacheableDependency($context->pop());
    }

    return $result ?? '';
  }

}
