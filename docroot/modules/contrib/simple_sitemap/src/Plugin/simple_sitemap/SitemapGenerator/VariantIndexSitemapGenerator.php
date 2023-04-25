<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

/**
 * Class VariantIndexSitemapGenerator.
 *
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator
 *
 * @SitemapGenerator(
 *   id = "variant_index_generator",
 *   label = @Translation("Variant Index generator"),
 *   description = @Translation("Generates a Variant Index of your sitemaps."),
 * )
 */
class VariantIndexSitemapGenerator extends SitemapGeneratorBase {

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with generated.
   *
   * @return string
   *   Sitemap chunk
   */
  protected function getXml(array $links) {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    // Add the XML stylesheet to document if enabled.
    if ($this->settings['xsl']) {
      $this->writer->writeXsl();
    }

    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    foreach ($links as $link) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', $link['loc']);
      $this->writer->writeElement('lastmod', $link['lastmod']);
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

}
