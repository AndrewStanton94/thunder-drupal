<?php

namespace Drupal\vgwort\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\vgwort\Api\AgencyParticipant;
use Drupal\vgwort\Api\Participant;
use Drupal\vgwort\Api\PersonParticipant;

/**
 * Defines the 'vgwort_participant_info' entity field type.
 *
 * @FieldType(
 *   id = "vgwort_participant_info",
 *   label = @Translation("VG Wort participant info"),
 *   description = @Translation("A VG Wort participant info."),
 *   default_widget = "vgwort_participant_info",
 *   default_formatter = "vgwort_participant_info",
 * )
 *
 * @see vgwort_entity_base_field_info()
 */
class ParticipantInfo extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'involvement' => Participant::AUTHOR,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['involvement'] = [
      '#type' => 'radios',
      '#options' => [
        Participant::AUTHOR => $this->t('Author(s)'),
        Participant::TRANSLATOR => $this->t('Translator(s)'),
      ],
      '#title' => $this->t('Type of participant'),
      '#description' => $this->t('Describes how the participant is involved.'),
      '#default_value' => $this->getSetting('involvement') ?? Participant::AUTHOR,
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    // @todo decide if this is a number of not. And if we should add some
    //   validation based on the rule:
    //   <xsd:minInclusive value="10" />
    //   <xsd:maxInclusive value="9999999" />
    $properties['card_number'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('Card number')));
    $properties['firstname'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('Firstname')));
    $properties['surname'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('Surname')));
    $properties['agency_abbr'] = DataDefinition::create('string')
      ->setLabel((new TranslatableMarkup('Content agency abbreviation')));

    if (floatval(\Drupal::VERSION) < 10) {
      // On SF5 the allowEmptyString was deprecated and removed SF6. In SF4 the
      // allowEmptyString default was changed from TRUE to FALSE.
      $properties['firstname']->addConstraint('Length', ['min' => 2, 'max' => 40, 'allowEmptyString' => FALSE]);
      $properties['surname']->addConstraint('Length', ['min' => 2, 'max' => 255, 'allowEmptyString' => FALSE]);
      $properties['agency_abbr']->addConstraint('Length', ['min' => 2, 'max' => 4, 'allowEmptyString' => FALSE]);
    }
    else {
      $properties['firstname']->addConstraint('Length', ['min' => 2, 'max' => 40]);
      $properties['surname']->addConstraint('Length', ['min' => 2, 'max' => 255]);
      $properties['agency_abbr']->addConstraint('Length', ['min' => 2, 'max' => 4]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('vgwort_participant_info', []);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName(): ?string {
    return 'firstname';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'card_number' => [
          // @todo Should we store as an integer?
          'type' => 'varchar_ascii',
          // @todo is this sensible?
          'length' => 20,
        ],
        'firstname' => [
          'type' => 'varchar',
          'length' => 40,
        ],
        'surname' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'agency_abbr' => [
          'type' => 'varchar',
          'length' => 4,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $firstname = $this->get('firstname')->getValue();
    $surname = $this->get('surname')->getValue();
    $card_number = $this->get('card_number')->getValue();
    $agency_abbr = $this->get('agency_abbr')->getValue();
    return ($firstname === NULL || $firstname === '') &&
      ($surname === NULL || $surname === '') &&
      ($card_number === NULL || $card_number === '') &&
      ($agency_abbr === NULL || $agency_abbr === '');
  }

  /**
   * Converts a field to a participant object.
   *
   * @return \Drupal\vgwort\Api\Participant
   *   The participant object.
   */
  public function toParticipant(): Participant {
    $involvement = $this->getSetting('involvement');
    $agency_abbr = $this->get('agency_abbr')->getValue();
    if (!($agency_abbr === NULL || $agency_abbr === '')) {
      return new AgencyParticipant($agency_abbr, $involvement);
    }
    return new PersonParticipant($this->get('card_number')->getValue(), $this->get('firstname')->getValue(), $this->get('surname')->getValue(), $involvement);
  }

}
