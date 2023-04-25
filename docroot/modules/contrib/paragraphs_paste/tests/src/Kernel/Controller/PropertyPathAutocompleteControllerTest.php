<?php

namespace Drupal\Tests\paragraphs_paste\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs_paste\Controller\PropertyPathAutocompleteController;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the behavior plugins API.
 *
 * @group paragraphs_paste
 */
class PropertyPathAutocompleteControllerTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'paragraphs_paste_test',
    'paragraphs_paste',
    'paragraphs',
    'media_entity_twitter',
    'entity_reference_revisions',
    'file',
    'field',
    'media',
    'node',
    'system',
    'text',
    'filter',
    'editor',
    'user',
    'ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['paragraphs_paste_test']);

  }

  /**
   * Data provider for handleAutocomplete.
   */
  public function handleAutocompleteProvider() {
    return [
      [
        'para',
        '[{"value":"paragraph.","label":"paragraph (Paragraph)","keyword":"paragraph Paragraph"}]',
      ],
      [
        'paragraph.',
        '[{"value":"paragraph.text.","label":"paragraph.text (Text)","keyword":"text Text"},{"value":"paragraph.twitter.","label":"paragraph.twitter (Twitter)","keyword":"twitter Twitter"},{"value":"paragraph.video.","label":"paragraph.video (Video)","keyword":"video Video"}]',
      ],
      [
        'paragraph.te',
        '[{"value":"paragraph.text.","label":"paragraph.text (Text)","keyword":"text Text"},{"value":"paragraph.twitter.","label":"paragraph.twitter (Twitter)","keyword":"twitter Twitter"}]',
      ],
      [
        'paragraph.text.',
        '[{"value":"paragraph.text.field_text","label":"paragraph.text.field_text (Text)","keyword":"field_text Text"}]',
      ],
      [
        'paragraph.video.field_video:',
        '[{"value":"paragraph.video.field_video:remote_video.","label":"paragraph.video.field_video:remote_video (Remote video)","keyword":"remote_video Remote video"}]',
      ],
    ];
  }

  /**
   * Test the handle autocomplete method.
   *
   * @dataProvider handleAutocompleteProvider
   */
  public function testHandleAutocomplete($input, $result) {

    $controller = PropertyPathAutocompleteController::create($this->container);
    $response = $controller->handleAutocomplete(new Request([
      'q' => $input,
      'allowed_field_types' => ['text_long'],
    ]));
    $this->assertSame($result, $response->getContent());
  }

}
