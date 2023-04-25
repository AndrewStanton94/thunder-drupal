<?php

namespace Drupal\xymatic_migrate\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Xymatic.
 *
 * @package Drupal\xymatic\Form
 */
class XymaticMigrateSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityTypeManager($container->get('entity_type.manager'));
    return $form;
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xymatics_migrate_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['xymatic_migrate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('xymatic_migrate.settings');

    // Select element with of all media types with source type xymatic.
    $media_types = $this->entityTypeManager
      ->getStorage('media_type')
      ->loadMultiple();
    $options = [];
    foreach ($media_types as $media_type) {
      $options[$media_type->id()] = $media_type->label();
    }

    $form['description'] = [
      '#type' => 'item',
      '#description' => $this->t('Select the media type to migrate away from. This will replace all media entities of this type with new ones. The new media entities will be of the type selected in the Xymatic settings form. This will not delete the old media entities. You will have to do that manually.'),
    ];

    $form['legacy_media_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Legacy Media type'),
      '#options' => $options,
      '#default_value' => $settings->get('legacy_media_type'),
      '#description' => $this->t('The media type to migrate away from.'),
    ];

    $form['legacy_video_id_jsonpath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Legacy video ID JSONPath'),
      '#default_value' => $settings->get('legacy_video_id_jsonpath'),
      '#description' => $this->t('A JsonPath to the legacy video ID. For example: "payload.userMetadata.legacyId"'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('xymatic_migrate.settings')
      ->set('legacy_media_type', $form_state->getValue('legacy_media_type'))
      ->set('legacy_video_id_jsonpath', $form_state->getValue('legacy_video_id_jsonpath'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
