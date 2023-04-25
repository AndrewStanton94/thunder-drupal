<?php

namespace Drupal\Tests\vgwort\Kernel;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the vgwort message generator service.
 *
 * @group vgwort
 *
 * @covers \Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification
 */
class RegistrationNotificationJobTypeTest extends VgWortKernelTestBase {
  use PrettyJsonTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['advancedqueue'];

  /**
   * @var \GuzzleHttp\Handler\MockHandler
   */
  private MockHandler $handler;

  /**
   * History of requests/responses.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected EntityTest $entity;

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
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->create([
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
    $this->entity = $entity;
  }

  private function processJob(Job $job) : JobResult {
    /** @var \Drupal\vgwort\Plugin\AdvancedQueue\JobType\RegistrationNotification $processor */
    $processor = $this->container->get('plugin.manager.advancedqueue_job_type')->createInstance('vgwort_registration_notification');
    return $processor->process($job);
  }

  public function testInvalidPayload() {
    $job = Job::create('vgwort_registration_notification', ['entity_type' => 'entity_test']);
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_FAILURE, $result->getState());
    $this->assertSame('Missing entity_type or entity_id from the payload', (string) $result->getMessage());
    $this->assertEmpty($this->history);
  }

  public function testMissingEntity() {
    $job = Job::create('vgwort_registration_notification', ['entity_type' => 'entity_test', 'entity_id' => 12341243]);
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_FAILURE, $result->getState());
    $this->assertSame('The entity entity_test:12341243 does not exist', (string) $result->getMessage());
    $this->assertEmpty($this->history);
  }

  public function testEntityWithoutCounterId() {
    $this->enableModules(['vgwort_test']);
    $this->container->get('state')->set('vgwort_test_vgwort_enable_for_entity', [$this->entity->id()]);
    $this->container->get('entity_type.manager')->getStorage('entity_test')->resetCache();
    $job = RegistrationNotification::createJob($this->entity);
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_FAILURE, $result->getState());
    $this->assertSame('The entity entity_test:1 failed: Entities must have the vgwort_counter_id in order to generate a VG Wort new massage notification', (string) $result->getMessage());
    $this->assertEmpty($this->history);
  }

  public function testMissingCredentials() {
    $this->handler->append(new Response(401, ['Content-Type' => ['text/html', 'charset=UTF-8']], '<html><head><title>Error</title></head><body>Unauthorized</body></html>'));

    $job = RegistrationNotification::createJob($this->entity);
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_FAILURE, $result->getState());
    $this->assertSame('The request to VG Wort failed (status code: 401). Error message: Unauthorized', $result->getMessage());
    $this->assertCount(1, $this->history);
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $this->history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    // This is broken we have no values for username or password.
    $this->assertSame('Basic Og==', $request->getHeader('Authorization')[0]);
  }

  public function testSuccess() {
    $this->config('vgwort.settings')
      ->set('username', 'username')
      ->set('password', 'password')
      ->save();
    $this->handler->append(new Response());
    $job = RegistrationNotification::createJob($this->entity);
    $job->setId('1');
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_SUCCESS, $result->getState());
    $this->assertSame('', $result->getMessage());
    $this->assertCount(1, $this->history);
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $this->history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame('Basic dXNlcm5hbWU6cGFzc3dvcmQ=', $request->getHeader('Authorization')[0]);
    // The functionality of creating a message from an entity is tested in
    // MessageGeneratorTest.
    $expected_json = Json::encode($this->container->get('vgwort.message_generator')
      ->entityToNewMessage($this->entity)
      ->jsonSerialize());
    $this->assertSame($expected_json, (string) $request->getBody());
    $this->assertSame('https://tom.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest', (string) $request->getUri());

    // Check URL for test mode.
    $this->config('vgwort.settings')
      ->set('test_mode', TRUE)
      ->save();
    $this->handler->append(new Response());
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_SUCCESS, $result->getState());
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $this->history[1]['request'];
    $this->assertSame('https://tom-test.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest', (string) $request->getUri());
  }

  public function testVgWortJsonError() {
    $this->config('vgwort.settings')
      ->set('username', 'username')
      ->set('password', 'password')
      ->save();
    $this->handler->append(new Response(500, ['Content-Type' => ['application/json', 'charset=UTF-8']], '{"message":{"errorcode":1,"errormsg":"Privater Identifikationscode: Für den eingegebenen Wert existiert keine Zählmarke."}}'));
    $job = RegistrationNotification::createJob($this->entity);
    $result = $this->processJob($job);
    $this->assertSame(Job::STATE_FAILURE, $result->getState());
    $this->assertSame('The request to VG Wort failed (status code: 500). Error code: 1. Error message: Privater Identifikationscode: Für den eingegebenen Wert existiert keine Zählmarke.', $result->getMessage());
    $this->assertCount(1, $this->history);
  }

}
