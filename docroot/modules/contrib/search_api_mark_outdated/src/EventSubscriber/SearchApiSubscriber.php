<?php

namespace Drupal\search_api_mark_outdated\EventSubscriber;

use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api_mark_outdated\SearchApiManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes indexed items from the outdated state tracker.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * The search api manager service.
   *
   * @var \Drupal\search_api_mark_outdated\SearchApiManager
   */
  protected $searchApiManager;

  /**
   * Constructs a new search api subscriber.
   *
   * @param \Drupal\search_api_mark_outdated\SearchApiManager $searchApiManager
   *   The current user.
   */
  public function __construct(SearchApiManager $searchApiManager) {
    $this->searchApiManager = $searchApiManager;
  }

  /**
   * Remove indexed items from the outdated state.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The event to process.
   */
  public function onItemsIndexed(ItemsIndexedEvent $event): void {
    /** @var string[] $ids */
    $ids = $event->getProcessedIds();
    $this->searchApiManager->itemsIndexed($event->getIndex(), $ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SearchApiEvents::ITEMS_INDEXED][] = ['onItemsIndexed'];
    return $events;
  }

}
