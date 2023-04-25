<?php

namespace Drupal\xymatic\Plugin\media\Source;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Token;
use Drupal\media\MediaTypeInterface;
use Drupal\xymatic\VideoFetcher;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * Implementation of an oEmbed Instagram source.
 *
 * @MediaSource(
 *   id = "xymatic",
 *   label = @Translation("Xymatic"),
 *   description = @Translation("Xymatic video integration."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "video.png"
 * )
 */
class Xymatic extends MediaSourceBase {

  use LoggerChannelTrait;

  /**
   * The video fetcher service.
   *
   * @var \Drupal\xymatic\VideoFetcher
   */
  protected VideoFetcher $videoFetcher;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
    );
    $plugin->setLoggerFactory($container->get('logger.factory'));
    $plugin->setMessenger($container->get('messenger'));
    $plugin->setVideoFetcher($container->get('xymatic.video_fetcher'));
    $plugin->setToken($container->get('token'));
    $plugin->setFileSystem($container->get('file_system'));
    return $plugin;
  }

  /**
   * Set the video fetcher service.
   *
   * @param \Drupal\xymatic\VideoFetcher $videoFetcher
   *   The video fetcher service.
   */
  protected function setVideoFetcher(VideoFetcher $videoFetcher): void {
    $this->videoFetcher = $videoFetcher;
  }

  /**
   * Set the token service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  protected function setToken(Token $token): void {
    $this->token = $token;
  }

  /**
   * Set the file system service.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  protected function setFileSystem(FileSystemInterface $fileSystem): void {
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'adsDisallowed' => $this->t('Whether ads are disabled'),
      'author' => $this->t('The ID of the author/owner'),
      'date' => $this->t('The date the video was created'),
      'duration' => $this->t('The duration of the video in seconds'),
      'default_name' => $this->t('Default name of the media item'),
      'enabled' => $this->t('Whether the video is enabled'),
      'nudeContent' => $this->t('Whether the video contains nudity'),
      'shortTitle' => $this->t('The short title of the video'),
      'summary' => $this->t('The summary of the video'),
      'title' => $this->t('The title of the video'),
      'thumbnail_uri' => $this->t('The thumbnail of the video'),
      'url' => $this->t('The URL to the video'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {

    $contentId = $this->getSourceFieldValue($media);

    // Read example data from file.
    $json = $this->videoFetcher->fetch($contentId);

    switch ($name) {
      case 'default_name':
        $title = $this->getMetadata($media, 'title');

        if (!empty($title)) {
          return $title;
        }
        // Fallback to the parent's default name if everything else failed.
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        $posterImage = !empty($json['inputPosters']) ? end($json['inputPosters']) : [];
        return $this->getLocalThumbnailUri($posterImage) ?: parent::getMetadata($media, 'thumbnail_uri');

      case 'nudeContent':
        return $json['nudeContent'] ?? FALSE;

      default:
        return $json[$name] ?? parent::getMetadata($media, $name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['thumbnails_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thumbnails location'),
      '#default_value' => $this->configuration['thumbnails_directory'],
      '#description' => $this->t('Thumbnails will be fetched from the provider for local usage. This is the URI of the directory where they will be placed.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $thumbnails_directory = $form_state->getValue('thumbnails_directory');

    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');

    if (!$stream_wrapper_manager->isValidUri($thumbnails_directory)) {
      $form_state->setErrorByName('thumbnails_directory', $this->t('@path is not a valid path.', [
        '@path' => $thumbnails_directory,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'thumbnails_directory' => 'public://xymatic_thumbnails/[date:custom:Y-m]',
    ];
  }

  /**
   * Returns the local URI for a resource thumbnail.
   *
   * If the thumbnail is not already locally stored, this method will attempt
   * to download it.
   *
   * This is mostly copied from the oEmbed module.
   *
   * @param array $image
   *   The image data array.
   *
   * @return string|null
   *   The local thumbnail URI, or NULL if it could not be downloaded, or if the
   *   resource has no thumbnail at all.
   *
   * @todo Determine whether or not oEmbed media thumbnails should be stored
   * locally at all, and if so, whether that functionality should be
   * toggle-able. See https://www.drupal.org/project/drupal/issues/2962751 for
   * more information.
   */
  protected function getLocalThumbnailUri(array $image) {
    // If there is no remote thumbnail, there's nothing for us to fetch here.
    if (empty($image['src'])) {
      return NULL;
    }

    // Use the configured directory to store thumbnails. The directory can
    // contain basic (i.e., global) tokens. If any of the replaced tokens
    // contain HTML, the tags will be removed and XML entities will be decoded.
    $configuration = $this->getConfiguration();
    $directory = $configuration['thumbnails_directory'];
    $directory = $this->token->replace($directory);
    $directory = PlainTextOutput::renderFromHtml($directory);

    // The local thumbnail doesn't exist yet, so try to download it. First,
    // ensure that the destination directory is writable, and if it's not,
    // log an error and bail out.
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->getLogger('xymatic')->warning('Could not prepare thumbnail destination directory @dir for oEmbed media.', [
        '@dir' => $directory,
      ]);
      return NULL;
    }

    // The local filename of the thumbnail is always a hash of its remote URL.
    // If a file with that name already exists in the thumbnails directory,
    // regardless of its extension, return its URI.
    $hash = Crypt::hashBase64($image['src']);
    $files = $this->fileSystem->scanDirectory($directory, "/^$hash\..*/");
    if (count($files) > 0) {
      return reset($files)->uri;
    }

    // The local thumbnail doesn't exist yet, so we need to download it.
    try {
      $body = $this->videoFetcher->fetchThumbnail($image['src']);
      $extension = pathinfo($image['src'], PATHINFO_EXTENSION);
      $local_thumbnail_uri = $directory . DIRECTORY_SEPARATOR . $hash . '.' . $extension;
      $this->fileSystem->saveData((string) $body, $local_thumbnail_uri, FileSystemInterface::EXISTS_REPLACE);
      return $local_thumbnail_uri;
    }
    catch (TransferException $e) {
      $this->getLogger('xymatic')->warning('Failed to download remote thumbnail file due to "%error".', [
        '%error' => $e->getMessage(),
      ]);
    }
    catch (FileException $e) {
      $this->getLogger('xymatic')->warning('Could not download remote thumbnail from {url}.', [
        'url' => $image['src'],
      ]);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(MediaTypeInterface $type, EntityFormDisplayInterface $display) {
    parent::prepareFormDisplay($type, $display);
    $display->removeComponent('name');
  }

}
