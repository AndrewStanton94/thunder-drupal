<?php

namespace Drupal\config_selector;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Selects configuration to enable after a module install or uninstall.
 *
 * Uses the Configuration Selector feature name and priority to select which
 * configuration should be enabled after a module install or uninstall. The
 * Configuration Selector feature name and priority are stored in a
 * configuration entity's third party settings. For example:
 * @code
 * third_party_settings:
 *   config_selector:
 *     feature: an_example_feature_name
 *     priority: 1000
 * @endcode
 */
class ConfigSelector {
  use StringTranslationTrait;
  use ConfigSelectorSortTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Ensures ::selectConfig() has the correct list of configuration.
   *
   * Prevents multiple triggers of ::setCurrentConfigList() causing the list of
   * new configuration to be calculated incorrectly.
   *
   * Records the name of the module that first triggered
   * config_selector_module_preinstall().
   *
   * @var string
   *
   * @see \Drupal\config_selector\ConfigSelector::setCurrentConfigList()
   * @see \Drupal\config_selector\ConfigSelector::selectConfig()
   * @see config_selector_module_preinstall()
   * @see config_selector_modules_installed()
   */
  private static $modulePreinstallTriggered;

  /**
   * ConfigSelector constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigManagerInterface $config_manager, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, StateInterface $state, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->configManager = $config_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * Stores a list of active configuration prior to module installation.
   *
   * The list makes it simple to work out what configuration is new and if we
   * have to enable or disable any configuration.
   *
   * @param string $module
   *   The module being installed.
   *
   * @return $this
   *
   * @see config_selector_module_preinstall()
   */
  public function setCurrentConfigList($module) {
    // This should only trigger once per set of modules passed to the
    // ModuleInstaller to install. As service will be rebuild during the module
    // install a private static variable is used to store this information.
    if (static::$modulePreinstallTriggered !== NULL) {
      return $this;
    }
    static::$modulePreinstallTriggered = $module;
    if ($module === 'config_selector') {
      // If the Configuration Selector module is being installed, process all
      // existing configuration in
      // \Drupal\config_selector\ConfigSelector::selectConfig().
      $list = [];
    }
    else {
      $list = $this->configFactory->listAll();
    }
    $this->state->set('config_selector.current_config_list', $list);
    return $this;
  }

  /**
   * Determines if a Configuration Selector feature might during an uninstall.
   *
   * Stores a list of affected features keyed by full configuration object name.
   *
   * @param string $module
   *   The module being uninstalled.
   *
   * @return $this
   *
   * @see config_selector_module_preuninstall()
   */
  public function setUninstallConfigList($module) {
    // Get a list of config entities that might be deleted.
    $config_entities = $this->configManager->findConfigEntityDependenciesAsEntities('module', [$module]);
    // We need to keep adding to the list since more than one module might be
    // uninstalled at a time.
    $features = $this->state->get('config_selector.feature_uninstall_list', []);
    foreach ($config_entities as $config_entity) {
      if (!$config_entity->status()) {
        // We are only interested in enabled configuration entities, ie.
        // functionality a user might lose.
        continue;
      }
      $feature = $config_entity->getThirdPartySetting('config_selector', 'feature');
      if ($feature !== NULL) {
        $features[$config_entity->getConfigDependencyName()] = $feature;
      }
    }
    $this->state->set('config_selector.feature_uninstall_list', $features);
    return $this;
  }

  /**
   * Selects configuration to enable after uninstalling a module.
   *
   * @return $this
   *
   * @see config_selector_modules_uninstalled()
   */
  public function selectConfigOnUninstall() {
    $features = $this->state->get('config_selector.feature_uninstall_list', []);
    foreach ($features as $config_entity_id => $feature) {
      $entity_type_id = $this->configManager->getEntityTypeIdByName($config_entity_id);
      if (!$entity_type_id) {
        // The entity type no longer exists there will not be any replacement
        // config.
        continue;
      }

      // Get all the possible configuration for the feature.
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $matching_config = $entity_storage
        ->getQuery()
        ->condition('third_party_settings.config_selector.feature', $feature)
        ->execute();
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
      $configs = $entity_storage->loadMultiple($matching_config);
      $this->sortConfigEntities($configs);

      // If any of the configuration is enabled there is nothing to do here.
      foreach ($configs as $config) {
        if ($config->status()) {
          continue 2;
        }
      }

      // No configuration is enabled. Enable the highest priority one.
      $highest_priority_config = array_pop($configs);
      $highest_priority_config->setStatus(TRUE)->save();
      $variables = [
        ':active_config_href' => static::getConfigEntityLink($highest_priority_config),
        '@active_config_label' => $highest_priority_config->label(),
      ];
      $this->logger->info(
        'Configuration <a href=":active_config_href">@active_config_label</a> has been enabled.',
        $variables
      );
      $this->messenger->addStatus($this->t(
        'Configuration <a href=":active_config_href">@active_config_label</a> has been enabled.',
        $variables
      ));
    }
    // Reset the list.
    $this->state->set('config_selector.feature_uninstall_list', []);
    return $this;
  }

  /**
   * Selects configuration to enable and disable after installing modules.
   *
   * Ensures config selection works when multiple modules are installed or
   * when a module's hook_install() also installs modules.
   *
   * @param string[] $modules
   *   The list of modules being installed.
   *
   * @return $this
   *
   * @see config_selector_modules_installed()
   */
  public function selectConfigOnInstall(array $modules) {
    if (static::$modulePreinstallTriggered !== NULL && !in_array(static::$modulePreinstallTriggered, $modules)) {
      return $this;
    }
    // Reset the flag as we're now selecting config based on the new config that
    // has been created.
    static::$modulePreinstallTriggered = NULL;

    $new_configuration_list = array_diff(
      $this->configFactory->listAll(),
      $this->state->get('config_selector.current_config_list', [])
    );
    // Build a list of feature names of the configuration that's been imported.
    $features = [];
    foreach ($new_configuration_list as $config_name) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $config_entity */
      $config_entity = $this->configManager->loadConfigEntityByName($config_name);
      if (!$config_entity) {
        // Simple configuration is ignored.
        continue;
      }
      if (!$config_entity->status()) {
        // Disabled configuration is ignored.
        continue;
      }
      $feature = $config_entity->getThirdPartySetting('config_selector', 'feature');
      if ($feature !== NULL) {
        $features[] = $feature;
      }
    }
    // It is possible that the module or profile installed has multiple
    // configurations for the same feature.
    $features = array_unique($features);

    // Process each feature and choose the configuration with the highest
    // priority.
    foreach ($features as $feature) {
      $entity_storage = $this->entityTypeManager->getStorage($config_entity->getEntityTypeId());
      $matching_config = $entity_storage
        ->getQuery()
        ->condition('third_party_settings.config_selector.feature', $feature)
        ->condition('status', FALSE, '<>')
        ->execute();

      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $configs */
      $configs = $entity_storage->loadMultiple($matching_config);
      $configs = $this->sortConfigEntities($configs);

      // The last member of the array has the highest priority and should remain
      // enabled.
      $active_config = array_pop($configs);
      foreach ($configs as $config) {
        $config->setStatus(FALSE)->save();
        $variables = [
          ':disabled_config_href' => static::getConfigEntityLink($config),
          '@disabled_config_label' => $config->label(),
          ':active_config_href' => static::getConfigEntityLink($active_config),
          '@active_config_label' => $active_config->label(),
        ];

        $this->logger->info(
          'Configuration <a href=":disabled_config_href">@disabled_config_label</a> has been disabled in favor of <a href=":active_config_href">@active_config_label</a>.',
          $variables
        );
        $this->messenger->addStatus($this->t(
          'Configuration <a href=":disabled_config_href">@disabled_config_label</a> has been disabled in favor of <a href=":active_config_href">@active_config_label</a>.',
          $variables
        ));
      }
    }
    return $this;
  }

  /**
   * Generates a link for a configuration entity if possible.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The configuration entity to generate a link for.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   The best URL to link to the entity with. Edit links are preferred to
   *   canonical links. If no link is possible an empty string is returned.
   */
  public static function getConfigEntityLink(ConfigEntityInterface $entity) {
    try {
      if ($entity->hasLinkTemplate('edit-form')) {
        $url = $entity->toUrl('edit-form');
      }
      else {
        $url = $entity->toUrl();
      }
    }
    catch (\Exception $e) {
    }
    return isset($url) ? $url->toString() : '';
  }

}
