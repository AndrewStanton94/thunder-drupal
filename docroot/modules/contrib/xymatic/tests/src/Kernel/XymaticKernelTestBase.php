<?php

namespace Drupal\Tests\xymatic\Kernel;

use Drupal\media\MediaTypeInterface;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Base class for Xymatic kernel tests.
 */
abstract class XymaticKernelTestBase extends MediaKernelTestBase {

  /**
   * The Xymatic media type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected MediaTypeInterface $xymaticMediaType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['xymatic'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('xymatic');

    $this->xymaticMediaType = $this->createMediaType('xymatic');
    $this->xymaticMediaType->setFieldMap([
      'enabled' => 'status',
      'default_name' => 'name',
    ]);
    $this->xymaticMediaType->save();

    $this->config('xymatic.settings')
      ->set('media_type', $this->xymaticMediaType->id())
      ->save();
  }

}
