<?php

namespace Drupal\search_api_mark_outdated\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\SearchApiHandlerTrait;
use Drupal\search_api_mark_outdated\SearchApiManager;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field that indicates if a search result item might be outdated.
 *
 * @ViewsField("search_api_mark_outdated_state_field")
 */
class SearchApiStateField extends FieldPluginBase {

  use SearchApiHandlerTrait;

  /**
   * The search api mark outdated manager.
   *
   * @var \Drupal\search_api_mark_outdated\SearchApiManager
   */
  protected $searchApiManager;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $field = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $field->setSearchApiManager($container->get('search_api_mark_outdated.manager'));

    return $field;
  }

  /**
   * Set the search api mark outdated manager.
   *
   * @param \Drupal\search_api_mark_outdated\SearchApiManager $searchApiManager
   *   The manager service.
   */
  protected function setSearchApiManager(SearchApiManager $searchApiManager): void {
    $this->searchApiManager = $searchApiManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['label']['default'] = '';
    $options['add_row_class']['default'] = TRUE;

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['add_row_class'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add row class'),
      '#default_value' => $this->options['add_row_class'],
      '#description' => $this->t('Add a row class to indicate that the result item is outdated.'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function render(ResultRow $row): array {
    $element = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'data-is-outdated' => (int) $this->searchApiManager->isOutdated($this->getIndex(), $row->search_api_id),
      ],
    ];

    if ($this->options['add_row_class']) {
      $element['#attached'] = [
        'library' => [
          'search_api_mark_outdated/row-class',
        ],
      ];
    }

    return $element;
  }

}
