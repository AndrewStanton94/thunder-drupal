<?php

namespace Drupal\vgwort\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'vgwort_participant_info' widget.
 *
 * @FieldWidget(
 *   id = "vgwort_participant_info",
 *   label = @Translation("VGWort participant info"),
 *   field_types = {
 *     "vgwort_participant_info"
 *   }
 * )
 */
class ParticipantInfoWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'agency_abbr' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['agency_abbr'] = [
      '#type' => 'checkbox',
      // @todo Improve this label.
      '#title' => $this->t('Support agency abbreviations.'),
      '#default_value' => $this->getSetting('agency_abbr'),
      '#weight' => -1,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $agency_abbr = $this->getSetting('agency_abbr');
    $summary[] = $this->t('Allow Agency abbreviations: @agency_abbr', ['@agency_abbr' => ($agency_abbr ? $this->t('Yes') : $this->t('No'))]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    // Wrap all the elements with a fieldset.
    $element['#theme_wrappers'][] = 'fieldset';

    $agency_abbr = $items[$delta]->agency_abbr ?? NULL;

    if ($this->getSetting('agency_abbr')) {
      $element['type'] = [
        '#type' => 'radios',
        '#options' => [
          'person' => $this->t('Person'),
          'agency' => $this->t('Agency'),
        ],
        '#title' => $this->t('Type of participant'),
        '#default_value' => ($agency_abbr === NULL || $agency_abbr === '') ? 'person' : 'agency',
      ];
    }
    else {
      $element['type'] = [
        '#type' => 'hidden',
        '#value' => 'person',
      ];
    }

    $type_field_name = $this->fieldDefinition->getName() . '[' . $delta . '][type]';

    $element['card_number'] = [
      '#title' => $this->t('Card number'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->card_number ?? NULL,
      '#size' => 20,
      // '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => 20,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#states' => [
        'visible' => [
          ':input[name="' . $type_field_name . '"]' => ['value' => 'person'],
        ],
      ],
    ];

    $element['firstname'] = [
      '#title' => $this->t('Firstname'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->firstname ?? NULL,
      '#size' => 20,
      '#maxlength' => 40,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#states' => [
        'visible' => [
          ':input[name="' . $type_field_name . '"]' => ['value' => 'person'],
        ],
      ],
    ];

    $element['surname'] = [
      '#title' => $this->t('Surname'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->surname ?? NULL,
      '#size' => 20,
      '#maxlength' => 255,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#states' => [
        'visible' => [
          ':input[name="' . $type_field_name . '"]' => ['value' => 'person'],
        ],
      ],
    ];

    $element['agency_abbr'] = [
      '#title' => $this->t('Agency abbreviation'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->agency_abbr ?? NULL,
      '#size' => 4,
      '#maxlength' => 4,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#states' => [
        'visible' => [
          ':input[name="' . $type_field_name . '"]' => ['value' => 'agency'],
        ],
      ],
      '#access' => $this->getSetting('agency_abbr'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      if ($value['type'] === 'person') {
        $value['agency_abbr'] = NULL;
      }
      else {
        $value['card_number'] = NULL;
        $value['firstname'] = NULL;
        $value['surname'] = NULL;
      }
      if ($value['card_number'] === '') {
        $value['card_number'] = NULL;
      }
      unset($value['type']);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    // Move the errors to the subfields if possible.
    $property_path = explode('.', $error->getPropertyPath());
    $sub_field = array_pop($property_path);
    if (isset($element[$sub_field])) {
      return $element[$sub_field];
    }
    return $element;
  }

}
