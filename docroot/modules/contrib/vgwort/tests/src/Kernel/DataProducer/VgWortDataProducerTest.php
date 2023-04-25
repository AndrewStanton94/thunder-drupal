<?php

namespace Drupal\Tests\vgwort\Kernel\DataProducer;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\Tests\vgwort\Traits\KernelSetupTrait;

/**
 * Data producers EntityLinks test class.
 *
 * @group Thunder
 */
class VgWortDataProducerTest extends GraphQLTestBase {
  use KernelSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advancedqueue',
    'vgwort',
    'text',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installVgWort();
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'tags',
    ])->save();

  }

  /**
   * Tests basic data producer functionality.
   */
  public function testDataProducer(): void {
    $node = Node::create([
      'title' => 'Title',
      'type' => 'article',
    ]);

    $node->save();

    $result = $this->executeDataProducer('vgwort', [
      'entity' => $node,
    ]);

    $this->assertNotNull($result);
    $this->assertSame($node->vgwort_counter_id->value, $result['counterId']);
    $this->assertSame($node->vgwort_counter_id->url, $result['url']);
    $this->assertSame('<img src="\\\\' . $node->vgwort_counter_id->url . '" height="1" width="1" alt=""/>', (string) $result['rendered']);

    // Test the test mode.
    $this->config('vgwort.settings')
      ->set('test_mode', TRUE)
      ->save();
    $result = $this->executeDataProducer('vgwort', [
      'entity' => $node,
    ]);
    $this->assertNotNull($result);
    $this->assertSame($node->vgwort_counter_id->value, $result['counterId']);
    $this->assertSame($node->vgwort_counter_id->url, $result['url']);
    $this->assertSame('<!-- <img src="\\\\' . $node->vgwort_counter_id->url . '" height="1" width="1" alt=""/> -->', (string) $result['rendered']);

    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Apples',
    ]);
    $term->save();

    $result = $this->executeDataProducer('vgwort', [
      'entity' => $term,
    ]);

    $this->assertNull($result);

    $this->config('vgwort.settings')
      ->set('entity_types', ['taxonomy_term' => []])
      ->save();

    $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->resetCache();
    $term = Term::load($term->id());

    $result = $this->executeDataProducer('vgwort', [
      'entity' => $term,
    ]);
    $this->assertNotNull($result);
    $this->assertSame($term->vgwort_counter_id->value, $result['counterId']);
    $this->assertSame($term->vgwort_counter_id->url, $result['url']);
    $this->assertSame('<!-- <img src="\\\\' . $term->vgwort_counter_id->url . '" height="1" width="1" alt=""/> -->', (string) $result['rendered']);

    // Ensure the data producer respects hook_vgwort_enable_for_entity().
    $this->enableModules(['vgwort_test']);
    $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->resetCache();
    $this->container->get('state')->set('vgwort_test_vgwort_enable_for_entity', [$term->id()]);
    $term = Term::load($term->id());

    $result = $this->executeDataProducer('vgwort', [
      'entity' => $term,
    ]);
    $this->assertNotNull($result);
    $this->assertSame('', $result['counterId']);
    $this->assertSame('', $result['url']);
    $this->assertSame('', $result['rendered']);
  }

}
