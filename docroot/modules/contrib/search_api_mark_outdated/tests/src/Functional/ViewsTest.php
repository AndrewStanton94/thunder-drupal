<?php

namespace Drupal\Tests\search_api_mark_outdated\Functional;

use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Functional\SearchApiBrowserTestBase;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api_mark_outdated
 */
class ViewsTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_mark_outdated_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $additionalBundles = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
  }

  /**
   * Tests that a result item is marked as outdated after it was changed.
   */
  public function testViewsAdmin(): void {

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('search-api-test-mark-outdated', ['query' => ['search_api_fulltext' => 'baz']]);

    $this->assertSession()->elementExists('xpath', "//table/tbody/tr[3]/td[2]/div[@data-is-outdated=0]");

    $entity = EntityTestMulRevChanged::load(1);
    $entity->name = 'foo';
    $entity->save();

    // Our item is still in the result set, that's why we mark it outdated.
    $this->drupalGet('search-api-test-mark-outdated', ['query' => ['search_api_fulltext' => 'baz']]);

    $this->assertSession()->elementExists('xpath', "//div[contains(@class, 'table-outdated')]/table/tbody/tr[3]/td[2]/div[@data-is-outdated=1]");
  }

}
