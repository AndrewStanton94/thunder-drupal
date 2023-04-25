<?php

namespace Drupal\Tests\xymatic\Kernel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\media\Entity\Media;
use Drupal\Tests\xymatic\Kernel\XymaticKernelTestBase;
use Drupal\xymatic\Controller\XymaticWebhookController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests the Xymatic webhook controller.
 *
 * @group xymatic
 */
class XymaticWebhookControllerTest extends XymaticKernelTestBase {

  /**
   * Tests that an invalid token request is denied.
   */
  public function testInvalidTokenRequest() {
    $request = new Request();
    $request->headers->set('X-Xymatic-Token', 'invalid-token');

    $this->expectException(AccessDeniedHttpException::class);

    $controller = new XymaticWebhookController();
    $controller->handle($request);
  }

  /**
   * Tests that an invalid payload is denied.
   */
  public function testInvalidPayloadRequest() {

    $this->config('xymatic.settings')
      ->set('access_token', 'valid-token')
      ->save();

    $request = new Request([], [], [], [], [], [],
      json_encode([
        'id' => 'test',
        'payload' => [],
      ], JSON_THROW_ON_ERROR),
    );
    $request->headers->set('X-Xymatic-Token', 'valid-token');
    $request->headers->set('Content-Type', 'application/json');

    $this->expectException(BadRequestHttpException::class);

    $controller = new XymaticWebhookController();
    $controller->handle($request);
  }

  /**
   * Tests that a valid token is accepted.
   */
  public function testValidTokenRequest() {

    $this->config('xymatic.settings')
      ->set('access_token', 'valid-token')
      ->save();
    $request = new Request([], [], [], [], [], [],
      json_encode([
        'type' => 'test',
        'id' => 'test',
        'payload' => [
          'contentId' => 'test',
        ],
      ], JSON_THROW_ON_ERROR),
    );

    $request->headers->set('X-Xymatic-Token', 'valid-token');
    $request->headers->set('Content-Type', 'application/json');

    $controller = new XymaticWebhookController();
    $response = $controller->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests that a media entity is created.
   */
  public function testMediaCreate() {

    $request = new Request([], [], [], [], [], [],
      json_encode([
        'type' => 'create',
        'id' => '1',
        'payload' => [
          'contentId' => 'random-contentId',
        ],
      ], JSON_THROW_ON_ERROR),
    );

    // Mock the video fetcher.
    $mockVideoFetcher = $this->getMockBuilder('Drupal\xymatic\VideoFetcher')
      ->disableOriginalConstructor()
      ->getMock();
    $mockVideoFetcher->expects($this->atLeastOnce())->method('fetch')->with('random-contentId')
      ->willReturn(Json::decode(file_get_contents(__DIR__ . '/../../../fixtures/sample-get-response.json')));

    $this->container->set('xymatic.video_fetcher', $mockVideoFetcher);

    $controller = new XymaticWebhookController();
    $response = $controller->handle($request);

    $this->assertEquals(200, $response->getStatusCode());

    $media = Media::load(1);
    $this->assertEquals('xymatic', $media->getSource()->getPluginId());
    $this->assertEquals('random-contentId', $media->get('field_media_xymatic')->value);
    $this->assertEquals('gin-dialog.mp4', $media->label());
    $this->assertTrue($media->isPublished());
    $this->assertStringMatchesFormat('public://xymatic_thumbnails/%d-%d/%s.png', $media->getSource()->getMetadata($media, 'thumbnail_uri'));
    $this->assertFalse($media->getSource()->getMetadata($media, 'adsDisallowed'));
    $this->assertEquals('6mCNo51V93OkKAkUNQJL2QufnVz2', $media->getSource()->getMetadata($media, 'author'));
    $this->assertEquals(14, $media->getSource()->getMetadata($media, 'duration'));
    $this->assertFalse($media->getSource()->getMetadata($media, 'nudeContent'));
    $this->assertEquals('My awesome summary', $media->getSource()->getMetadata($media, 'summary'));

  }

  /**
   * Test that a media item is updated when a webhook is received.
   */
  public function testMediaUpdate() {
    // Mock the video fetcher.
    $mockVideoFetcher = $this->getMockBuilder('Drupal\xymatic\VideoFetcher')
      ->disableOriginalConstructor()
      ->getMock();

    $createResponse = [
      'title' => 'My video',
    ];
    $updateResponse = [
      'title' => 'My updated video',
      'enabled' => FALSE,
    ];

    $mockVideoFetcher->expects($this->exactly(8))->method('fetch')->with(1)
      ->willReturnOnConsecutiveCalls($createResponse, $createResponse, $createResponse, $createResponse, $createResponse, $updateResponse, $updateResponse, $updateResponse);
    $this->container->set('xymatic.video_fetcher', $mockVideoFetcher);

    $media = Media::create([
      'bundle' => $this->xymaticMediaType->id(),
      'field_media_xymatic' => 1,
    ]);
    $media->save();

    $media = Media::load(1);
    $this->assertEquals('xymatic', $media->getSource()->getPluginId());
    $this->assertEquals('1', $media->get('field_media_xymatic')->value);
    $this->assertEquals('My video', $media->label());
    $this->assertTrue($media->isPublished());

    $request = new Request([], [], [], [], [], [],
      json_encode([
        'type' => 'update',
        'id' => '1',
        'payload' => [
          'contentId' => 1,
        ],
      ], JSON_THROW_ON_ERROR),
    );

    $controller = new XymaticWebhookController();
    $controller->handle($request);

    $media = Media::load(1);
    $this->assertEquals('My updated video', $media->label());
    $this->assertFalse($media->isPublished());
  }

  /**
   * Test that a media item is deleted when a webhook is received.
   */
  public function testMediaDelete() {
    // Mock the video fetcher.
    $mockVideoFetcher = $this->getMockBuilder('Drupal\xymatic\VideoFetcher')
      ->disableOriginalConstructor()
      ->getMock();

    $createResponse = [
      'title' => 'My video',
    ];

    $mockVideoFetcher->expects($this->exactly(5))->method('fetch')->with(1)
      ->willReturn($createResponse);
    $this->container->set('xymatic.video_fetcher', $mockVideoFetcher);

    $media = Media::create([
      'bundle' => $this->xymaticMediaType->id(),
      'field_media_xymatic' => 1,
    ]);
    $media->save();

    $media = Media::load(1);
    $this->assertEquals('xymatic', $media->getSource()->getPluginId());
    $this->assertEquals('1', $media->get('field_media_xymatic')->value);
    $this->assertEquals('My video', $media->label());
    $this->assertTrue($media->isPublished());

    $request = new Request([], [], [], [], [], [],
      json_encode([
        'type' => 'delete',
        'id' => '1',
        'payload' => [
          'contentId' => 1,
        ],
      ], JSON_THROW_ON_ERROR),
    );

    $controller = new XymaticWebhookController();
    $controller->handle($request);

    $this->assertEmpty(Media::load(1));
  }

}
