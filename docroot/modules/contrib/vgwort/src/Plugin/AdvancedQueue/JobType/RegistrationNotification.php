<?php

namespace Drupal\vgwort\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\vgwort\EntityJobMapper;
use Drupal\vgwort\Exception\NewMessageException;
use Drupal\vgwort\MessageGenerator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @AdvancedQueueJobType(
 *   id = "vgwort_registration_notification",
 *   label = @Translation("VG Wort Registration notification"),
 * )
 *
 * $job = Job::create('vgwort_registration_notification', ['entity_type' => 'node', 'entity_id' => '10']);
 */
class RegistrationNotification extends JobTypeBase implements ContainerFactoryPluginInterface {

  /**
   * URL to post to the VG Wort system.
   *
   * @todo Should this be configurable or overridable in some way?
   */
  private const LIVE_URL = 'https://tom.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest';
  private const TEST_URL = 'https://tom-test.vgwort.de/api/external/metis/rest/message/v1.0/newMessageRequest';

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\vgwort\MessageGenerator
   */
  protected MessageGenerator $messageGenerator;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * @var \Drupal\vgwort\EntityJobMapper
   */
  protected EntityJobMapper $entityJobMapper;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\vgwort\MessageGenerator $messageGenerator
   *   The VG Wort message generator.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Config\Config $config
   *   The vgwort.settings config.
   * @param \Drupal\vgwort\EntityJobMapper $entityJobMapper
   *   The entity job mapper.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, MessageGenerator $messageGenerator, ClientInterface $httpClient, Config $config, EntityJobMapper $entityJobMapper, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->messageGenerator = $messageGenerator;
    $this->httpClient = $httpClient;
    $this->config = $config;
    $this->entityJobMapper = $entityJobMapper;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('vgwort.message_generator'),
      $container->get('http_client'),
      $container->get('config.factory')->get('vgwort.settings'),
      $container->get('vgwort.entity_job_mapper'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job): JobResult {
    try {
      [$entity_type, $entity_id] = self::getEntityInfoFromJob($job);
    }
    catch (\RuntimeException $e) {
      return JobResult::failure($e->getMessage());
    }

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity instanceof FieldableEntityInterface) {
      return JobResult::failure(sprintf('The entity %s:%s does not exist', $entity_type, $entity_id));
    }

    try {
      $message = $this->messageGenerator->entityToNewMessage($entity);
    }
    catch (NewMessageException $e) {
      return JobResult::failure(
        sprintf('The entity %s:%s failed: %s', $entity_type, $entity_id, $e->getMessage()),
        $e->retries(),
        // As this is failing before sending wait for another 2 weeks before
        // trying again. This gives the site time to update things like author
        // information.
        $this->config->get('registration_wait_days') * 24 * 60 * 60
      );
    }

    $headers = [
      'Authorization' => 'Basic ' . base64_encode($this->config->get('username') . ':' . $this->config->get('password')),
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ];
    $request = new Request(
      'POST',
      $this->config->get('test_mode') ? static::TEST_URL : static::LIVE_URL,
      $headers,
      Json::encode($message)
    );

    $response = $this->httpClient->send($request);

    if ($response->getStatusCode() >= 400) {
      return $this->createJobFailure($response);
    }
    $this->entityJobMapper->merge($job, $this->time->getCurrentTime());
    return JobResult::success();
  }

  /**
   * Converts a response to an error message that can be logged.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response from VG Wort.
   *
   * @return \Drupal\advancedqueue\JobResult
   *   The error message to log.
   */
  private function createJobFailure(ResponseInterface $response): JobResult {
    $error_code = '';
    $retry = TRUE;
    $error_message = (string) $response->getBody();
    if (str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {
      $json = Json::decode($error_message);
      if ($json !== NULL && isset($json['message']['errormsg'])) {
        $error_message = $json['message']['errormsg'];
        $error_code = $json['message']['errorcode'] ?? '';
        // Do not retry if the error code is less than 3 characters.
        $retry = strlen($error_code) > 2;
      }
    }
    elseif ($error_message !== '') {
      $dom = new \DOMDocument();
      if (@$dom->loadHTML($error_message)) {
        $error_message = Html::serialize($dom);
      }
    }

    // Ensure the text is safe to display.
    $error_message = PlainTextOutput::renderFromHtml($error_message);
    $error_code = PlainTextOutput::renderFromHtml($error_code);

    // @todo Rather than filtering HTML should we use formattable markup? This
    //   depends on the implementation in Advanced Queue. Current implementation
    //   is safety first.
    if ($error_code === '') {
      $message = sprintf('The request to VG Wort failed (status code: %s). Error message: %s', $response->getStatusCode(), $error_message);
    }
    else {
      $message = sprintf('The request to VG Wort failed (status code: %s). Error code: %s. Error message: %s', $response->getStatusCode(), $error_code, $error_message);
    }

    return JobResult::failure($message, $retry ? 3 : 0, $this->config->get('queue_retry_time'));
  }

  /**
   * Creates a job that will be processed by this job type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return \Drupal\advancedqueue\Job
   *   The job to process.
   */
  public static function createJob(EntityInterface $entity): Job {
    return Job::create(
      'vgwort_registration_notification',
      ['entity_type' => $entity->getEntityTypeId(), 'entity_id' => $entity->id()]
    );
  }

  /**
   * Gets the entity type and entity ID from a job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job to get the entity type and entity ID for.
   *
   * @return array
   *   An array with two elements. The first is the entity type ID and the
   *   second is entity ID.
   *
   * @throws \RuntimeException
   *   Thrown when the job does not have the expected information.
   */
  public static function getEntityInfoFromJob(Job $job): array {
    $payload = $job->getPayload();
    if (!isset($payload['entity_type']) || !isset($payload['entity_id'])) {
      throw new \RuntimeException('Missing entity_type or entity_id from the payload');
    }
    return [$payload['entity_type'], $payload['entity_id']];
  }

}
