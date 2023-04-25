<?php

namespace Drupal\paragraphs_paste\Plugin\ParagraphsPastePlugin;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_paste\ParagraphsPastePluginBase;

/**
 * Defines the "oembed_url" plugin.
 *
 * @ParagraphsPastePlugin(
 *   id = "oembed_url",
 *   label = @Translation("OEmbed Urls"),
 *   module = "paragraphs_paste",
 *   weight = 10,
 *   providers = {},
 *   deriver = "\Drupal\paragraphs_paste\Plugin\Derivative\OEmbedUrlDeriver",
 *   allowed_field_types = {"string"}
 * )
 */
class OEmbedUrl extends ParagraphsPastePluginBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($input, array $definition) {
    if (!parent::isApplicable($input, $definition)) {
      return FALSE;
    }
    $input = trim($input);

    if (!\Drupal::service('path.validator')->isValid($input)) {
      return FALSE;
    }

    /** @var \Drupal\media\OEmbed\UrlResolverInterface $resolver */
    $resolver = \Drupal::service('media.oembed.url_resolver');

    foreach ($definition['providers'] as $provider_name) {
      try {
        $provider = $resolver->getProviderByUrl($input);
        if ($provider_name == $provider->getName()) {
          return TRUE;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatInput($value, FieldDefinitionInterface $fieldDefinition) {
    return trim($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSummaryItem() {
    return new TranslatableMarkup('Plugin for processing oembed urls.');
  }

  /**
   * {@inheritdoc}
   */
  public static function buildGuidelines() {

    return [
      '#markup' => '<p>' . t('Insert oembed url to create specific paragraph.') . '</p>',
    ];
  }

}
