<?php

namespace Drupal\vgwort\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'vgwort_participant_info' formatter.
 *
 * @FieldFormatter(
 *   id = "vgwort_participant_info",
 *   label = @Translation("VG Wort Participant info"),
 *   field_types = {
 *     "vgwort_participant_info",
 *   }
 * )
 *
 * @see vgwort_theme()
 */
class ParticipantInfoFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }
    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   Counter ID image generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item): array {
    $agency_abbr = $item->agency_abbr;
    if (!empty($agency_abbr)) {
      return [
        '#plain_text' => $agency_abbr,
      ];
    }
    $card_number = $item->card_number ?? '';
    return [
      '#plain_text' => trim("$card_number $item->firstname $item->surname"),
    ];
  }

}
