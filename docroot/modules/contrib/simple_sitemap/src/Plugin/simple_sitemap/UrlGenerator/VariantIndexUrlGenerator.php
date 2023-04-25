<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Database\Connection;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class VariantIndexUrlGenerator.
 *
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "variant_index_url_generator",
 *   label = @Translation("Variant Index URL generator"),
 *   description = @Translation("Generates URLs for variants."),
 * )
 */
class VariantIndexUrlGenerator extends EntityUrlGeneratorBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * VariantIndexUrlGenerator constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Path\PathValidator $path_validator
   * @param \Drupal\Core\Database\Connection $database
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    Logger $logger,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityHelper $entityHelper,
    PathValidator $path_validator,
    Connection $database) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $generator,
      $logger,
      $language_manager,
      $entity_type_manager,
      $entityHelper
    );
    $this->pathValidator = $path_validator;
    $this->db = $database;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\SimplesitemapPluginBase|\Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGeneratorBase|\Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase|static
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.logger'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('path.validator'),
      $container->get('database')
    );
  }

  /**
   * @return array|mixed
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDataSets() {
    $data_set = [];
    $sitemap_manager = $this->generator->getSitemapManager();
    $sitemap_settings = [
      'base_url' => $this->generator->getSetting('base_url', ''),
      'default_variant' => $this->generator->getSetting('default_variant', NULL),
    ];
    $sitemap_statuses = $this->fetchSitemapInstanceStatuses();

    foreach ($this->generator->getSitemapManager()->getSitemapTypes() as $type_name => $type_definition) {
      if (!empty($variants = $sitemap_manager->getSitemapVariants($type_name, FALSE))) {
        $sitemap_generator = $sitemap_manager
          ->getSitemapGenerator($type_definition['sitemapGenerator'])
          ->setSettings($sitemap_settings);
        foreach ($variants as $variant_name => $variant_definition) {
          // Exclude variant index.
          if (isset($sitemap_statuses[$variant_name]) && $type_name != 'variant_index') {
            switch ($sitemap_statuses[$variant_name]) {
              case 1:
                $data_set[$variant_name]['loc'] = $sitemap_generator->setSitemapVariant($variant_name)->getSitemapUrl();
                break;
            }
          }
        }
      }
    }
    return $data_set;
  }

  /**
   * @return array
   *   Array of sitemap statuses keyed by variant name.
   *   Status values:
   *   0: Instance is unpublished
   *   1: Instance is published
   *   2: Instance is published but is being regenerated
   *
   * @todo Move to SitemapGeneratorBase or DefaultSitemapGenerator so it can be overwritten by sitemap types with custom storages.
   */
  protected function fetchSitemapInstanceStatuses() {
    $results = $this->db
      ->query('SELECT type, status FROM {simple_sitemap} GROUP BY type, status')
      ->fetchAll();

    $instances = [];
    foreach ($results as $i => $result) {
      $instances[$result->type] = isset($instances[$result->type])
        ? $result->status + 1
        : (int) $result->status;
    }

    return $instances;
  }

  /**
   * @param $data_set
   *
   * @return array|mixed
   */
  protected function processDataSet($data_set) {
    $path_data = [
      'loc' => $data_set['loc'],
      'lastmod' => date('c'),
    ];
    return $path_data;
  }

}
