<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\UrlHelper;

/**
 * Webranges means all URLs which are required to read the entire text once.
 *
 * Example 1: A text which is spread across 2 URLs must be registered with a
 * webrange (with 2 URLs) (e.g. http://domain1.de/page1.html,
 * http://domain1.de/page2.html).
 *
 * Example 2: A text under 1 URL but appears on 2 pages must be registered with
 * 2 webranges (with 1 URL each) (e.g. http://domain1.de/mytext.html and
 * http://domain2.de/mytext.html).
 */
class Webrange implements \JsonSerializable {

  /**
   * Maximum size: 250 characters.
   *
   * @var string[]
   */
  private array $urls;

  /**
   * @param string[] $urls
   *   An array of URLs required to read the entire text once.
   */
  public function __construct(array $urls) {
    assert(Inspector::assertAll(function (string $url) {
      return UrlHelper::isValid($url) && UrlHelper::isExternal($url);
    }, $urls));

    $this->urls = $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'urls' => $this->urls,
    ];
  }

}
