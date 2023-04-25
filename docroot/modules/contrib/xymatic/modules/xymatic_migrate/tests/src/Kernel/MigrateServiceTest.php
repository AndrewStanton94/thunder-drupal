<?php

namespace Drupal\Tests\xymatic_migrate\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\xymatic\Kernel\XymaticKernelTestBase;

/**
 * Tests the migrate service.
 *
 * @group xymatic_migrate
 */
class MigrateServiceTest extends XymaticKernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['xymatic_migrate', 'entity_test'];

  /**
   * The legacy media type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected MediaTypeInterface $legacyMediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->legacyMediaType = $this->createMediaType('file');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_media_entity_test', 'Entity test', 'media', 'default:media', [
      'target_bundles' => [
        $this->xymaticMediaType->id() => $this->xymaticMediaType->id(),
        $this->legacyMediaType->id() => $this->legacyMediaType->id(),
      ],
    ], 1);

    $this->config('xymatic_migrate.settings')
      ->set('legacy_media_type', $this->legacyMediaType->id())
      ->set('legacy_video_id_jsonpath', 'payload.userMetadata.legacyId')
      ->save();

  }

  /**
   * Tests the migrate service.
   */
  public function testMigrate() {
    // Mock the video fetcher.
    $mockVideoFetcher = $this->getMockBuilder('Drupal\xymatic\VideoFetcher')
      ->disableOriginalConstructor()
      ->getMock();

    $mockVideoFetcher->expects($this->exactly(5))->method('fetch')->with(1)
      ->willReturn([
        'title' => 'My video',
      ]
    );
    $this->container->set('xymatic.video_fetcher', $mockVideoFetcher);

    $media = Media::create([
      'bundle' => $this->xymaticMediaType->id(),
      'field_media_xymatic' => 1,
    ]);
    $media->save();

    $legacyMedia = $this->generateMedia('test.patch', $this->legacyMediaType);
    $legacyMedia->save();

    $requestBody = [
      'type' => 'create',
      'payload' => [
        'userMetadata' => [
          'legacyId' => $legacyMedia->getSource()->getSourceFieldValue($legacyMedia),
        ],
      ],
    ];

    $entity = EntityTest::create([
      'name' => 'Test',
      'field_media_entity_test' => $legacyMedia,
    ]);
    $entity->save();

    $this->assertEquals(2, $entity->field_media_entity_test->target_id);

    \Drupal::getContainer()->get('xymatic.migrate')->migrate($media, $requestBody);
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->load($entity->id());

    $this->assertEquals(1, $entity->field_media_entity_test->target_id);
    $this->assertEquals('My video', $entity->field_media_entity_test->entity->label());
  }

}
