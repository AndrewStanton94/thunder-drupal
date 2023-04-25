<?php

namespace Drupal\vgwort;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsDeletingJobsInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsLoadingJobsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;

/**
 * Maintains the maps between entities and jobs.
 *
 * The map allows the VG Wort module to know whether an entity has been
 * successfully posted to VG Wort even after the queue has been cleaned.
 */
final class EntityJobMapper {

  /**
   * The table name for the map.
   */
  public const TABLE = 'vgwort_entity_registration';

  /**
   * The database connection used to check the IP against.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a EntityJobMapper object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to check the IP against.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Inserts or updates a row in the map.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job to insert or update.
   * @param int|null $success_timestamp
   *   (optional) The timestamp to set success_timestamp to. Defaults to NULL.
   *
   * @return $this
   *   The entity job mapper.
   */
  public function merge(Job $job, ?int $success_timestamp = NULL): self {
    [$entity_type, $entity_id] = RegistrationNotification::getEntityInfoFromJob($job);
    $this->connection->merge(self::TABLE)
      ->keys(['entity_type' => $entity_type, 'entity_id' => $entity_id])
      ->fields(['job_id' => $job->getId(), 'success_timestamp' => $success_timestamp])
      ->execute();
    return $this;
  }

  /**
   * Gets a Job object for entity.
   *
   * If the entity has a job on the queue the job object return will be loaded
   * from the queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get a job for.
   *
   * @return \Drupal\advancedqueue\Job|null
   *   The job object. NULL if a new job needs to be queued.
   */
  public function getJob(EntityInterface $entity): ?Job {
    // The table might not yet exist.
    $result = $this->connection->select(self::TABLE, 'map')
      ->fields('map', ['entity_type', 'entity_id', 'job_id', 'success_timestamp'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchObject();

    if (empty($result)) {
      return NULL;
    }

    $queue = Queue::load('vgwort');
    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof SupportsLoadingJobsInterface) {
      try {
        return $queue_backend->loadJob($result->job_id);
      }
      catch (\InvalidArgumentException $e) {
        // Fall through to the last return.
      }
    }

    if ((int) $result->success_timestamp > 0) {
      $job = RegistrationNotification::createJob($entity);
      $job->setId($result->job_id);
      $job->setState(Job::STATE_SUCCESS);
      $job->setProcessedTime($result->success_timestamp);
      return $job;
    }

    return NULL;
  }

  /**
   * Cleans up when an entity is deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove from the map and queue.
   *
   * @return $this
   *   The entity job mapper.
   */
  public function removeEntity(EntityInterface $entity): self {
    $result = $this->connection->select(self::TABLE, 'map')
      ->fields('map', ['job_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchObject();

    if (empty($result)) {
      return $this;
    }

    $queue = Queue::load('vgwort');
    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof SupportsDeletingJobsInterface) {
      $queue_backend->deleteJob($result->job_id);
    }

    $this->connection->delete(self::TABLE)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
    return $this;
  }

  /**
   * Gets the schema definition for the map table.
   *
   * If this specification changes vgwort_update_20001() will need to be
   * updated to have the original spec and a new update function should be
   * added.
   *
   * @return array
   *   The schema definition.
   *
   * @see vgwort_update_20001()
   */
  public static function schemaDefinition(): array {
    return [
      'description' => 'Stores map from entity to job ID for registration notification.',
      'fields' => [
        'entity_type' => [
          'type' => 'varchar_ascii',
          'length' => EntityTypeInterface::ID_MAX_LENGTH,
          'not null' => TRUE,
          'description' => 'The entity type.',
        ],
        'entity_id' => [
          // Support string entity IDs
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The entity ID.',
        ],
        'job_id' => [
          'type' => 'int',
          'size' => 'big',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'The job ID.',
        ],
        'success_timestamp' => [
          'description' => 'The Unix timestamp when this entity was successfully post to VG Wort.',
          'type' => 'int',
          'size' => 'big',
          'unsigned' => TRUE,
        ],
      ],
      'primary key' => ['entity_type', 'entity_id'],
      'indexes' => [
        'job_id' => ['job_id'],
      ],
    ];
  }

}
