<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType;

/**
 * Class VariantIndexSitemapType.
 *
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType
 *
 * @SitemapType(
 *   id = "variant_index",
 *   label = @Translation("Variant Index"),
 *   description = @Translation("The sitemap for index other sitemaps."),
 *   sitemapGenerator = "variant_index_generator",
 *   urlGenerators = {
 *     "variant_index_url_generator",
 *   },
 * )
 */
class VariantIndexSitemapType extends SitemapTypeBase {
}
