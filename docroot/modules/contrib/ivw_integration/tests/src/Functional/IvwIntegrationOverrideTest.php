<?php

namespace Drupal\Tests\ivw_integration\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test override functionality of ivw.
 *
 * @group ivw_integration
 */
class IvwIntegrationOverrideTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'node',
    'block',
    'ivw_integration_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'create ivw_test content',
      'administer ivw integration configuration',
      'create terms in ivw_taxonomy',
    ]);

    // Setting defaults. To provide sane defaults and have a quick reference for
    // the actual tests.
    $this->config('ivw_integration.settings')->set('site', 'TestSiteName')
      ->set('mobile_site', 'TestMobileSiteName')
      ->set('frabo_default', 'IN')
      ->set('frabo_mobile_default', 'mo')
      ->set('frabo_overridable', 0)
      ->set('frabo_mobile_overridable', 0)
      ->set('code_template', '[ivw:offering]L[ivw:language]F[ivw:format]S[ivw:creator]H[ivw:homepage]D[ivw:delivery]A[ivw:app]P[ivw:paid]C[ivw:content]')
      ->set('responsive', 1)
      ->set('mobile_width', 480)
      ->set('offering_default', '01')
      ->set('offering_overridable', 0)
      ->set('language_default', 1)
      ->set('language_overridable', 0)
      ->set('format_default', 1)
      ->set('format_overridable', 0)
      ->set('creator_default', 1)
      ->set('creator_overridable', 0)
      ->set('homepage_default', 2)
      ->set('homepage_overridable', 0)
      ->set('delivery_default', 1)
      ->set('delivery_overridable', 0)
      ->set('app_default', 1)
      ->set('app_overridable', 0)
      ->set('paid_default', 1)
      ->set('paid_overridable', 0)
      ->set('content_default', '01')
      ->set('content_overridable', 0)
      ->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests overriding of site values.
   *
   * @dataProvider overrideTestCases
   */
  public function testOverride($settings, $termFieldOverrides, $nodeOverrides, $expectedOutput) {
    if (!empty($settings)) {
      $ivwSettings = $this->config('ivw_integration.settings');
      foreach ($settings as $settingName => $settingValue) {
        $ivwSettings->set($settingName, $settingValue);
      }
      $ivwSettings->save();
    }

    $nodeEdit = [
      'title[0][value]' => $this->randomString(),
    ];

    if (!empty($nodeOverrides)) {
      foreach ($nodeOverrides as $nodeOverrideName => $nodeOverrideValue) {
        $nodeEdit["field_ivw_settings[0][$nodeOverrideName]"] = $nodeOverrideValue;
      }
    }

    if (!empty($termFieldOverrides)) {
      $field_name = array_key_first($termFieldOverrides);

      // Load the term edit page.
      $this->drupalGet('admin/structure/taxonomy/manage/ivw_taxonomy/add');
      $this->assertSession()->statusCodeEquals(200);

      $termName = $this->randomString();
      $termEdit = [
        'name[0][value]' => $termName,
      ];

      foreach ($termFieldOverrides[$field_name] as $termOverrideName => $termOverrideValue) {
        $termEdit["field_ivw_settings[0][$termOverrideName]"] = $termOverrideValue;
      }

      $this->submitForm($termEdit, 'Save');

      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $termName]);
      $term = reset($terms);
      $nodeEdit[$field_name] = $term->id();
    }

    // Load the node edit page.
    $this->drupalGet('node/add/ivw_test');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm($nodeEdit, 'Save');
    $this->assertSession()->pageTextContains($expectedOutput);
  }

  /**
   * A data provider for testOverride.
   */
  public function overrideTestCases() {
    return [
      'No overrides' => [
        [
          'content_default' => '01',
          'content_overridable' => 0,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [],
        [],
        'IVWContent-01',
      ],
      'Override enabled, but no override value given in node' => [
        [
          'content_default' => '01',
          'content_overridable' => 1,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [],
        [],
        'IVWContent-01',
      ],
      'Override with value given in node' => [
        [
          'content_default' => '01',
          'content_overridable' => 1,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [],
        [
          'content' => '02',
        ],
        'IVWContent-02',
      ],
      'Override with value given in taxonomy' => [
        [
          'content_default' => '01',
          'content_overridable' => 1,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [
          'field_a' =>
            [
              'content' => '03',
            ],
        ],
        [],
        'IVWContent-03',
      ],
      'Override with value given in taxonomy field a and node' => [
        [
          'content_default' => '01',
          'content_overridable' => 1,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [
          'field_a' =>
            [
              'content' => '04',
            ],
        ],
        [
          'content' => '05',
        ],
        'IVWContent-05',
      ],
      'Override with value given in taxonomy field z and node' => [
        [
          'content_default' => '01',
          'content_overridable' => 1,
          'code_template' => 'IVWContent-[ivw:content]',
        ],
        [
          'field_z' =>
            [
              'content' => '04',
            ],
        ],
        [
          'content' => '05',
        ],
        'IVWContent-05',
      ],
    ];
  }

}
