<?php

namespace Drupal\vgwort\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Participant annotation object.
 *
 * @ingroup vgwort_api
 *
 * @Annotation
 */
class ParticipantList extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The administrative label of the participant list.
   *
   * @var \Drupal\Core\Annotation\Translation|\Drupal\Core\StringTranslation\TranslatableMarkup|string
   *
   * @ingroup plugin_translatable
   */
  public $admin_label = '';

}
