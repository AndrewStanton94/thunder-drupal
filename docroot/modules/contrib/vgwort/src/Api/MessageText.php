<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Render\PlainTextOutput;

/**
 * The message text.
 */
class MessageText implements \JsonSerializable {

  private const VALID_TEXT_TYPES = ['epub', 'pdf', 'plainText'];

  /**
   * TRUE if the text is poetic text, otherwise FALSE.
   *
   * @var bool
   */
  private bool $lyric;

  /**
   * Short description / heading (title).
   *
   * @var string
   */
  private string $shorttext;

  /**
   * The text itself.
   *
   * Possible keys are: 'epub', 'pdf' and 'plainText'. The values are:
   * - epub: The text in PDF format (base 64 encoded!). Maximum size: 15 MB.
   * - pdf: The text in EPUB format (base 64 encoded!). Maximum size: 15 MB.
   * - plaintext: Plain text without HTML and other formatting information (base
   *   64 encoded!). Maximum size: 15 MB.
   *
   * @todo Does the order of the keys matter if there are multiple? We are using
   *   ksort() to ensure the same order as the example.
   *
   * @var string[]
   */
  private array $text;

  /**
   * @param string $shorttext
   *   Short description / heading (title).
   * @param string $text
   *   The text itself.
   * @param string $text_type
   *   (optional) The text type. Either 'epub', 'pdf', 'plainText'. Defaults to
   *   'plainText'.
   * @param bool $lyric
   *   (optional) TRUE if the text is poetic text, otherwise FALSE. Defaults to
   *   FALSE.
   */
  public function __construct(string $shorttext, string $text, string $text_type = 'plainText', bool $lyric = FALSE) {
    $this->lyric = $lyric;
    // @todo Is there any maximum length for this value?
    $this->shorttext = PlainTextOutput::renderFromHtml($shorttext);
    $this->addText($text, $text_type);
  }

  /**
   * Adds text to the message.
   *
   * @param string $text
   *   The text. If the type is 'plainText' any HTML will be stripped.
   * @param string $text_type
   *   (optional) The text type. Defaults to 'plainText'.
   *
   * @return $this
   */
  public function addText(string $text, string $text_type = 'plainText') {
    if (!in_array($text_type, self::VALID_TEXT_TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid text type.", $text_type));
    }
    if ($text_type === 'plainText') {
      $text = PlainTextOutput::renderFromHtml($text);
    }
    $this->text[$text_type] = base64_encode($text);
    ksort($this->text);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'lyric' => $this->lyric,
      'shorttext' => $this->shorttext,
      'text' => $this->text,
    ];
  }

}
