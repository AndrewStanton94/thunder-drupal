<?php

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\vgwort\Traits\TimePatcher;
use Drupal\user\Entity\User;
use Drupal\vgwort\EntityJobMapper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the vgwort entity queue.
 *
 * @group vgwort
 *
 * @covers \Drupal\vgwort\EntityQueuer
 * @covers \Drupal\vgwort\EntityJobMapper
 */
class EntityQueueTest extends VgWortKernelTestBase {

  /**
   * @var \GuzzleHttp\Handler\MockHandler
   */
  private MockHandler $handler;

  /**
   * {@inheritdoc}
   *
   * @todo Remove once minimum support Drupal version is greater or equal to
   *   10.1. This is fixed by https://drupal.org/i/2350939.
   */
  protected static $modules = ['vgwort_test'];

  /**
   * History of requests/responses.
   *
   * @var array
   */
  protected array $history = [];

  private const JOB_COUNT = [
    Job::STATE_QUEUED => 0,
    Job::STATE_PROCESSING => 0,
    Job::STATE_SUCCESS => 0,
    Job::STATE_FAILURE => 0,
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Set up a mock handler to prevent sending any HTTP requests.
    $this->handler = new MockHandler();
    $history = Middleware::history($this->history);
    $handler_stack = new HandlerStack($this->handler);
    $handler_stack->push($history);
    $client = new Client(['handler' => $handler_stack]);
    $container->set('http_client', $client);
    $container->getDefinition('datetime.time')->setClass(TimePatcher::class);
  }

  /**
   * Use a publishable entity type for testing queueing.
   */
  protected const ENTITY_TYPE = 'entity_test_revpub';

  /**
   * Tests vgwort_entity_insert() and vgwort_entity_update().
   */
  public function testEntityHooks() {
    $queue = Queue::load('vgwort');
    $this->assertInstanceOf(Queue::class, $queue);

    $jobs = self::JOB_COUNT;
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $entity_storage = $this->container->get('entity_type.manager')->getStorage(static::ENTITY_TYPE);
    /** @var \Drupal\entity_test\Entity\EntityTestRevPub $entity */
    $entity = $entity_storage->create([
      'vgwort_test' => [
        'card_number' => '123123123',
        'firstname' => 'Bob',
        'surname' => 'Jones',
        'agency_abbr' => '',
      ],
      'text' => 'Some text',
      'name' => 'A title',
    ]);
    $entity->save();

    $jobs[Job::STATE_QUEUED] = '1';
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('1', $job->getId());
    // Items are queued for 14 days before processing.
    $delay = 14 * 24 * 60 * 60;
    $this->assertGreaterThanOrEqual($this->container->get('datetime.time')->getRequestTime() + $delay, $job->getAvailableTime());

    // Test unpublished entities are not queued.
    /** @var \Drupal\entity_test\Entity\EntityTestRevPub $another_entity */
    $another_entity = $entity_storage->create([
      'vgwort_test' => [
        'card_number' => '123123123',
        'firstname' => 'Bob',
        'surname' => 'Jones',
        'agency_abbr' => '',
      ],
      'text' => 'Some text',
      'name' => 'A title',
    ]);
    $another_entity->setUnpublished();
    $another_entity->save();
    $jobs[Job::STATE_QUEUED] = '1';
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $this->assertNull($this->container->get('vgwort.entity_job_mapper')->getJob($another_entity));

    // Test that once the entity is published it is queued.
    $another_entity->setPublished()->save();
    $jobs[Job::STATE_QUEUED] = '2';
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('2', $job->getId());

    // Test that saving the entity does add another job.
    $another_entity->set('text', 'Edited text')->save();
    $jobs[Job::STATE_QUEUED] = '2';
    $this->assertSame($jobs, $queue->getBackend()->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('2', $job->getId());
    $this->assertGreaterThanOrEqual($this->container->get('datetime.time')->getRequestTime() + (14 * 24 * 60 * 60), $job->getAvailableTime());
  }

  public function testQueueProcessing() {
    // Process queue immediately.
    $this->config('vgwort.settings')->set('registration_wait_days', 0)->save();

    $entity_storage = $this->container->get('entity_type.manager')->getStorage(static::ENTITY_TYPE);
    /** @var \Drupal\entity_test\Entity\EntityTestRevPub $entity */
    $entity = $entity_storage->create([
      'vgwort_test' => [
        'card_number' => '123123123',
        'firstname' => 'Bob',
        'surname' => 'Jones',
        'agency_abbr' => '',
      ],
      'text' => 'Some text',
      'name' => 'A title',
    ]);
    $entity->save();
    $another_entity = $entity_storage->create([
      'vgwort_test' => [
        'card_number' => '43432342',
        'firstname' => 'Alice',
        'surname' => 'Jones',
        'agency_abbr' => '',
      ],
      'text' => 'More text',
      'name' => 'Another title',
    ]);
    $another_entity->save();
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_QUEUED] = '2';
    /** @var \Drupal\advancedqueue\Entity\Queue $queue */
    $queue = Queue::load('vgwort');
    /** @var \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database $queue_backend */
    $queue_backend = $queue->getBackend();
    $this->assertSame($jobs, $queue_backend->countJobs());

    $this->handler->append(new Response());
    $this->handler->append(new Response(500, ['Content-Type' => ['application/json', 'charset=UTF-8']], '{"message":{"errorcode":1,"errormsg":"Privater Identifikationscode: Für den eingegebenen Wert existiert keine Zählmarke."}}'));

    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_SUCCESS] = '1';
    $jobs[Job::STATE_FAILURE] = '1';
    $this->assertSame($jobs, $queue_backend->countJobs());

    // Re-saving the entity should put it in the queue again.
    $another_entity->save();
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_SUCCESS] = '1';
    $jobs[Job::STATE_QUEUED] = '1';
    $this->assertSame($jobs, $queue_backend->countJobs());

    // Fail the job in a way that can be retried.
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_SUCCESS] = '1';
    $jobs[Job::STATE_QUEUED] = '1';
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('2', $job->getId());
    $this->assertSame(0, $job->getNumRetries());

    // Retry for the first time.
    TimePatcher::setPatch(86401);
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('2', $job->getId());
    $this->assertSame('1', $job->getNumRetries());

    // Retry for the second time.
    TimePatcher::setPatch(86401 + 86401);
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('2', $job->getNumRetries());

    // Retry for the third time.
    TimePatcher::setPatch(86401 + 86401 + 86401);
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame($jobs, $queue_backend->countJobs());
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('3', $job->getNumRetries());

    // Fail the job in a way that can be retried again but because max retries
    // has been met it will fail.
    TimePatcher::setPatch(86401 + 86401 + 86401 + 86401);
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertSame(Job::STATE_FAILURE, $job->getState());
    $this->assertSame('3', $job->getNumRetries());
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_SUCCESS] = '1';
    $jobs[Job::STATE_FAILURE] = '1';
    $this->assertSame($jobs, $queue_backend->countJobs());

    // We maintain a map separate from the queue to ensure we know when an
    // entity has been successfully sent to VG Wort.
    $queue_backend->deleteQueue();
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertInstanceOf(Job::class, $job);
    $this->assertSame(Job::STATE_SUCCESS, $job->getState());
    $this->assertSame('1', $job->getId());

    // Since processing $another_entity failed, even though it is in the map,
    // EntityJobMapper::getJob() returns NULL. This ensures it'll be added back
    // to the queue if the entity is saved again.
    $this->assertNull($this->container->get('vgwort.entity_job_mapper')->getJob($another_entity));

    $another_entity->save();
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($another_entity);
    $this->assertInstanceOf(Job::class, $job);
    $this->assertSame(Job::STATE_QUEUED, $job->getState());
    $this->assertSame('3', $job->getId());

    // Ensure the queue and the map are cleaned up when an entity is deleted.
    $another_entity->delete();
    $this->assertNull($this->container->get('vgwort.entity_job_mapper')->getJob($another_entity));
    try {
      $queue_backend->loadJob('3');
      $this->fail('Expected exception not thrown');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('Job with id 3 not found.', $e->getMessage());
    }
    $this->assertFalse($this->entityInTable($another_entity), 'Deleted entity removed from the map');
    $this->assertTrue($this->entityInTable($entity), 'Expected entity in the map');
  }

  public function testNoParticipantQueueProcessing() {
    $user = User::create(['name' => 'test', 'status' => TRUE]);
    $user->save();

    $entity_storage = $this->container->get('entity_type.manager')
      ->getStorage(static::ENTITY_TYPE);
    /** @var \Drupal\entity_test\Entity\EntityTestRevPub $entity */
    $entity = $entity_storage->create([
      'text' => 'Some text',
      'name' => 'A title',
      'user_id' => $user->id(),
    ]);
    $entity->save();
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_QUEUED] = '1';
    /** @var \Drupal\advancedqueue\Entity\Queue $queue */
    $queue = Queue::load('vgwort');
    /** @var \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database $queue_backend */
    $queue_backend = $queue->getBackend();

    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $this->assertSame($jobs, $queue_backend->countJobs());

    // Go 15 days into the future.
    TimePatcher::setPatch(15 * 24 * 60 * 60);
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertSame('The entity entity_test_revpub:1 failed: Entities must have at least one participant in order to generate a VG Wort new massage notification', $job->getMessage());
    $this->assertSame('1', $job->getNumRetries());

    // Go 30 days into the future.
    TimePatcher::setPatch(30 * 24 * 60 * 60);
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertSame('The entity entity_test_revpub:1 failed: Entities must have at least one participant in order to generate a VG Wort new massage notification', $job->getMessage());
    $this->assertSame('2', $job->getNumRetries());

    // Add VG Wort info to user.
    $user->vgwort_test = [
      'card_number' => '45325342',
      'firstname' => 'Simon',
      'surname' => 'George',
      'agency_abbr' => '',
    ];
    $user->save();
    // Reload the entity to update the user entity reference.
    $storage = $this->container->get('entity_type.manager')->getStorage(self::ENTITY_TYPE);
    $storage->resetCache();
    $entity = $storage->load($entity->id());

    // Go 45 days into the future and have a successful POST.
    TimePatcher::setPatch(45 * 24 * 60 * 60);
    $this->handler->append(new Response());
    $this->container->get('advancedqueue.processor')->processQueue($queue);
    $jobs = self::JOB_COUNT;
    $jobs[Job::STATE_SUCCESS] = '1';
    $this->assertSame($jobs, $queue_backend->countJobs());
    $job = $this->container->get('vgwort.entity_job_mapper')->getJob($entity);
    $this->assertNull($job->getMessage());
    $this->assertSame('2', $job->getNumRetries());
  }

  /**
   * Determines if an entity is in the map table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity is in the map table, FALSE if not.
   */
  private function entityInTable(EntityInterface $entity): bool {
    return (bool) $this->container->get('database')->select(EntityJobMapper::TABLE, 'map')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
