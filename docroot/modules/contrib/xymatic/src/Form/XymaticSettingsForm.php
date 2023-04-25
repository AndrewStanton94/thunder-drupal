<?php

namespace Drupal\xymatic\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Xymatic.
 *
 * @package Drupal\xymatic\Form
 */
class XymaticSettingsForm extends ConfigFormBase {

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
    return 'xymatics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['xymatic.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('xymatic.settings');

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Xymatic credentials'),
      '#open' => TRUE,
    ];

    $form['credentials']['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API URL'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('api_url'),
      '#description' => $this->t('Xymatic endpoint URL.'),
    ];

    $form['credentials']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('api_key'),
      '#description' => $this->t('The API key.'),
    ];

    $form['credentials']['license_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License key'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('license_key'),
      '#description' => $this->t('The license key.'),
    ];

    $form['webhook'] = [
      '#type' => 'details',
      '#title' => $this->t('Webhook settings'),
      '#description' => $this->t('The webhook is triggered when a video is uploaded to Xymatic. It will be sent to the following URL: <code>@url</code>', [
        '@url' => Url::fromRoute('xymatic.webhook', [], ['absolute' => TRUE])
          ->toString(),
      ]),
      '#open' => TRUE,
    ];

    $form['webhook']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('access_token'),
      '#description' => $this->t('The access token needs to be sent as X-Xymatic-Token header.'),
    ];

    // Select element with of all media types with source type xymatic.
    $media_types = $this->entityTypeManager
      ->getStorage('media_type')
      ->loadByProperties(['source' => 'xymatic']);
    $options = [];
    foreach ($media_types as $media_type) {
      $options[$media_type->id()] = $media_type->label();
    }
    $form['webhook']['media_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Media type'),
      '#options' => $options,
      '#default_value' => $settings->get('media_type'),
      '#description' => $this->t('The media type to use when creating a new media entity.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('xymatic.settings')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('license_key', $form_state->getValue('license_key'))
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('media_type', $form_state->getValue('media_type'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
