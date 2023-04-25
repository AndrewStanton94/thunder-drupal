<?php

namespace Drupal\vgwort;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\vgwort\Api\MessageText;
use Drupal\vgwort\Api\NewMessage;
use Drupal\vgwort\Api\Webrange;
use Drupal\vgwort\Exception\NoCounterIdException;
use Drupal\vgwort\Exception\NoParticipantsException;

/**
 * Converts entities to VG Wort messages.
 */
class MessageGenerator {

  /**
   * @var \Drupal\vgwort\ParticipantListManager
   */
  protected ParticipantListManager $participantListManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a MessageGenerator object.
   *
   * @param \Drupal\vgwort\ParticipantListManager $participantListManager
   *   The participant list manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ParticipantListManager $participantListManager, EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    $this->participantListManager = $participantListManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->config = $configFactory->get('vgwort.settings');
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Creates a NewMessage Object for the provided entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to create a new message object for.
   *
   * @return \Drupal\vgwort\Api\NewMessage
   *   The new message object representing the entity.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Throw if the entity does not have a vgwort_counter_id field.
   */
  public function entityToNewMessage(FieldableEntityInterface $entity): NewMessage {
    if (!$entity->hasField('vgwort_counter_id') || $entity->vgwort_counter_id->isEmpty()) {
      throw new NoCounterIdException('Entities must have the vgwort_counter_id in order to generate a VG Wort new massage notification');
    }
    $vgwort_id = $entity->vgwort_counter_id->value;
    $view_mode = $this->config->get("entity_types.{$entity->getEntityTypeId()}.view_mode") ?? 'full';
    $build = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);

    $text = new MessageText(
      $entity->label(),
      (string) $this->renderer->renderPlain($build)
    );
    $participants = $this->participantListManager->getParticipants($entity);
    if (empty($participants)) {
      throw new NoParticipantsException('Entities must have at least one participant in order to generate a VG Wort new massage notification');
    }
    $webranges = [new Webrange([$entity->toUrl()->setAbsolute()->toString()])];
    $legal_rights = $this->config->get('legal_rights');

    // All modules to alter some of the data we send to VG Wort.
    $alter_data = [
      'webranges' => $webranges,
      'legal_rights' => $legal_rights,
      'without_own_participation' => FALSE,
    ];

    $this->moduleHandler->alter('vgwort_new_message', $alter_data, $entity);

    return new NewMessage(
      $vgwort_id,
      $text,
      $participants,
      $alter_data['webranges'],
      $alter_data['legal_rights']['distribution'],
      $alter_data['legal_rights']['public_access'],
      $alter_data['legal_rights']['reproduction'],
      $alter_data['legal_rights']['declaration_of_granting'],
      $alter_data['legal_rights']['other_public_communication'],
      $alter_data['without_own_participation']
    );
  }

}
