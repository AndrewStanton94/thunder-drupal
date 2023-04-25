<?php

namespace Drupal\entity_reference_actions\EventSubscriber;

use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Drupal\entity_reference_actions\Render\EmptyAttachmentsProcessor;

/**
 * Decorator for AjaxResponseSubscriber.
 *
 * For subrequests from the ERA module we don't want to process attachments,
 * because they are processed later in the main request.
 */
class SubRequestAjaxResponseSubscriber extends AjaxResponseSubscriber {

  protected $ajaxResponseSubscriber;

  /**
   * {@inheritdoc}
   */
  public function __construct(AjaxResponseSubscriber $ajax_response_subscriber) {
    $this->ajaxResponseSubscriber = $ajax_response_subscriber;
  }

  /**
   * {@inheritdoc}
   */
  public function onResponse(ResponseEvent $event): void {
    $processor = $this->ajaxResponseSubscriber->ajaxResponseAttachmentsProcessor;
    if (!$event->isMainRequest() && $event->getRequest()->query->get('era_subrequest')) {
      $this->ajaxResponseSubscriber->ajaxResponseAttachmentsProcessor = new EmptyAttachmentsProcessor();
    }
    $this->ajaxResponseSubscriber->onResponse($event);
    $this->ajaxResponseSubscriber->ajaxResponseAttachmentsProcessor = $processor;

  }

}
