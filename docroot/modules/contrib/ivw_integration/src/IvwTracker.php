<?php

namespace Drupal\ivw_integration;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Utility\Token;
use Drupal\taxonomy\TermInterface;

/**
 * Provides all the IVW tracking information.
 */
class IvwTracker implements IvwTrackerInterface, CacheableDependencyInterface {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Static cache of tracking information.
   *
   * @var array|null
   */
  protected $trackingInformation;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token object.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The IVW lookup service.
   *
   * @var \Drupal\ivw_integration\IvwLookupServiceInterface
   */
  protected $lookupService;

  /**
   * Generates IVW tracking information.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\ivw_integration\IvwLookupServiceInterface $lookupService
   *   The IVW lookup service.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The Drupal language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Token $token,
    IvwLookupServiceInterface $lookupService,
    LanguageManager $language_manager
  ) {
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->lookupService = $lookupService;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingInformation(ContentEntityInterface $entity = NULL) {
    if (!isset($this->trackingInformation)) {
      $this->trackingInformation = [
        'st' => $this->getSt(),
        'mobile_st' => $this->getMobileSt(),
        'cp' => $this->getCp($entity),
        'sv' => $this->getSv($entity),
        'mobile_sv' => $this->getMobileSv($entity),
        'mobile_dn' => $this->getMobileDn(),
        'dn' => $this->getDn(),
        'mobile_dn' => $this->getMobileDn(),
        'dg' => $this->getDg(),
        'pt' => $this->getPt(),
        'dc' => $this->getDc(),
        'legacy_mode' => $this->getLegacyMode(),
        'bfe' => $this->getBfe(),
      ];
      // Calculate cpm based upon cp.
      // @todo This is absolutely not generic.
      $this->trackingInformation['cpm'] = str_replace('D1A', 'D2A', $this->trackingInformation['cp']);
    }
    return $this->trackingInformation;
  }

  /**
   * Gets the st parameter.
   *
   * @return string
   *   The value of the st parameter.
   */
  protected function getSt() {
    return $this->configFactory->get('ivw_integration.settings')->get('site');
  }

  /**
   * Gets the mobile_st parameter.
   *
   * @return string
   *   The value of the mobile_st parameter.
   */
  protected function getMobileSt() {
    return $this->configFactory->get('ivw_integration.settings')->get('mobile_site');
  }

  /**
   * Gets the cp parameter.
   *
   * Possible overrides have been applied for the current page.
   *
   * @return string
   *   The value of the cp parameter.
   */
  protected function getCp(ContentEntityInterface $entity = NULL) {
    $settings = $this->configFactory->get('ivw_integration.settings');
    $code_template = $settings->get('code_template');

    $data = [];
    if ($entity) {
      if ($entity instanceof TermInterface) {
        $data['term'] = $entity;
      }
      else {
        $data['entity'] = $entity;
      }
    }

    return $this->token->replace($code_template, $data, ['sanitize' => FALSE]);
  }

  /**
   * Gets the sv parameter.
   *
   * @return string
   *   The value of the sv parameter.
   *   If non is defined anywhere 'in' is returned as default.
   */
  protected function getSv(ContentEntityInterface $entity = NULL) {
    $data = [];
    if ($entity) {
      if ($entity instanceof TermInterface) {
        $data['term'] = $entity;
      }
      else {
        $data['entity'] = $entity;
      }
    }
    $sv = $this->token->replace('[ivw:frabo]', $data, ['sanitize' => FALSE]);
    return empty($sv) ? 'in' : $sv;
  }

  /**
   * Gets the sv parameter for mobile devices.
   *
   * @return string
   *   The value of the sv parameter.
   *   If non is defined anywhere 'mo' is returned as default.
   */
  protected function getMobileSv(ContentEntityInterface $entity = NULL) {
    $data = [];
    if ($entity) {
      if ($entity instanceof TermInterface) {
        $data['term'] = $entity;
      }
      else {
        $data['entity'] = $entity;
      }
    }
    $sv = $this->token->replace('[ivw:frabo_mobile]', $data, ['sanitize' => FALSE]);
    return empty($sv) ? 'mo' : $sv;
  }

  /**
   * Gets the dn parameter.
   *
   * @return string
   *   The value of the dn parameter.
   */
  protected function getDn() {
    return $this->configFactory->get('ivw_integration.settings')->get('service_domain_name');
  }

  /**
   * Gets the dn parameter.
   *
   * @return string
   *   The value of the dn parameter.
   */
  protected function getMobileDn() {
    return $this->configFactory->get('ivw_integration.settings')->get('mobile_service_domain_name');
  }

  /**
   * Gets the debug parameter.
   *
   * @return bool
   *   The value of the debug parameter.
   */
  protected function getDg() {
    return $this->configFactory->get('ivw_integration.settings')->get('debug');
  }

  /**
   * Gets the pixel type parameter.
   *
   * @return string
   *   The value of the pixel type parameter.
   */
  protected function getPt() {
    return $this->configFactory->get('ivw_integration.settings')->get('pixel_type');
  }

  /**
   * Gets the legacy mode parameter.
   *
   * @return bool
   *   The value of the legacy mode parameter.
   */
  protected function getLegacyMode() {
    return $this->configFactory->get('ivw_integration.settings')->get('legacy_mode');
  }

  /**
   * Gets the bfe value.
   *
   * @return bool
   *   The value of the bfe value.
   */
  protected function getBfe() {
    return $this->configFactory->get('ivw_integration.settings')->get('bfe');
  }

  /**
   * Gets distribution channel.
   *
   * @return string
   *   The value of the distribution channel.
   */
  protected function getDc() {
    return $this->configFactory->get('ivw_integration.settings')->get('distribution_channel');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.path'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = $this->lookupService->getCacheTagsByCurrentRoute();

    $settings = $this->configFactory->get('ivw_integration.settings');

    return Cache::mergeTags($cache_tags, $settings->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * Should the tracker be enabled for the current language?
   *
   * @return bool
   *   Returns TRUE if the tracker is enabled, FALSE otherwise.
   */
  public function isLanguageEnabled() {
    $config = $this->configFactory->get('ivw_integration.settings');
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $disabled_languages = $config->get('disabled_languages');

    if (!$disabled_languages) {
      return TRUE;
    }

    if (
          array_key_exists($current_language, $disabled_languages)
          && $disabled_languages[$current_language] === $current_language
      ) {
      return FALSE;
    }
    return TRUE;
  }

}
