<?php

namespace Drupal\vgwort\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobTypeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vgwort\EntityQueuer;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;
use Drush\Commands\DrushCommands;

/**
 * VGWort Drush commands.
 */
class VgwortCommands extends DrushCommands {
  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\vgwort\EntityQueuer
   */
  protected EntityQueuer $entityQueuer;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected MemoryCacheInterface $entityMemoryCache;

  /**
   * @var \Drupal\advancedqueue\JobTypeManager
   */
  protected JobTypeManager $jobTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\vgwort\EntityQueuer $entityQueuer
   *   The VG Wort entity queuer.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $entityMemoryCache
   *   The entity memory cache.
   * @param \Drupal\advancedqueue\JobTypeManager $jobTypeManager
   *   The advanced queue job type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityQueuer $entityQueuer, TimeInterface $time, MemoryCacheInterface $entityMemoryCache, JobTypeManager $jobTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityQueuer = $entityQueuer;
    $this->time = $time;
    $this->entityMemoryCache = $entityMemoryCache;
    $this->jobTypeManager = $jobTypeManager;
  }

  /**
   * Sends a single entity to VG Wort.
   *
   * @param string $entityType
   *   The entity type, for example, 'node'.
   * @param string $entityId
   *   The entity ID, for example, '1'.
   *
   * @usage vgwort:send node 3
   *   Sends node 3 to VG Wort.
   *
   * @command vgwort:send
   * @aliases vgws
   * @validate-vgwort-entity-type
   * @validate-vgwort-entity-id
   */
  public function sendToVGWort(string $entityType, string $entityId) {
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    $job = RegistrationNotification::createJob($entity);
    /** @var \Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification $job_type */
    $job_type = $this->jobTypeManager->createInstance($job->getType());
    $job_result = $job_type->process($job);
    if ($job_result->getState() !== Job::STATE_SUCCESS) {
      $this->io()->error(\dt('Sending failed: @message.', ['@message' => $job_result->getMessage()]));
      return self::EXIT_FAILURE;
    }
    $this->io()->success(\dt('Sending complete.'));
    return self::EXIT_SUCCESS;
  }

  /**
   * Adds existing content to VGWort queue.
   *
   * @param string $entityType
   *   Entity type to process
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @option batch-size
   *   How many entities to process in each batch invocation. Defaults to 50.
   *
   * @usage vgwort:queue node
   *   Adds nodes to queue.
   *
   * @command vgwort:queue
   * @aliases vgwq
   * @validate-vgwort-entity-type
   * @validate-vgwort-batch-size
   *
   * NOTE: this command will appear in the _global namespace until there is more
   * than one command available.
   */
  public function addExistingContentToQueue(string $entityType, $options = ['batch-size' => 50]): bool {
    $batch = new BatchBuilder();
    $batch->addOperation([$this, 'batchAddExistingContentToQueue'], [$entityType, $options['batch-size']]);
    batch_set($batch->toArray());
    $result = drush_backend_batch_process();

    $success = self::EXIT_FAILURE;
    if (!is_array($result)) {
      $this->logger()->error(dt('Batch process did not return a result array. Returned: !type', ['!type' => gettype($result)]));
    }
    elseif (!empty($result[0]['#abort'])) {
      // Whenever an error occurs the batch process does not continue, so
      // this array should only contain a single item, but we still output
      // all available data for completeness.
      $this->logger()->error(dt('Update aborted by: !process', [
        '!process' => implode(', ', $result[0]['#abort']),
      ]));
    }
    else {
      $success = self::EXIT_SUCCESS;
    }

    return $success;
  }

  /**
   * Batch callback.
   *
   * @param $entity_type
   *   The entity type to process.
   * @param int $batch_size
   *   How many entities to process at a time.
   * @param $context
   *   The batch context.
   */
  public function batchAddExistingContentToQueue($entity_type, int $batch_size, &$context) {
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entity_type = $this->entityTypeManager->getDefinition($entity_type);
    $id_key = $entity_type->getKey('id');

    if (!isset($context['sandbox']['@processed'])) {
      $context['sandbox']['@processed'] = $context['sandbox']['@queued'] = 0;

      $count_query = $entity_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->count();
      $context['sandbox']['@total'] = (int) $count_query->execute();

      if ($context['sandbox']['@total'] === 0) {
        // If there are no entities to process, then stop immediately.
        $context['finished'] = 1;
        return;
      }
    }

    $query = $entity_storage->getQuery();
    if (isset($context['sandbox']['last_id'])) {
      $query->condition($id_key, $context['sandbox']['last_id'], '>');
    }

    $entities = $entity_storage->loadMultiple($query->accessCheck(FALSE)->sort($id_key)->range(0, $batch_size)->execute());
    if (empty($entities)) {
      // If there are no entities to process, then stop immediately.
      $context['finished'] = 1;
      return;
    }

    foreach ($entities as $entity) {
      // @todo Should we add some jitter so that all the existing content is
      //   available for claiming immediately? Or should be avoid the queue
      //   completely and just send straight to VG Wort?
      if ($entity instanceof EntityChangedInterface) {
        // Work out the delay based on the last changed time. Entities older
        // than the default delay will have a delay of 0.
        $delay = $entity->getChangedTime() + $this->entityQueuer->getDefaultDelay() - $this->time->getCurrentTime();
        $queued = $this->entityQueuer->queueEntity($entity, max($delay, 0));
      }
      else {
        $queued = $this->entityQueuer->queueEntity($entity);
      }

      if ($queued) {
        $context['sandbox']['@queued']++;
      }
      $context['sandbox']['@processed']++;
    }

    // Clear entity memory cache to prevent out-of-memory issues.
    if ($entity_type->isStaticallyCacheable()) {
      $this->entityMemoryCache->deleteAll();
    }
    $context['sandbox']['last_id'] = $entity->id();

    $context['finished'] = min(1, $context['sandbox']['@processed'] / $context['sandbox']['@total']);
    $context['results']['total'] = $context['sandbox']['@total'];
    // Optional message displayed under the progressbar.
    $context['message'] = dt('Processed @processed out of @total entities, added @queued to the queue', $context['sandbox']);
  }

  /**
   * Validates an entity type has VG Wort enabled.
   *
   * @hook validate @validate-vgwort-entity-type
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateEntityType(CommandData $commandData): ?CommandError {
    $entityType = $commandData->input()->getArgument('entityType');
    if (!_vgwort_entity_type_has_counter_id($entityType)) {
      return new CommandError(dt('Entity type "@entityType" is not configured for VG Wort', ['@entityType' => $entityType]));
    }
    return NULL;
  }

  /**
   * Validates an entity ID exists.
   *
   * @hook validate @validate-vgwort-entity-id
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateEntityID(CommandData $commandData): ?CommandError {
    $entityType = $commandData->input()->getArgument('entityType');
    $entityId = $commandData->input()->getArgument('entityId');
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if ($entity === NULL) {
      return new CommandError(dt('The entity @entityType:@entityId does not exist.', ['@entityType' => $entityType, '@entityId' => $entityId]));
    }
    return NULL;
  }

  /**
   * Validates the batch size is a positive integer.
   *
   * @hook validate @validate-vgwort-batch-size
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateBatchSize(CommandData $commandData): ?CommandError {
    $options = $commandData->options();
    if (isset($options['batch-size'])) {
      $validatedValue = filter_var($options['batch-size'], FILTER_VALIDATE_INT);
      if (!$validatedValue || $validatedValue < 1) {
        return new CommandError(dt('The batch-size "@batchSize" is not valid. It must be an integer and greater than 0.', ['@batchSize' => $options['batch-size']]));
      }
    }
    return NULL;
  }

}
