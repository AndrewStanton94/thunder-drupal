<?php

namespace Drupal\config_selector;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A test logger.
 */
class TestLogger implements DebugLoggerInterface, LoggerInterface {

  /**
   * An array of log messages keyed by type.
   *
   * @var array
   */
  protected static $logs;

  /**
   * TestLogger constructor.
   */
  public function __construct() {
    if (empty(static::$logs)) {
      $this->clear();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLogs($level = FALSE) {
    return FALSE === $level ? static::$logs : static::$logs[$level];
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    static::$logs = [
      'emergency' => [],
      'alert' => [],
      'critical' => [],
      'error' => [],
      'warning' => [],
      'notice' => [],
      'info' => [],
      'debug' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // Convert levels...
    static $map = [
      RfcLogLevel::DEBUG => 'debug',
      RfcLogLevel::INFO => 'info',
      RfcLogLevel::NOTICE => 'notice',
      RfcLogLevel::WARNING => 'warning',
      RfcLogLevel::ERROR => 'error',
      RfcLogLevel::CRITICAL => 'critical',
      RfcLogLevel::ALERT => 'alert',
      RfcLogLevel::EMERGENCY => 'emergency',
    ];

    foreach ($context as $key => $value) {
      if (ctype_alpha($key[0]) && strpos($message, $key) === FALSE) {
        unset($context[$key]);
      }
    }

    $level = isset($map[$level]) ? $map[$level] : $level;
    static::$logs[$level][] = (string) new FormattableMarkup($message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function emergency($message, array $context = []) {
    $this->log('emergency', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function alert($message, array $context = []) {
    $this->log('alert', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function critical($message, array $context = []) {
    $this->log('critical', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function error($message, array $context = []) {
    $this->log('error', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function warning($message, array $context = []) {
    $this->log('warning', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function notice($message, array $context = []) {
    $this->log('notice', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function info($message, array $context = []) {
    $this->log('info', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function debug($message, array $context = []) {
    $this->log('debug', $message, $context);
  }

  /**
   * Registers the test logger to the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder to register the test logger to.
   */
  public static function register(ContainerBuilder $container) {
    $container->register('config_selector.test_logger', __CLASS__)->addTag('logger');
  }

  /**
   * {@inheritdoc}
   */
  public function countErrors(Request $request = NULL) {
    return count(static::$logs['critical']) + count(static::$logs['error']) + count(static::$logs['emergency']);
  }

}
