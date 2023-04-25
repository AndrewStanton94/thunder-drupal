<?php

namespace Drupal\vgwort;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * @var \Drupal\vgwort\ParticipantListManager
   */
  private ParticipantListManager $participantListManager;

  /**
   * Constructs the ConfigSubscriber.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, ParticipantListManager $participantListManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->participantListManager = $participantListManager;
  }

  /**
   * Rebuilds the router when the default or admin theme is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'vgwort.settings' && $event->isChanged('entity_types')) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
      $this->participantListManager->clearCachedDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }

}
