<?php

namespace Drupal\paragraphs_paste\Plugin\ParagraphsPastePlugin;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_paste\ParagraphsPastePluginBase;
use Netcarver\Textile\Parser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "text" plugin.
 *
 * @ParagraphsPastePlugin(
 *   id = "text",
 *   label = @Translation("Text"),
 *   module = "paragraphs_paste",
 *   weight = -1,
 *   allowed_field_types = {"text", "text_long", "text_with_summary", "string",
 *   "string_long"}
 * )
 */
class Text extends ParagraphsPastePluginBase {

  /**
   * The textile Parser.
   *
   * @var \Netcarver\Textile\Parser
   */
  protected $textileParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setTextileParser(new Parser());

    return $instance;
  }

  /**
   * Sets the parser for this plugin.
   *
   * @param \Netcarver\Textile\Parser $parser
   *   The textile parser.
   */
  protected function setTextileParser(Parser $parser) {
    $this->textileParser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatInput($value, FieldDefinitionInterface $fieldDefinition) {
    return $this->parseTextileInput($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($input, array $definition) {
    return parent::isApplicable($input, $definition) && class_exists('\Netcarver\Textile\Parser');
  }

  /**
   * Use textile to parse input.
   */
  public function parseTextileInput($input) {
    $input = preg_replace('~\r?\n~', "\n", $input);
    return $this->textileParser->setBlockTags(TRUE)->setRestricted(TRUE)->parse($input);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSummaryItem() {
    return new TranslatableMarkup('Simple text processing plugin for text paragraphs.');
  }

}
