<?php

namespace Drupal\xymatic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The webhook controller that is called by Xymatic.
 */
class XymaticWebhookController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $controller = parent::create($container);
    $controller->setLoggerFactory($container->get('logger.factory'));

    return $controller;
  }

  /**
   * Handle the webhook request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the token is invalid.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the payload is invalid.
   * @throws \JsonException
   *   Thrown when the JSON is invalid.
   */
  public function handle(Request $request): Response {

    if (!$this->isTokenValid($request)) {
      throw new AccessDeniedHttpException('Invalid token');
    }

    // Check that the request is a JSON request.
    $content = json_decode(
      $request->getContent(),
      TRUE,
      512,
      JSON_THROW_ON_ERROR
    );
    if (!$this->isPayloadValid($content)) {
      throw new BadRequestHttpException(
        'Expected JSON payload with type, id and payload with contentId keys in the root does not exist.'
      );
    }

    $contentId = $content['payload']['contentId'];

    $config = $this->config('xymatic.settings');
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager()->getStorage('media_type')->load($config->get('media_type'));
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    switch ($content['type']) {
      case 'create':

        $media = $this->entityTypeManager()
          ->getStorage('media')
          ->create([
            'bundle' => $media_type->id(),
            $source_field => $contentId,
          ]);
        $media->save();
        break;

      case 'update':
        $medias = $this->entityTypeManager()
          ->getStorage('media')
          ->loadByProperties([$source_field => $contentId]);
        /** @var \Drupal\media\MediaInterface $media */
        $media = reset($medias);
        if ($media) {
          $this->cache('xymatic_video')->delete($contentId);
          $this->updateMappedMetadata($media, TRUE);
          $media->save();
        }

        break;

      case 'delete':
        $medias = $this->entityTypeManager()
          ->getStorage('media')
          ->loadByProperties([$source_field => $contentId]);
        /** @var \Drupal\media\MediaInterface $media */
        $media = reset($medias);
        if ($media) {
          $media->delete();
        }

        break;
    }

    if (isset($media)) {
      $this->moduleHandler()->invokeAll('xymatic_webhook', [$media, $content]);
    }

    return new Response();
  }

  /**
   * Checks if the payload is valid.
   *
   * @param array $payload
   *   The payload to check.
   *
   * @return bool
   *   TRUE if the payload is valid, FALSE otherwise.
   */
  protected function isPayloadValid(array $payload) {
    return array_key_exists('type', $payload) &&
      array_key_exists('id', $payload) &&
      array_key_exists('payload', $payload) &&
      array_key_exists('contentId', $payload['payload']);
  }

  /**
   * Checks if the token is valid.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to check.
   *
   * @return bool
   *   TRUE if the token is valid, FALSE otherwise.
   */
  protected function isTokenValid(Request $request) {
    $token = $request->headers->get('X-Xymatic-Token');
    $config = $this->config('xymatic.settings');

    return $token === $config->get('access_token');
  }

  /**
   * Maps metadata values into entity field values.
   *
   * @param \Drupal\media\MediaInterface $translation
   *   The media translation we are updating.
   * @param bool $overwrite_existing
   *   (optional) If TRUE, metadata values will always be copied into mapped
   *   field values. If FALSE, values will be copied only if the mapped field is
   *   empty or if the media source field changed. Defaults to FALSE.
   */
  protected function updateMappedMetadata(MediaInterface $translation, $overwrite_existing = FALSE) {
    $media_source = $translation->getSource();

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $translation->bundle->entity;
    foreach ($media_type->getFieldMap() as $metadata_attribute_name => $entity_field_name) {
      if (!$translation->hasField($entity_field_name)) {
        continue;
      }
      // Populate the field value in one of these scenarios:
      // - The caller of this function asked for it explicitly.
      // - The entity field is empty.
      if ($overwrite_existing || $translation->get($entity_field_name)->isEmpty()) {
        $translation->set(
          $entity_field_name,
          $media_source->getMetadata($translation, $metadata_attribute_name)
        );
      }
    }
  }

}
