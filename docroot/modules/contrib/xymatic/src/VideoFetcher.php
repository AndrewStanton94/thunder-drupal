<?php

namespace Drupal\xymatic;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\Client;

/**
 * Fetches videos from the Xymatic API.
 */
class VideoFetcher {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected readonly Config $config;

  /**
   * Constructs a new VideoFetcher object.
   *
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(protected readonly Client $httpClient, protected readonly ConfigFactory $configFactory, protected readonly CacheBackendInterface $cache) {
    $this->config = $configFactory->get('xymatic.settings');
  }

  /**
   * Fetches a video from the Xymatic API.
   *
   * @param string $contentId
   *   The content ID of the video to fetch.
   *
   * @return array
   *   The video data.
   */
  public function fetch(string $contentId): array {
    if ($this->cache && $cached_video = $this->cache->get($contentId)) {
      return $cached_video->data;
    }

    $response = $this->httpClient->get(sprintf('%s/v1/get_video/%s/%s', $this->config->get('api_url'), $this->config->get('license_key'), $contentId), [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->config->get('api_key'),
      ],
    ]);
    if ($response->getStatusCode() !== 200) {
      throw new \UnexpectedValueException(sprintf('Unexpected response code %d from Xymatic API.', $response->getStatusCode()));
    }

    $video_data = Json::decode($response->getBody());

    $this->cache->set($contentId, $video_data, CacheBackendInterface::CACHE_PERMANENT);

    return $video_data;
  }

  /**
   * Fetches a thumbnail from the Xymatic API.
   *
   * @param string $url
   *   The URL of the thumbnail to fetch.
   *
   * @return string
   *   The thumbnail data.
   */
  public function fetchThumbnail($url) {
    $response = $this->httpClient->request('GET', $url);

    if ($response->getStatusCode() !== 200) {
      throw new \UnexpectedValueException(sprintf('Unexpected response code %d from Xymatic API.', $response->getStatusCode()));
    }
    return $response->getBody();
  }

}
