<?php

namespace Drupal\Tests\config_selector\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\config_selector\TestLogger;
use Psr\Log\LogLevel;

/**
 * Tests the ConfigSelector.
 *
 * @group config_selector
 *
 * @see \Drupal\config_selector\ConfigSelector
 */
class ConfigSelectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'config_test', 'config_selector'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    TestLogger::register($container);
  }

  /**
   * Tests \Drupal\config_selector\ConfigSelector().
   */
  public function testConfigSelector() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');

    // Install a module that has configuration with config_selector third party
    // settings for the ConfigSelector to process..
    $module_installer->install(['config_selector_test_one']);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertLogMessages(['<em class="placeholder">config_selector_test_one</em> module installed.']);
    $this->assertMessages([]);
    $this->clearLogger();

    // Install another module that will cause config_test.dynamic.feature_a_two
    // to be installed. This configuration has a higher priority than
    // config_test.dynamic.feature_a_one. Therefore, feature_a_one will be
    // disabled and feature_a_two will be enabled.
    $module_installer->install(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.']);
    $this->clearLogger();

    // Install another module that will cause
    // config_test.dynamic.feature_a_three to be installed. This configuration
    // has a higher priority than config_test.dynamic.feature_a_one but a lower
    // priority than config_test.dynamic.feature_a_two. Therefore,
    // feature_a_three will be disabled and feature_a_two will still be enabled.
    $module_installer->install(['config_selector_test_three']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertFalse($configs['feature_a_three']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_three</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.']);
    $this->clearLogger();

    // Uninstall a module causing config_test.dynamic.feature_a_two to be
    // removed. Since config_test.dynamic.feature_a_three has the next highest
    // priority it will be enabled.
    $module_installer->uninstall(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertTrue($configs['feature_a_three']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module uninstalled.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been enabled.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been enabled.']);
    $this->clearLogger();

    // Install the module that will cause config_test.dynamic.feature_a_two to
    // be installed again. This configuration has a higher priority than
    // config_test.dynamic.feature_a_one and
    // config_test.dynamic.feature_a_three. Therefore, feature_a_three will be
    // disabled and feature_a_two will be enabled.
    $module_installer->install(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertFalse($configs['feature_a_three']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_three">Feature A version 3</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.']);
    $this->clearLogger();

    // Manually disable config_test.dynamic.feature_a_two and enable
    // config_test.dynamic.feature_a_one.
    $configs['feature_a_two']->setStatus(FALSE)->save();
    $configs['feature_a_one']->setStatus(TRUE)->save();
    // Uninstalling config_selector_test_two will not enable
    // config_test.dynamic.feature_a_three because
    // config_test.dynamic.feature_a_one is enabled.
    $module_installer->uninstall(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertFalse($configs['feature_a_three']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module uninstalled.',
    ]);
    $this->assertMessages([]);
    $this->clearLogger();

    // Install the module that will cause config_test.dynamic.feature_a_two to
    // be installed again. This configuration has a higher priority than
    // config_test.dynamic.feature_a_one and
    // config_test.dynamic.feature_a_three. Therefore, feature_a_one will be
    // disabled and feature_a_two will be enabled.
    $module_installer->install(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertFalse($configs['feature_a_three']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.']);
    $this->clearLogger();

    // Uninstalling the module that config_test.dynamic.feature_a_three depends
    // on does not change which config is enabled.
    $module_installer->uninstall(['config_selector_test_three']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_three</em> module uninstalled.',
    ]);
    $this->assertMessages([]);
    $this->clearLogger();

    // Uninstalling the module that config_test.dynamic.feature_a_two depends
    // on means that as the last remaining config,
    // config_test.dynamic.feature_a_one is enabled.
    $module_installer->uninstall(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module uninstalled.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been enabled.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been enabled.']);
    $this->clearLogger();

    // Install the module that will cause config_test.dynamic.feature_a_four to
    // be created. This configuration has a higher priority than
    // config_test.dynamic.feature_a_one but is disabled by default. Therefore,
    // feature_a_one will be still be enabled and feature_a_four will be
    // disabled.
    $module_installer->install(['config_selector_test_four']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertFalse($configs['feature_a_four']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_four</em> module installed.',
    ]);
    $this->assertMessages([]);
    $this->clearLogger();

    // Uninstalling the module that will cause config_test.dynamic.feature_a_one
    // to be removed. This will cause config_test.dynamic.feature_a_four to be
    // enabled.
    $module_installer->uninstall(['config_selector_test_one']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertArrayNotHasKey('feature_a_one', $configs);
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertTrue($configs['feature_a_four']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_one</em> module uninstalled.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_four">Feature A version 4</a> has been enabled.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_four">Feature A version 4</a> has been enabled.']);
    $this->clearLogger();

    // Installing the module that will cause config_test.dynamic.feature_a_one
    // to be create. This will cause config_test.dynamic.feature_a_four to still
    // be enabled and feature_a_one will be disabled.
    $module_installer->install(['config_selector_test_one']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertTrue($configs['feature_a_four']->status());
    $configs['feature_a_four']->setStatus(FALSE)->save();
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_one</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_four">Feature A version 4</a>.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_four">Feature A version 4</a>.']);
    $this->clearLogger();

    // Because both config_test.dynamic.feature_a_one and
    // config_test.dynamic.feature_a_four are disabled, uninstalling a module
    // should not enable feature_a_four even though feature_a_one is deleted.
    $module_installer->uninstall(['config_selector_test_one']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertArrayNotHasKey('feature_a_one', $configs);
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertFalse($configs['feature_a_four']->status());
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_one</em> module uninstalled.',
    ]);
    $this->assertMessages([]);
    $this->clearLogger();
  }

  /**
   * Tests \Drupal\config_selector\ConfigSelector().
   *
   * Checks indirect module uninstall dependencies.
   */
  public function testConfigSelectorIndirectDependency() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');

    // Install two modules at start, 3 configurations should be imported, where
    // only one is enabled and that one depends on
    // "config_selector_test_depends_on_test_two", where that module depends on
    // "config_selector_test_two".
    $module_installer->install([
      'config_selector_test_one',
      'config_selector_test_depends_on_test_two',
    ]);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();

    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertFalse($configs['feature_a_two']->status());
    $this->assertTrue($configs['feature_a_depends_on_test_two']->status());
    $this->assertArrayNotHasKey('feature_a_three', $configs);

    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_two</em> module installed.',
      '<em class="placeholder">config_selector_test_depends_on_test_two</em> module installed.',
      '<em class="placeholder">config_selector_test_one</em> module installed.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_depends_on_test_two">Feature A indirect depending on Test Two</a>.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_depends_on_test_two">Feature A indirect depending on Test Two</a>.',
    ]);
    $this->assertMessages([
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_depends_on_test_two">Feature A indirect depending on Test Two</a>.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_depends_on_test_two">Feature A indirect depending on Test Two</a>.',
    ]);
    $this->clearLogger();

    // Uninstall "config_selector_test_two", that will indirectly uninstall also
    // "config_selector_test_depends_on_test_two", where all dependency are
    // removed and only requirements for "feature_a_one" are fulfilled.
    $module_installer->uninstall(['config_selector_test_two']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status(), "Configuration: Feature A version 1 - should be enabled.");
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_depends_on_test_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertLogMessages([
      '<em class="placeholder">config_selector_test_depends_on_test_two</em> module uninstalled.',
      '<em class="placeholder">config_selector_test_two</em> module uninstalled.',
      'Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been enabled.',
    ]);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been enabled.']);
    $this->clearLogger();
  }

  /**
   * Tests \Drupal\config_selector\ConfigSelector().
   *
   * Tests installing a module that provides multiple features with multiple
   * versions.
   */
  public function testConfigSelectorMultipleFeatures() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');

    $module_installer->install(['config_selector_test_provides_multiple']);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();

    $this->assertTrue($configs['feature_a_two']->status());
    // Lower priority than feature_a_two.
    $this->assertFalse($configs['feature_a_one']->status());
    // Lower priority than feature_a_two.
    $this->assertFalse($configs['feature_a_three']->status());
    // Higher priority but it is disabled in default configuration.
    $this->assertFalse($configs['feature_a_four']->status());

    $this->assertTrue($configs['feature_b_two']->status());
    $this->assertFalse($configs['feature_b_one']->status());

    $this->assertTrue($configs['feature_c_one']->status());
  }

  /**
   * Tests \Drupal\config_selector\ConfigSelector().
   *
   * Tests installing multiple modules at the same time.
   */
  public function testConfigSelectorMultipleModuleInstall() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');

    // Install a module that has configuration with config_selector third party
    // settings for the ConfigSelector to process..
    $module_installer->install(['config_selector_test_one']);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertTrue($configs['feature_a_one']->status());
    $this->assertArrayNotHasKey('feature_a_two', $configs);
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertMessages([]);
    $this->clearLogger();

    // Install another module that will cause config_test.dynamic.feature_a_two
    // to be installed. This configuration has a higher priority than
    // config_test.dynamic.feature_a_one. Therefore, feature_a_one will be
    // disabled and feature_a_two will be enabled.
    $module_installer->install(['config_selector_test_two', 'config_selector_zzzzz_test']);
    $configs = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertFalse($configs['feature_a_one']->status());
    $this->assertTrue($configs['feature_a_two']->status());
    $this->assertArrayNotHasKey('feature_a_three', $configs);
    $this->assertMessages(['Configuration <a href="/admin/structure/config_test/manage/feature_a_one">Feature A version 1</a> has been disabled in favor of <a href="/admin/structure/config_test/manage/feature_a_two">Feature A version 2</a>.']);
    $this->clearLogger();
  }

  /**
   * Asserts the logger has messages.
   *
   * @param string[] $messages
   *   (optional) The messages we expect the logger to have. Defaults to an
   *   empty array.
   * @param string $level
   *   (optional) The log level of the expected messages. Defaults to
   *   \Psr\Log\LogLevel::INFO.
   */
  protected function assertLogMessages(array $messages = [], $level = LogLevel::INFO) {
    $this->assertEquals($messages, $this->container->get('config_selector.test_logger')->getLogs($level));
  }

  /**
   * Asserts the Drupal message service has messages.
   *
   * @param array $messages
   *   (optional) The messages we expect the Drupal message service to have.
   *   Defaults to an empty array.
   * @param string $type
   *   (optional) The type of the expected messages. Defaults to 'status'.
   */
  protected function assertMessages(array $messages = [], $type = 'status') {
    $actual_messages = \Drupal::messenger()->messagesByType($type);
    if (!empty($actual_messages)) {
      $actual_messages = array_map('strval', $actual_messages);
    }
    $this->assertEquals($messages, $actual_messages);
    \Drupal::messenger()->deleteByType($type);
  }

  /**
   * Clears the test logger of messages.
   */
  protected function clearLogger() {
    $this->container->get('config_selector.test_logger')->clear();
  }

}
