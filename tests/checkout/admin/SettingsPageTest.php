<?php
/**
 * Tests for Settings_Page.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Tests the Checkout Tools settings page.
 */
final class SettingsPageTest extends TestCase {

	/**
	 * Checkout Tools settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $settings_page;

	/**
	 * Sets up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_actions']             = array();
		$GLOBALS['shurloc_test_action_metadata']     = array();
		$GLOBALS['shurloc_test_options']             = array();
		$GLOBALS['shurloc_test_registered_settings'] = array();
		$GLOBALS['shurloc_test_settings_sections']   = array();
		$GLOBALS['shurloc_test_settings_fields']     = array();
		$GLOBALS['shurloc_test_settings_errors']     = array();
		$GLOBALS['shurloc_test_user_capabilities']   = array(
			'manage_options' => true,
		);

		$_GET = array();

		$this->settings = new Settings();

		$this->settings_page = new Settings_Page(
			settings: $this->settings
		);
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['shurloc_test_actions'],
			$GLOBALS['shurloc_test_action_metadata'],
			$GLOBALS['shurloc_test_options'],
			$GLOBALS['shurloc_test_registered_settings'],
			$GLOBALS['shurloc_test_settings_sections'],
			$GLOBALS['shurloc_test_settings_fields'],
			$GLOBALS['shurloc_test_settings_errors'],
			$GLOBALS['shurloc_test_user_capabilities']
		);

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Tests that the admin initialization hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_init_action(): void {
		$this->settings_page->register();

		$this->assertSame(
			array(
				array( $this->settings_page, 'register_settings' ),
			),
			$GLOBALS['shurloc_test_actions']['admin_init']
		);
	}

	/**
	 * Tests that the option is registered with the Settings API.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_option(): void {
		$this->settings_page->register_settings();

		$this->assertCount(
			1,
			$GLOBALS['shurloc_test_registered_settings']
		);

		$registered = $GLOBALS['shurloc_test_registered_settings'][0];

		$this->assertSame(
			'shurloc_checkout_tools',
			$registered['option_group']
		);

		$this->assertSame(
			Settings::OPTION_NAME,
			$registered['option_name']
		);

		$this->assertSame(
			'array',
			$registered['args']['type']
		);

		$this->assertSame(
			array( $this->settings_page, 'sanitize_settings' ),
			$registered['args']['sanitize_callback']
		);

		$this->assertSame(
			$this->settings->get_defaults(),
			$registered['args']['default']
		);
	}

	/**
	 * Tests that the tariff settings section is registered.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_tariff_section(): void {
		$this->settings_page->register_settings();

		$this->assertCount(
			2,
			$GLOBALS['shurloc_test_settings_sections']
		);

		$section = $GLOBALS['shurloc_test_settings_sections'][0];

		$this->assertSame(
			'shurloc_checkout_tools_tariffs',
			$section['id']
		);

		$this->assertSame(
			'Tariff Fees',
			$section['title']
		);

		$this->assertSame(
			array( $this->settings_page, 'render_tariff_section' ),
			$section['callback']
		);

		$this->assertSame(
			Settings_Page::PAGE_SLUG,
			$section['page']
		);
	}

	/**
	 * Tests that the payment processing settings section is registered.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_payment_processing_section(): void {
		$this->settings_page->register_settings();

		$section = $GLOBALS['shurloc_test_settings_sections'][1];

		$this->assertSame(
			'Payment Processing Fees',
			$section['title']
		);

		$this->assertSame(
			array( $this->settings_page, 'render_payment_processing_section' ),
			$section['callback']
		);
	}

	/**
	 * Tests that all tariff settings fields are registered.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_tariff_fields(): void {
		$this->settings_page->register_settings();

		$this->assertCount(
			9,
			$GLOBALS['shurloc_test_settings_fields']
		);

		$field_ids = array_column(
			$GLOBALS['shurloc_test_settings_fields'],
			'id'
		);

		$this->assertSame(
			array(
				'mesh_tariff_enabled',
				'payment_processing_enabled',
				'payment_processing_standard_rate',
				'payment_processing_paypal_rate',
				'mesh_tariff_rate',
				'mesh_tariff_message',
				'sefar_tariff_enabled',
				'sefar_tariff_rate',
				'sefar_tariff_message',
			),
			$field_ids
		);
	}

	/**
	 * Tests that valid submitted settings are sanitized.
	 *
	 * @return void
	 */
	public function test_valid_settings_are_sanitized(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'enabled' => '1',
						'rate'    => '4.50',
						'message' => 'Custom mesh message.',
					),
					'sefar' => array(
						'enabled' => '1',
						'rate'    => '11.25',
						'message' => 'Custom Sefar message.',
					),
				),
			)
		);

		$this->assertSame(
			array(
				'tariffs'            => array(
					'mesh'  => array(
						'enabled' => true,
						'rate'    => 4.5,
						'message' => 'Custom mesh message.',
					),
					'sefar' => array(
						'enabled' => true,
						'rate'    => 11.25,
						'message' => 'Custom Sefar message.',
					),
				),
				'payment_processing' => array(
					'enabled'       => true,
					'standard_rate' => 1.5,
					'paypal_rate'   => 1.75,
				),
			),
			$sanitized
		);
	}

	/**
	 * Tests that missing enabled fields are sanitized as disabled.
	 *
	 * @return void
	 */
	public function test_missing_enabled_fields_are_sanitized_as_false(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'rate'    => '3',
						'message' => 'Mesh message.',
					),
					'sefar' => array(
						'rate'    => '9',
						'message' => 'Sefar message.',
					),
				),
			)
		);

		$this->assertFalse(
			$sanitized['tariffs']['mesh']['enabled']
		);

		$this->assertFalse(
			$sanitized['tariffs']['sefar']['enabled']
		);
	}

	/**
	 * Tests that payment processing settings are sanitized.
	 *
	 * @return void
	 */
	public function test_payment_processing_settings_are_sanitized(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'payment_processing' => array(
					'enabled'       => '1',
					'standard_rate' => '2.00',
					'paypal_rate'   => '2.50',
				),
			)
		);

		$this->assertSame(
			array(
				'enabled'       => true,
				'standard_rate' => 2.0,
				'paypal_rate'   => 2.5,
			),
			$sanitized['payment_processing']
		);
	}

	/**
	 * Tests that payment processing settings reset without changing tariffs.
	 *
	 * @return void
	 */
	public function test_payment_processing_settings_can_be_reset_to_defaults(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs'            => array(
					'mesh'  => array(
						'enabled' => '0',
						'rate'    => '4.00',
						'message' => 'Custom mesh message.',
					),
					'sefar' => array(
						'enabled' => '1',
						'rate'    => '10.00',
						'message' => 'Custom Sefar message.',
					),
				),
				'payment_processing' => array(
					'enabled'       => '0',
					'standard_rate' => '2.00',
					'paypal_rate'   => '2.50',
					'reset'         => 'Reset to defaults',
				),
			)
		);

		$this->assertSame(
			array(
				'enabled'       => true,
				'standard_rate' => 1.5,
				'paypal_rate'   => 1.75,
			),
			$sanitized['payment_processing']
		);

		$this->assertSame(
			4.0,
			$sanitized['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			'Custom Sefar message.',
			$sanitized['tariffs']['sefar']['message']
		);
	}

	/**
	 * Tests that explicit zero enabled values are sanitized as disabled.
	 *
	 * @return void
	 */
	public function test_zero_enabled_values_are_sanitized_as_false(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'enabled' => '0',
						'rate'    => '3',
						'message' => 'Mesh message.',
					),
					'sefar' => array(
						'enabled' => '0',
						'rate'    => '9',
						'message' => 'Sefar message.',
					),
				),
			)
		);

		$this->assertFalse(
			$sanitized['tariffs']['mesh']['enabled']
		);

		$this->assertFalse(
			$sanitized['tariffs']['sefar']['enabled']
		);
	}

	/**
	 * Tests that tariff rates remain stored as percentages.
	 *
	 * @return void
	 */
	public function test_tariff_rates_are_stored_as_percentages(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'enabled' => '1',
						'rate'    => '3',
					),
					'sefar' => array(
						'enabled' => '1',
						'rate'    => '9',
					),
				),
			)
		);

		$this->assertSame(
			3.0,
			$sanitized['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			9.0,
			$sanitized['tariffs']['sefar']['rate']
		);
	}

	/**
	 * Tests that zero percent is accepted.
	 *
	 * @return void
	 */
	public function test_zero_percent_tariff_rate_is_accepted(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh' => array(
						'enabled' => '1',
						'rate'    => '0',
					),
				),
			)
		);

		$this->assertSame(
			0.0,
			$sanitized['tariffs']['mesh']['rate']
		);
	}

	/**
	 * Tests that out-of-range tariff rates fall back to defaults.
	 *
	 * @return void
	 */
	public function test_out_of_range_rates_fall_back_to_defaults(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'enabled' => '1',
						'rate'    => '-1',
					),
					'sefar' => array(
						'enabled' => '1',
						'rate'    => '101',
					),
				),
			)
		);

		$this->assertSame(
			3.0,
			$sanitized['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			9.0,
			$sanitized['tariffs']['sefar']['rate']
		);
	}

	/**
	 * Tests that non-numeric tariff rates fall back to defaults.
	 *
	 * @return void
	 */
	public function test_non_numeric_rates_fall_back_to_defaults(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array(
				'tariffs' => array(
					'mesh'  => array(
						'rate' => 'invalid',
					),
					'sefar' => array(
						'rate' => array(),
					),
				),
			)
		);

		$this->assertSame(
			3.0,
			$sanitized['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			9.0,
			$sanitized['tariffs']['sefar']['rate']
		);
	}

	/**
	 * Tests that invalid input falls back to default settings.
	 *
	 * @return void
	 */
	public function test_non_array_input_returns_defaults(): void {
		$this->assertSame(
			$this->settings->get_defaults(),
			$this->settings_page->sanitize_settings(
				input: 'invalid'
			)
		);
	}

	/**
	 * Tests that missing tariff groups use default rates and messages.
	 *
	 * @return void
	 */
	public function test_missing_tariff_groups_use_default_values(): void {
		$sanitized = $this->settings_page->sanitize_settings(
			input: array()
		);

		$this->assertTrue(
			$sanitized['tariffs']['mesh']['enabled']
		);

		$this->assertSame(
			3.0,
			$sanitized['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			$this->settings->get_mesh_tariff_message(),
			$sanitized['tariffs']['mesh']['message']
		);

		$this->assertTrue(
			$sanitized['tariffs']['sefar']['enabled']
		);

		$this->assertSame(
			9.0,
			$sanitized['tariffs']['sefar']['rate']
		);

		$this->assertSame(
			$this->settings->get_sefar_tariff_message(),
			$sanitized['tariffs']['sefar']['message']
		);
	}

	/**
	 * Tests that the mesh enabled field renders checked by default.
	 *
	 * @return void
	 */
	public function test_mesh_enabled_field_renders_checked_by_default(): void {
		ob_start();

		$this->settings_page->render_mesh_enabled_field();

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'[tariffs][mesh][enabled]',
			$output
		);

		$this->assertStringContainsString(
			'value="0"',
			$output
		);

		$this->assertStringContainsString(
			'value="1"',
			$output
		);

		$this->assertStringContainsString(
			'checked',
			$output
		);
	}

	/**
	 * Tests that the mesh rate field renders the rate as a percentage.
	 *
	 * @return void
	 */
	public function test_mesh_rate_field_renders_percentage_value(): void {
		ob_start();

		$this->settings_page->render_mesh_rate_field();

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'value="3.00"',
			$output
		);
	}

	/**
	 * Tests that the Sefar rate field renders the rate as a percentage.
	 *
	 * @return void
	 */
	public function test_sefar_rate_field_renders_percentage_value(): void {
		ob_start();

		$this->settings_page->render_sefar_rate_field();

		$output = ob_get_clean();

		$this->assertIsString( $output );

		$this->assertStringContainsString(
			'value="9.00"',
			$output
		);
	}

	/**
	 * Tests that payment processing fields render their configured defaults.
	 *
	 * @return void
	 */
	public function test_payment_processing_fields_render_default_values(): void {
		ob_start();

		$this->settings_page->render_payment_processing_enabled_field();
		$this->settings_page->render_payment_processing_standard_rate_field();
		$this->settings_page->render_payment_processing_paypal_rate_field();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'[payment_processing][enabled]',
			$output
		);

		$this->assertStringContainsString(
			'value="1.50"',
			$output
		);

		$this->assertStringContainsString(
			'value="1.75"',
			$output
		);
	}

	/**
	 * Tests that unauthorized users do not receive settings page output.
	 *
	 * @return void
	 */
	public function test_render_page_returns_without_output_for_unauthorized_user(): void {
		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;

		ob_start();

		$this->settings_page->render_page();

		$output = ob_get_clean();

		$this->assertSame(
			'',
			$output
		);
	}

	/**
	 * Tests that the tariff fees renderer emits only the settings form.
	 *
	 * @return void
	 */
	public function test_render_tariff_fees_tab_renders_settings_form(): void {
		$this->settings_page->register_settings();

		ob_start();

		$this->settings_page->render_tariff_fees_tab();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'<form action="options.php" method="post">',
			$output
		);

		$this->assertStringNotContainsString(
			'<div class="wrap">',
			$output
		);

		$this->assertStringNotContainsString(
			'<h1>Checkout Tools</h1>',
			$output
		);

		$this->assertStringContainsString(
			'[tariffs][mesh][enabled]',
			$output
		);
	}

	/**
	 * Tests that the payment processing fees renderer emits its settings form.
	 *
	 * @return void
	 */
	public function test_render_payment_processing_fees_tab_renders_settings_form(): void {
		$this->settings_page->register_settings();

		ob_start();

		$this->settings_page->render_payment_processing_fees_tab();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'<form action="options.php" method="post">',
			$output
		);

		$this->assertStringContainsString(
			'[payment_processing][enabled]',
			$output
		);

		$this->assertStringNotContainsString(
			'[tariffs][mesh][enabled]',
			$output
		);

		$this->assertStringContainsString(
			'Reset to defaults',
			$output
		);

		$this->assertStringContainsString(
			'[payment_processing][reset]',
			$output
		);

		$this->assertMatchesRegularExpression(
			'/<p class="submit">\s*<input[^>]+Save Changes[^>]*>\s*<span aria-hidden="true">&nbsp;<\/span>\s*<input[^>]+Reset to defaults[^>]*>\s*<\/p>/',
			$output
		);
	}

	/**
	 * Tests that resetting payment processing settings displays a success notice.
	 *
	 * @return void
	 */
	public function test_reset_payment_processing_settings_displays_success_notice(): void {
		$this->settings_page->sanitize_settings(
			input: array(
				'payment_processing' => array(
					'reset' => 'Reset to defaults',
				),
			)
		);

		ob_start();

		$this->settings_page->render_payment_processing_fees_tab();

		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'notice notice-success is-dismissible',
			$output
		);

		$this->assertStringContainsString(
			'Payment processing fee settings reset to defaults.',
			$output
		);
	}
}
