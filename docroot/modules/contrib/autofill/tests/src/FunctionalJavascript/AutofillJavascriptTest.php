<?php

namespace Drupal\Tests\autofill\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Autofill module.
 *
 * @group autofill
 */
class AutofillJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'autofill',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The content type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $contentType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contentType = $this->drupalCreateContentType(['type' => 'article']);
    $this->setupFields();
  }

  /**
   * Tests the autofill of a new field based on the node title.
   */
  public function testAutofillFromAnotherField(): void {
    $this->drupalLogin($this->rootUser);
    $this->configureAutofillFields();

    // Start the actual test.
    $this->drupalGet('node/add/' . $this->contentType->id());
    $this->getSession()->getPage()->fillField('title[0][value]', $this->randomString());

    // Set the source fields and check the target fields.
    $this->getSession()->getPage()->fillField('field_source_1[0][value]', 'Test value 1');
    $this->assertSession()->fieldValueEquals('field_target_1[0][value]', 'Test value 1');
    $this->getSession()->getPage()->fillField('field_source_2[0][value]', 'Test value 2');
    $this->assertSession()->fieldValueEquals('field_target_2[0][value]', 'Test value 2');

    // Finally, save the node.
    $this->getSession()->getPage()->findButton('Save')->click();

    // Open the created node again. When changing the source field, the target
    // field should change since values are identical.
    $this->drupalGet('node/1/edit');
    $this->getSession()->getPage()->fillField('field_source_1[0][value]', 'My adjusted value 1');
    $this->assertSession()->fieldValueEquals('field_target_1[0][value]', 'My adjusted value 1');
    $this->getSession()->getPage()->fillField('field_source_2[0][value]', 'My adjusted value 2');
    $this->assertSession()->fieldValueEquals('field_target_2[0][value]', 'My adjusted value 2');

    // If the target field was manipulated once it should not be autofilled
    // anymore. Manipulation is done by pressing backspace in the textfield.
    $this->drupalGet('node/add/' . $this->contentType->id());
    $target_field = $this->getSession()->getPage()->findField('field_target_1[0][value]');
    $target_field->keyPress(8);
    $this->getSession()->getPage()->fillField('field_source_1[0][value]', 'My adjusted value');
    $this->assertSession()->fieldValueEquals('field_target_1[0][value]', '');
    // The second target field is still not manipulated, so copying should work.
    $this->getSession()->getPage()->fillField('field_source_2[0][value]', 'My adjusted value 2');
    $this->assertSession()->fieldValueEquals('field_target_2[0][value]', 'My adjusted value 2');
  }

  /**
   * Creates the necessary fields.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setupFields(): void {
    // Set up two source fields and one target.
    $fields = [
      'field_source_1',
      'field_target_1',
      'field_source_2',
      'field_target_2',
    ];
    foreach ($fields as $field) {
      FieldStorageConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'field_name' => $field,
        'bundle' => $this->contentType->id(),
        'label' => $field,
      ])->save();
      EntityFormDisplay::load('node.article.default')
        ->setComponent($field)
        ->save();
    }
  }

  /**
   * Configures the autofill fields.
   */
  protected function configureAutofillFields(): void {
    // Set up the source and target field names as a key/value pair.
    $fields = [
      'field_source_1' => 'field_target_1',
      'field_source_2' => 'field_target_2',
    ];

    // Open the "Manage form display" page.
    $this->drupalGet('admin/structure/types/manage/' . $this->contentType->id() . '/form-display');

    foreach ($fields as $source => $target) {
      // Configure the target fields.
      $this->click('[name="' . $target . '_settings_edit"]');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $autofill_enable_checkbox = $this->assertSession()->elementExists('css', 'input[name="fields[' . $target . '][settings_edit_form][third_party_settings][autofill][enabled]"]');
      $autofill_enable_checkbox->check();
      $this->assertSession()->assertWaitOnAjaxRequest();
      $source_field_select = $this->assertSession()->elementExists('css', 'select[name="fields[' . $target . '][settings_edit_form][third_party_settings][autofill][source_field]"]');

      // Set the source autofill field.
      $source_field_select->selectOption($source);
      $this->getSession()->getPage()->findButton('Update')->click();
      $this->assertSession()->assertWaitOnAjaxRequest();

      // The target field summary should state the source field.
      $target_drupal_selector = 'edit-fields-' . str_replace('_', '-', $target);
      $this->assertSession()->elementTextContains('css', 'tr[data-drupal-selector="' . $target_drupal_selector . '"]', 'Autofill from: ' . $source);
    }

    // Save the configuration.
    $this->getSession()->getPage()->findButton('Save')->click();

  }

}
