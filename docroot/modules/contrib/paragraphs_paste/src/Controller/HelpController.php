<?php

namespace Drupal\paragraphs_paste\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs_paste\ParagraphsPastePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for filter routes.
 */
class HelpController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The ParagraphsPaste plugin manager.
   *
   * @var \Drupal\paragraphs_paste\ParagraphsPastePluginManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $controller = new static();
    $controller->setPluginManager($container->get('plugin.manager.paragraphs_paste.plugin'));
    return $controller;
  }

  /**
   * Set the ParagraphsPaste plugin manager.
   *
   * @param \Drupal\paragraphs_paste\ParagraphsPastePluginManager $pluginManager
   *   The ParagraphsPaste plugin manager service.
   */
  protected function setPluginManager(ParagraphsPastePluginManager $pluginManager) {
    $this->pluginManager = $pluginManager;
  }

  /**
   * Displays a page for paragraphs paste help.
   *
   * @return array
   *   A renderable array.
   */
  public function pageHelp() {

    $build = [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>' . $this->t('Insert multiple paragraphs at once by pasting content using urls or special keywords. Double newline will create a new paragraph.') . '</p>',
      ],
    ];

    foreach ($this->pluginManager->getDefinitions() as $definition) {
      if ($guidelines = $definition['class']::buildGuidelines()) {
        $build[$definition['id']] = [
          'label' => [
            '#markup' => '<h2>Plugin ' . $definition['label'] . '</h2>',
          ],
          'guidelines' => $guidelines,
        ];
      }
    }

    return $build;
  }

}
