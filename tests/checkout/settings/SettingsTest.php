<?php
/**
 * Tests for Settings.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Tests Checkout Tools settings.
 */
final class SettingsTest extends TestCase {

	/**
	 * Sets up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array();
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['shurloc_test_options']
		);

		parent::tearDown();
	}

	/**
	 * Tests that default settings are returned when no option exists.
	 *
	 * @return void
	 */
	public function test_defaults_are_returned_when_option_does_not_exist(): void {
		$settings = new Settings();

		$this->assertSame(
			array(
				'tariffs' => array(
					'mesh'  => array(
						'enabled' => true,
						'rate'    => 3.0,
						'message' => 'Due to a 6% tariff from our suppliers, all mesh orders will include a 3% tariff fee as a separate line item on invoices. We\'re sharing this cost to minimize impact and will adjust if tariff conditions change. Thank you for your understanding.',
					),
					'sefar' => array(
						'enabled' => true,
						'rate'    => 9.0,
						'message' => 'Due to a 12% mesh tariff from Sefar, mesh orders will include a 9% tariff fee as a separate line item on invoices. Shur-loc pays 3% of this tariff based on paying half of 6% for both Murakami and Saati sharing this cost to minimize industry impact and Shur-loc will adjust if tariff conditions change. Thank you for your understanding.',
					),
				),
			),
			$settings->get_defaults()
		);
	}

	/**
	 * Tests that getters return default values when no option exists.
	 *
	 * @return void
	 */
	public function test_getters_return_default_values(): void {
		$settings = new Settings();

		$this->assertTrue(
			$settings->is_mesh_tariff_enabled()
		);

		$this->assertSame(
			0.03,
			$settings->get_mesh_tariff_rate()
		);

		$this->assertStringContainsString(
			'3% tariff fee',
			$settings->get_mesh_tariff_message()
		);

		$this->assertTrue(
			$settings->is_sefar_tariff_enabled()
		);

		$this->assertSame(
			0.09,
			$settings->get_sefar_tariff_rate()
		);

		$this->assertStringContainsString(
			'9% tariff fee',
			$settings->get_sefar_tariff_message()
		);
	}

	/**
	 * Tests that stored tariff settings override defaults.
	 *
	 * @return void
	 */
	public function test_stored_settings_override_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh'  => array(
					'enabled' => false,
					'rate'    => 5.0,
					'message' => 'Custom mesh tariff message.',
				),
				'sefar' => array(
					'enabled' => true,
					'rate'    => 12.0,
					'message' => 'Custom Sefar tariff message.',
				),
			),
		);

		$settings = new Settings();

		$this->assertFalse(
			$settings->is_mesh_tariff_enabled()
		);

		$this->assertSame(
			0.05,
			$settings->get_mesh_tariff_rate()
		);

		$this->assertSame(
			'Custom mesh tariff message.',
			$settings->get_mesh_tariff_message()
		);

		$this->assertTrue(
			$settings->is_sefar_tariff_enabled()
		);

		$this->assertSame(
			0.12,
			$settings->get_sefar_tariff_rate()
		);

		$this->assertSame(
			'Custom Sefar tariff message.',
			$settings->get_sefar_tariff_message()
		);
	}

	/**
	 * Tests that missing stored values fall back to defaults.
	 *
	 * @return void
	 */
	public function test_partial_settings_fall_back_to_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh' => array(
					'rate' => 4.0,
				),
			),
		);

		$settings = new Settings();

		$this->assertTrue(
			$settings->is_mesh_tariff_enabled()
		);

		$this->assertSame(
			0.04,
			$settings->get_mesh_tariff_rate()
		);

		$this->assertStringContainsString(
			'3% tariff fee',
			$settings->get_mesh_tariff_message()
		);

		$this->assertTrue(
			$settings->is_sefar_tariff_enabled()
		);

		$this->assertSame(
			0.09,
			$settings->get_sefar_tariff_rate()
		);
	}

	/**
	 * Tests that non-array option data falls back to defaults.
	 *
	 * @return void
	 */
	public function test_non_array_option_data_falls_back_to_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = 'invalid';

		$settings = new Settings();

		$this->assertSame(
			$settings->get_defaults(),
			$settings->get_settings()
		);
	}

	/**
	 * Tests that malformed tariff settings fall back to defaults.
	 *
	 * @return void
	 */
	public function test_malformed_tariff_settings_fall_back_to_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh'  => 'invalid',
				'sefar' => 123,
			),
		);

		$settings = new Settings();

		$this->assertSame(
			$settings->get_defaults(),
			$settings->get_settings()
		);
	}

	/**
	 * Tests that numeric string percentages are normalized to floats.
	 *
	 * @return void
	 */
	public function test_numeric_string_rates_are_normalized_to_floats(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh'  => array(
					'rate' => '4.5',
				),
				'sefar' => array(
					'rate' => '11',
				),
			),
		);

		$settings = new Settings();

		$this->assertSame(
			4.5,
			$settings->get_settings()['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			11.0,
			$settings->get_settings()['tariffs']['sefar']['rate']
		);

		$this->assertSame(
			0.045,
			$settings->get_mesh_tariff_rate()
		);

		$this->assertSame(
			0.11,
			$settings->get_sefar_tariff_rate()
		);
	}

	/**
	 * Tests that invalid rates fall back to defaults.
	 *
	 * @return void
	 */
	public function test_invalid_rates_fall_back_to_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh'  => array(
					'rate' => 'invalid',
				),
				'sefar' => array(
					'rate' => array(),
				),
			),
		);

		$settings = new Settings();

		$this->assertSame(
			3.0,
			$settings->get_settings()['tariffs']['mesh']['rate']
		);

		$this->assertSame(
			9.0,
			$settings->get_settings()['tariffs']['sefar']['rate']
		);

		$this->assertSame(
			0.03,
			$settings->get_mesh_tariff_rate()
		);

		$this->assertSame(
			0.09,
			$settings->get_sefar_tariff_rate()
		);
	}

	/**
	 * Tests that invalid messages fall back to defaults.
	 *
	 * @return void
	 */
	public function test_invalid_messages_fall_back_to_defaults(): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => array(
				'mesh'  => array(
					'message' => array(),
				),
				'sefar' => array(
					'message' => 123,
				),
			),
		);

		$settings = new Settings();

		$this->assertStringContainsString(
			'3% tariff fee',
			$settings->get_mesh_tariff_message()
		);

		$this->assertStringContainsString(
			'9% tariff fee',
			$settings->get_sefar_tariff_message()
		);
	}
}
