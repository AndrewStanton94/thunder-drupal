<?php

namespace Drupal\Tests\paragraphs_paste\FunctionalJavascript;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\dblog\Controller\DbLogController;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\LoginAdminTrait;

/**
 * Base class for Javascript tests for paragraphs_paste module.
 *
 * @package Drupal\Tests\paragraphs_paste\FunctionalJavascript
 */
abstract class ParagraphsPasteJavascriptTestBase extends WebDriverTestBase {

  use LoginAdminTrait;
  use ParagraphsPasteJavascriptTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'paragraphs_paste_test',
    'field_ui',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void  {
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = \Drupal::database()->select('watchdog', 'w')
      ->fields('w', ['message', 'variables']);
    $andGroup = $query->andConditionGroup()
      ->condition('severity', 6, '<')
      ->condition('type', 'php');
    $group = $query->orConditionGroup()
      ->condition('severity', 4, '<')
      ->condition($andGroup);
    $query->condition($group);
    $query->groupBy('w.message');
    $query->groupBy('w.variables');

    $controller = DbLogController::create($this->container);

    // Check that there are no warnings in the log after installation.
    // $this->assertEqual($query->countQuery()->execute()->fetchField(), 0);.
    if ($query->countQuery()->execute()->fetchField()) {
      // Output all errors for modules tested.
      $errors = [];
      foreach ($query->execute()->fetchAll() as $row) {
        $errors[] = Unicode::truncate(Html::decodeEntities(strip_tags($controller->formatMessage($row))), 256, TRUE, TRUE);
      }
      throw new \Exception(print_r($errors, TRUE));
    }

    parent::tearDown();
  }

}
