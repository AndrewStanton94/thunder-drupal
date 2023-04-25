<?php

namespace Drupal\vgwort\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'vgwort_counter_id_image' formatter.
 *
 * Default field formatting is removed using the
 * field--vgwort-counter-id.html.twig template.
 *
 * @FieldFormatter(
 *   id = "vgwort_counter_id_image",
 *   label = @Translation("VG Wort counter ID image"),
 *   field_types = {
 *     "vgwort_counter_id",
 *   }
 * )
 *
 * @see vgwort_theme()
 */
class CounterIdImage extends FormatterBase {

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // There are too many arguments in the constructor. Let's do this the simple
    // way.
    $formatter->configFactory = $container->get('config.factory');
    return $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }
    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   Counter ID image generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item): array {
    // Test mode will render the web bug in a comment.
    $config = $this->configFactory->get('vgwort.settings');
    return self::getRenderArray($item->url, $config);
  }

  /**
   * Creates a render array for the 1x1 pixel web bug.
   *
   * @param string $url
   *   The VG Wort web bug url.
   * @param \Drupal\Core\Config\Config $config
   *   The VG Wort settings.
   *
   * @return array
   *   The render array.
   */
  public static function getRenderArray(string $url, Config $config): array {
    // Test mode will render the web bug in a comment.
    $test_mode = $config->get('test_mode');
    // The VG Wort documentation says to use border="0" but this is no longer
    // supported by HTML5. Should this be omitted?
    // The alt attribute has been added as this is how the HTML appears in VG
    // Wort's user interface.
    // @todo should we use a proper template for more flexibility?
    return [
      '#type' => 'inline_template',
      '#prefix' => $test_mode ? Markup::create('<!-- ') : '',
      '#template' => '<img src="\\\\{{ url }}" height="1" width="1" alt=""/>',
      '#context' => ['url' => $url],
      '#suffix' => $test_mode ? Markup::create(' -->') : '',
      '#cache' => ['tags' => $config->getCacheTags()],
    ];
  }

}
