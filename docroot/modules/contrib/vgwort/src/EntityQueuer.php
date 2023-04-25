<?php

namespace Drupal\vgwort;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;

/**
 * Adds eligible entities to the VG Wort queue.
 */
class EntityQueuer {

  /**
   * @var \Drupal\Core\Config\Config
   */
  private Config $config;

  /**
   * @var \Drupal\vgwort\EntityJobMapper
   */
  private EntityJobMapper $entityJobMapper;

  /**
   * Constructs the EntityQueuer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\vgwort\EntityJobMapper $entityJobMapper
   *   The entity job mapper service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityJobMapper $entityJobMapper) {
    $this->config = $configFactory->get('vgwort.settings');
    $this->entityJobMapper = $entityJobMapper;
  }

  /**
   * Adds a registration notification for an entity to the VG wort queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to notify VG Wort about.
   * @param int|null $delay
   *   (optional) Override the default in processing the created queue item.
   *
   * @return bool
   *   TRUE if the entity is in the queue, FALSE if not.
   */
  public function queueEntity(EntityInterface $entity, int $delay = NULL): bool {
    // This service only supports entity types that can be configured in the UI.
    // @see \Drupal\vgwort\Form\SettingsForm::buildForm()
    if (!$entity instanceof EntityPublishedInterface || !$entity instanceof FieldableEntityInterface) {
      return FALSE;
    }

    // Only published entities can be added to the queue.
    if (!$entity->isPublished()) {
      return FALSE;
    }

    // Only entities with a counter ID can be queued.
    if ($entity->vgwort_counter_id->isEmpty()) {
      return FALSE;
    }

    $queue = Queue::load('vgwort');
    // If there is no queue fail silently. This ensures content can be inserted
    // or updated prior to vgwort_post_update_create_queue() running.
    if (!$queue instanceof Queue) {
      return FALSE;
    }

    // Failed jobs should be added to the queue again.
    $job = $this->entityJobMapper->getJob($entity);
    if ($job && $job->getState() === Job::STATE_FAILURE) {
      $queue_backend = $queue->getBackend();
      // Reset the number of retries. As an entity save should cause the process
      // to start again.
      $job->setNumRetries(-1);
      $queue_backend->retryJob($job, $this->config->get('queue_retry_time'));
      return TRUE;
    }
    elseif ($job && in_array($job->getState(), [Job::STATE_SUCCESS, Job::STATE_PROCESSING, Job::STATE_QUEUED], TRUE)) {
      // Nothing to do.
      return TRUE;
    }

    $job = RegistrationNotification::createJob($entity);
    $queue->enqueueJob($job, $delay ?? $this->getDefaultDelay());
    $this->entityJobMapper->merge($job);
    return TRUE;
  }

  /**
   * Gets the default delay before processing a queue item.
   *
   * @return int
   *   The default delay in seconds.
   *
   * @see vgwort.settings:registration_wait_days
   */
  public function getDefaultDelay(): int {
    return $this->config->get('registration_wait_days') * 24 * 60 * 60;
  }

}
