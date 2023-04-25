<?php

namespace Drupal\paragraphs_paste;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\paragraphs_features\Ajax\ScrollToElementCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alter the entity form to add access unpublished elements.
 */
class ParagraphsPasteHandler implements ContainerInjectionInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The ParagraphsPaste plugin manager.
   *
   * @var \Drupal\paragraphs_paste\ParagraphsPastePluginManager
   */
  protected $pluginManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\paragraphs_paste\ParagraphsPastePluginManager $pluginManager
   *   The ParagraphsPaste plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ParagraphsPastePluginManager $pluginManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->pluginManager = $pluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.paragraphs_paste.plugin'),
    );
  }

  /**
   * Alter the entity form to add access unpublished elements.
   */
  public function formAlter(array &$form, FormStateInterface $form_state, array $context) {
    // Construct wrapper id.
    $fieldIdPrefix = implode('-', array_merge($form['widget']['#field_parents'], [$form['widget']['#field_name']]));
    $fieldWrapperId = Html::getId($fieldIdPrefix . '-add-more-wrapper');

    $form['add_more']['paragraphs_paste_modal_trigger'] = [
      '#type' => 'button',
      '#name' => $fieldIdPrefix . '_paragraphs_paste_modal_trigger',
      '#value' => $this->t('Create paragraphs'),
      '#attached' => [
        'library' => ['paragraphs_paste/modal'],
      ],
      '#attributes' => [
        'data-paragraphs-paste-target' => $fieldIdPrefix,
      ],
      '#weight' => -1,
    ];

    $form['paragraphs_paste'] = [
      '#type' => 'container',
      '#title' => $this->T('Copy & Paste paragraphs'),
      '#attributes' => [
        'class' => [
          'paragraphs-paste-form',
          'js-hide',
        ],
      ],
    ];

    $form['paragraphs_paste']['help'] = [
      '#type' => 'container',
      'about' => [
        '#type' => 'link',
        '#title' => $this->t('About creating paragraphs by pasting content'),
        '#url' => new Url('paragraphs_paste.help'),
        '#attributes' => ['target' => '_blank'],
      ],
      '#weight' => 0,
    ];

    $form['paragraphs_paste']['textarea'] = [
      '#type' => 'textarea',
      '#name' => $fieldIdPrefix . '_paste_area',
    ];

    $form['paragraphs_paste']['submit'] = [
      '#type' => 'submit',
      '#name' => $fieldIdPrefix . '_paste_submit',
      '#value' => $this->t('Create paragraphs'),
      '#submit' => [[get_class($this), 'pasteSubmit']],
      '#ajax' => [
        'callback' => [get_class($this), 'addMoreAjax'],
        'wrapper' => $fieldWrapperId,
      ],
      '#limit_validation_errors' => [['paragraphs_paste']],
    ];
  }

  /**
   * Submit callback.
   */
  public static function pasteSubmit(array $form, FormStateInterface $form_state) {
    $submit = self::getSubmitElementInfo($form, $form_state);
    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $form_state->getFormObject();
    $host = $form_object->getEntity();

    $settings = $form_object->getFormDisplay($form_state)
      ->getComponent($submit['field_name'])['third_party_settings']['paragraphs_paste'];

    $input = $form_state->getUserInput();
    $parents = array_merge(
      array_slice($submit['button']['#parents'], 0, -1),
      [$submit['field_name'] . '_paste_area'],
    );

    $pasted_data = NestedArray::getValue($input, $parents);

    // Split by RegEx pattern.
    $pattern = self::buildRegExPattern($settings);
    $data = preg_split($pattern, $pasted_data, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    $items = self::traverseData($data, $settings['property_path_mapping']);

    $submit['widget_state']['real_items_count'] = $submit['widget_state']['real_items_count'] ?? 0;
    $submit['widget_state']['items_count'] = $submit['widget_state']['items_count'] ?? 0;
    foreach ($items as $item) {
      if ($item->plugin instanceof ParagraphsPastePluginBase) {
        $paragraph_entity = $item->plugin->createParagraphEntity($item->value);
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
        $paragraph_entity->setParentEntity($host, $submit['field_name']);
        $submit['widget_state']['paragraphs'][] = [
          'entity' => $paragraph_entity,
          'display' => 'default',
          'mode' => 'edit',
        ];
        $submit['widget_state']['real_items_count']++;
        $submit['widget_state']['items_count']++;
      }
    }

    ParagraphsWidget::setWidgetState($submit['parents'], $submit['field_name'], $form_state, $submit['widget_state']);
    $form_state->setRebuild();
  }

  /**
   * Add more callback.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    /* @see ParagraphsWidget::getSubmitElementInfo() */
    $submit = self::getSubmitElementInfo($form, $form_state);
    $element = $submit['element'];

    // Add a DIV around the delta receiving the Ajax effect.
    $delta = $submit['element']['#max_delta'];
    $element[$delta]['#prefix'] = '<div class="ajax-new-content">' . (isset($element[$delta]['#prefix']) ? $element[$delta]['#prefix'] : '');
    $element[$delta]['#suffix'] = (isset($element[$delta]['#suffix']) ? $element[$delta]['#suffix'] : '') . '</div>';

    // Clear the Add more delta.
    NestedArray::setValue(
      $element,
      ['add_more', 'add_modal_form_area', 'add_more_delta', '#value'],
      ''
    );

    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $element));
    if (class_exists('Drupal\paragraphs_features\Ajax\ScrollToElementCommand')) {
      $response->addCommand(new ScrollToElementCommand($element[$element['#max_delta']]['#attributes']['data-drupal-selector'], $element['#attributes']['data-drupal-selector']));
    }

    return $response;
  }

  /**
   * Get common submit element information for processing ajax submit handlers.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Submit element information.
   */
  public static function getSubmitElementInfo(array $form, FormStateInterface $form_state) {
    /* @see ParagraphsWidget::getSubmitElementInfo() */
    $submit['button'] = $form_state->getTriggeringElement();
    $submit['element'] = NestedArray::getValue($form, array_merge(array_slice($submit['button']['#array_parents'], 0, -2), ['widget']));
    $submit['field_name'] = $submit['element']['#field_name'];
    $submit['parents'] = $submit['element']['#field_parents'];

    // Get widget state.
    $submit['widget_state'] = ParagraphsWidget::getWidgetState($submit['parents'], $submit['field_name'], $form_state);

    return $submit;
  }

  /**
   * Traverse pasted data.
   *
   * @param array $data
   *   Pasted data.
   * @param array $property_path_mapping
   *   Property path mapping.
   *
   * @return array
   *   Enriched data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public static function traverseData(array $data, array $property_path_mapping) {
    /** @var \Drupal\paragraphs_paste\ParagraphsPastePluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.paragraphs_paste.plugin');
    $results = [];

    // Enrich pasted data with plugins.
    foreach ($data as $value) {
      if ($plugins = $plugin_manager->getPluginsFromInput($value)) {

        // Filter out plugins without property path.
        $plugins = array_filter($plugins, function ($plugin) use ($property_path_mapping) {
          return !empty($property_path_mapping[$plugin['id']]);
        });

        // Sort definitions / candidates by weight.
        uasort($plugins, [SortArray::class, 'sortByWeightElement']);

        $plugin_id = end($plugins)['id'];
        $plugin = $plugin_manager->createInstance($plugin_id, ['property_path' => $property_path_mapping[$plugin_id]]);
        $results[] = (object) ['plugin' => $plugin, 'value' => $value];
      }
    }

    return $results;
  }

  /**
   * Get 3rd party setting form for paragraphs paste.
   *
   * @param \Drupal\Core\Field\WidgetInterface $plugin
   *   Widget plugin.
   * @param string $field_name
   *   Field name.
   *
   * @return array
   *   Returns 3rd party form elements.
   */
  public static function getThirdPartyForm(WidgetInterface $plugin, $field_name) {
    $elements = [
      '#type' => 'fieldset',
      '#title' => t('Paragraphs Paste'),
    ];
    $elements['enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable Copy & Paste area'),
      '#default_value' => $plugin->getThirdPartySetting('paragraphs_paste', 'enabled'),
    ];

    $visibility_rule = [":input[name=\"fields[$field_name][settings_edit_form][third_party_settings][paragraphs_paste][enabled]\"]" => ['checked' => TRUE]];

    $elements['property_path_mapping'] = [
      '#type' => 'fieldset',
      '#title' => t('Copy & Paste mapping'),
      '#description' => t('Specify a property path in the pattern of {entity_type}.{bundle}.{field_name} or {entity_type}.{bundle}.{entity_reference_field_name}:{referenced_entity_bundle}.{field_name} (Use arrow keys to navigate available options)'),
      '#states' => ['visible' => $visibility_rule],
    ];

    /** @var \Drupal\paragraphs_paste\ParagraphsPastePluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.paragraphs_paste.plugin');
    foreach ($plugin_manager->getDefinitions() as $definition) {
      $property_path_mapping = $plugin->getThirdPartySetting('paragraphs_paste', 'property_path_mapping');
      $elements['property_path_mapping'][$definition['id']] = [
        '#type' => 'textfield',
        '#title' => $definition['label'],
        '#description' => $definition['class']::getSummaryItem(),
        '#autocomplete_route_name' => 'paragraphs_paste.autocomplete.property_path',
        '#autocomplete_route_parameters' => ['allowed_field_types' => $definition['allowed_field_types']],
        '#default_value' => !empty($property_path_mapping[$definition['id']]) ? $property_path_mapping[$definition['id']] : '',
      ];
    }

    $elements['custom_split_method'] = [
      '#type' => 'checkbox',
      '#title' => t('Use a custom regex for splitting content.'),
      '#default_value' => !empty($plugin->getThirdPartySetting('paragraphs_paste', 'custom_split_method')),
      '#states' => ['visible' => $visibility_rule],
    ];

    $elements['custom_split_method_regex'] = [
      '#type' => 'textfield',
      '#description' => t('Define when new paragraphs should be created.'),
      '#default_value' => $plugin->getThirdPartySetting('paragraphs_paste', 'custom_split_method_regex'),
      '#states' => [
        'visible' => $visibility_rule,
        'required' => [":input[name=\"fields[$field_name][settings_edit_form][third_party_settings][paragraphs_paste][custom_split_method]\"]" => ['checked' => TRUE]],
      ],
      '#element_validate' => [[__CLASS__, 'validateRegEx']],
    ];

    return $elements;
  }

  /**
   * Validates the regex field element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateRegEx(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $enabled = $form_state->getValue(array_merge(array_slice($element['#parents'], 0, -1), ['custom_split_method']));
    $regex = $form_state->getValue($element['#parents']);
    if ($enabled && (empty($regex) || preg_match("/$regex/", NULL) === FALSE)) {
      $form_state->setError($element, t('A RegEx needs to be defined or is invalid.'));
    }
  }

  /**
   * Build regex pattern based on config settings.
   *
   * @param array $settings
   *   The form settings.
   *
   * @return string
   *   The regex pattern.
   */
  public static function buildRegExPattern(array $settings) {
    $parts = [];

    if ($settings['custom_split_method'] && !empty($settings['custom_split_method_regex'])) {
      $parts[] = $settings['custom_split_method_regex'];
    }
    else {
      $parts[] = "(?:\r\n *|\n *){3,}";
    }

    return '~(' . implode('|', $parts) . ')~';
  }

}
