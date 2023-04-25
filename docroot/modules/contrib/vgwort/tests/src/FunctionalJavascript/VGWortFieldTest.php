<?php

namespace Drupal\Tests\vgwort\FunctionalJavascript;

use Behat\Mink\Element\ElementInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Tests the VG Wort fields.
 *
 * @group vgwort
 */
class VGWortFieldTest extends WebDriverTestBase {
  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'vgwort', 'field_ui', 'page_cache'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'article']);
    $this->enablePageCaching();
  }

  /**
   * Tests the normal operation of the module.
   */
  public function testFields(): void {
    // Create a field.
    $node_field_name = 'field_vg_test';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $node_field_name,
      'entity_type' => 'node',
      'type' => 'vgwort_participant_info',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => $this->randomMachineName() . '_label',
    ])->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent($node_field_name, [
        'type' => 'vgwort_participant_info',
        'settings' => [
          'agency_abbr' => TRUE,
        ],
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'article', 'full')
      ->setComponent($node_field_name)
      ->save();

    $this->drupalLogin($this->createUser([
      'access content',
      'create article content',
      'edit own article content',
    ]));
    $this->drupalGet('node/add/article');
    $card_number = $this->assertSession()->waitForElementVisible('named', ['field', 'field_vg_test[0][card_number]']);
    $this->assertInstanceOf(ElementInterface::class, $card_number);
    $this->assertSession()->checkboxNotChecked('edit-field-vg-test-0-type-agency');
    $this->assertSession()->fieldExists('edit-field-vg-test-0-type-agency')->click();
    $agency_abbr = $this->assertSession()->waitForElementVisible('named', ['field', 'field_vg_test[0][agency_abbr]']);
    $this->assertInstanceOf(ElementInterface::class, $agency_abbr);
    $this->assertFalse($card_number->isVisible());
    $this->assertSession()->fieldExists('edit-field-vg-test-0-type-person')->click();
    $card_number = $this->assertSession()->waitForElementVisible('named', ['field', 'field_vg_test[0][card_number]']);
    $this->assertInstanceOf(ElementInterface::class, $card_number);
    $this->assertFalse($agency_abbr->isVisible());
    $card_number->setValue('123123123');
    $this->assertSession()->fieldExists('field_vg_test[0][firstname]')->setValue('Bob');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('Jones');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Test node for VGWort');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('article Test node for VGWort has been created.');
    $node = $this->drupalGetNodeByTitle('Test node for VGWort');
    $this->assertSame('123123123', $node->field_vg_test->card_number);
    $this->assertSame('Bob', $node->field_vg_test->firstname);
    $this->assertSame('Jones', $node->field_vg_test->surname);
    $this->assertNull($node->field_vg_test->agency_abbr);

    // Swap to an agency participant.
    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldExists('edit-field-vg-test-0-type-agency')->click();
    $agency_abbr = $this->assertSession()->waitForElementVisible('named', ['field', 'field_vg_test[0][agency_abbr]']);
    $this->assertInstanceOf(ElementInterface::class, $agency_abbr);
    $this->assertSession()->fieldExists('field_vg_test[0][agency_abbr]')->setValue('BBC');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('article Test node for VGWort has been updated.');
    $node = $this->drupalGetNodeByTitle('Test node for VGWort', TRUE);
    $this->assertNull($node->field_vg_test->card_number);
    $this->assertNull($node->field_vg_test->firstname);
    $this->assertNull($node->field_vg_test->surname);
    $this->assertSame('BBC', $node->field_vg_test->agency_abbr);

    $form_display = $display_repository->getFormDisplay('node', 'article');
    $widget = $form_display->getComponent($node_field_name);
    $widget['settings']['agency_abbr'] = FALSE;
    $form_display->setComponent($node_field_name, $widget);
    $form_display->save();

    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldNotExists('edit-field-vg-test-0-type-agency');
    $this->assertSession()->fieldNotExists('field_vg_test[0][agency_abbr]');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('article Test node for VGWort has been updated.');
    $node = $this->drupalGetNodeByTitle('Test node for VGWort', TRUE);
    $this->assertNull($node->field_vg_test->card_number);
    $this->assertNull($node->field_vg_test->firstname);
    $this->assertNull($node->field_vg_test->surname);
    $this->assertNull($node->field_vg_test->agency_abbr);

    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldExists('field_vg_test[0][card_number]')->setValue('12341234');
    $this->assertSession()->fieldExists('field_vg_test[0][firstname]')->setValue('Bob');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('Jones');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('article Test node for VGWort has been updated.');
    $node = $this->drupalGetNodeByTitle('Test node for VGWort', TRUE);
    $this->assertSame('12341234', $node->field_vg_test->card_number);
    $this->assertSame('Bob', $node->field_vg_test->firstname);
    $this->assertSame('Jones', $node->field_vg_test->surname);
    $this->assertNull($node->field_vg_test->agency_abbr);

    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldExists('field_vg_test[0][card_number]')->setValue('');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('Smith');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('article Test node for VGWort has been updated.');
    $node = $this->drupalGetNodeByTitle('Test node for VGWort', TRUE);
    $this->assertNull($node->field_vg_test->card_number);
    $this->assertSame('Bob', $node->field_vg_test->firstname);
    $this->assertSame('Smith', $node->field_vg_test->surname);
    $this->assertNull($node->field_vg_test->agency_abbr);

    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldExists('field_vg_test[0][card_number]')->setValue('');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('The Surname field is required. ');
    $this->assertSession()->fieldExists('field_vg_test[0][card_number]')->setValue('1234231431');
    $this->assertSession()->fieldExists('field_vg_test[0][firstname]')->setValue('');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('The Firstname field is required. ');
    $this->assertSession()->pageTextContainsOnce('The Surname field is required. ');
    $this->assertSession()->fieldExists('field_vg_test[0][firstname]')->setValue('B');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('Jones');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('This value is too short. It should have 2 characters or more.');
    $this->assertSession()->fieldExists('field_vg_test[0][firstname]')->setValue('Bob');
    $this->assertSession()->fieldExists('field_vg_test[0][surname]')->setValue('J');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContainsOnce('This value is too short. It should have 2 characters or more.');
  }

}
