<?php
/**
 * Tests for Tariff_Tooltips.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Frontend;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Tests tariff tooltip frontend assets.
 */
final class TariffTooltipsTest extends TestCase {

	/**
	 * Sets up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_enqueued_scripts']  = array();
		$GLOBALS['shurloc_test_styles']            = array();
		$GLOBALS['shurloc_test_localized_scripts'] = array();
		$GLOBALS['shurloc_test_is_cart']           = false;
		$GLOBALS['shurloc_test_is_checkout']       = false;
		$GLOBALS['shurloc_test_options']           = array();
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
			$GLOBALS['shurloc_test_enqueued_scripts'],
			$GLOBALS['shurloc_test_styles'],
			$GLOBALS['shurloc_test_localized_scripts'],
			$GLOBALS['shurloc_test_is_cart'],
			$GLOBALS['shurloc_test_is_checkout'],
			$GLOBALS['shurloc_test_options']
		);

		parent::tearDown();
	}

	/**
	 * Tests that the frontend asset hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_enqueue_scripts_action(): void {
		$tooltips = $this->create_tooltips();

		$tooltips->register();

		$this->assertSame(
			array(
				array( $tooltips, 'enqueue_assets' ),
			),
			$GLOBALS['shurloc_test_actions']['wp_enqueue_scripts']
		);
	}

	/**
	 * Tests that assets are not enqueued outside the cart and checkout.
	 *
	 * @return void
	 */
	public function test_assets_are_not_enqueued_on_other_pages(): void {
		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$this->assertSame(
			array(),
			$GLOBALS['shurloc_test_styles']
		);

		$this->assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		$this->assertSame(
			array(),
			$GLOBALS['shurloc_test_localized_scripts']
		);
	}

	/**
	 * Tests that assets are enqueued on the cart page.
	 *
	 * @return void
	 */
	public function test_assets_are_enqueued_on_cart_page(): void {
		$GLOBALS['shurloc_test_is_cart'] = true;

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$this->assert_assets_enqueued();
	}

	/**
	 * Tests that assets are enqueued on the checkout page.
	 *
	 * @return void
	 */
	public function test_assets_are_enqueued_on_checkout_page(): void {
		$GLOBALS['shurloc_test_is_checkout'] = true;

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$this->assert_assets_enqueued();
	}

	/**
	 * Tests the default tariff tooltip configuration passed to JavaScript.
	 *
	 * @return void
	 */
	public function test_tariff_configuration_is_localized(): void {
		$GLOBALS['shurloc_test_is_cart'] = true;

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$this->assertCount(
			1,
			$GLOBALS['shurloc_test_localized_scripts']
		);

		$localized = $GLOBALS['shurloc_test_localized_scripts'][0];

		$this->assertSame(
			'shurloc-tariff-tooltips',
			$localized['handle']
		);

		$this->assertSame(
			'shurlocTariffTooltips',
			$localized['object_name']
		);

		$this->assertSame(
			'Raw material import tariff',
			$localized['data']['fees'][0]['label']
		);

		$this->assertStringContainsString(
			'3% tariff fee',
			$localized['data']['fees'][0]['message']
		);

		$this->assertSame(
			'Sefar Mesh Tariff',
			$localized['data']['fees'][1]['label']
		);

		$this->assertStringContainsString(
			'9% tariff fee',
			$localized['data']['fees'][1]['message']
		);
	}

	/**
	 * Tests that custom tariff messages are localized.
	 *
	 * @return void
	 */
	public function test_custom_tariff_messages_are_localized(): void {
		$GLOBALS['shurloc_test_is_cart'] = true;

		$this->set_tariff_settings(
			array(
				'mesh'  => array(
					'message' => 'Custom mesh tariff message.',
				),
				'sefar' => array(
					'message' => 'Custom Sefar tariff message.',
				),
			)
		);

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$localized = $GLOBALS['shurloc_test_localized_scripts'][0];

		$this->assertSame(
			'Custom mesh tariff message.',
			$localized['data']['fees'][0]['message']
		);

		$this->assertSame(
			'Custom Sefar tariff message.',
			$localized['data']['fees'][1]['message']
		);
	}

	/**
	 * Tests that a disabled mesh tariff is omitted from tooltip data.
	 *
	 * @return void
	 */
	public function test_disabled_mesh_tariff_is_omitted_from_tooltip_data(): void {
		$GLOBALS['shurloc_test_is_cart'] = true;

		$this->set_tariff_settings(
			array(
				'mesh' => array(
					'enabled' => false,
				),
			)
		);

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$localized = $GLOBALS['shurloc_test_localized_scripts'][0];

		$this->assertCount(
			1,
			$localized['data']['fees']
		);

		$this->assertSame(
			'Sefar Mesh Tariff',
			$localized['data']['fees'][0]['label']
		);
	}

	/**
	 * Tests that a disabled Sefar tariff is omitted from tooltip data.
	 *
	 * @return void
	 */
	public function test_disabled_sefar_tariff_is_omitted_from_tooltip_data(): void {
		$GLOBALS['shurloc_test_is_cart'] = true;

		$this->set_tariff_settings(
			array(
				'sefar' => array(
					'enabled' => false,
				),
			)
		);

		$tooltips = $this->create_tooltips();

		$tooltips->enqueue_assets();

		$localized = $GLOBALS['shurloc_test_localized_scripts'][0];

		$this->assertCount(
			1,
			$localized['data']['fees']
		);

		$this->assertSame(
			'Raw material import tariff',
			$localized['data']['fees'][0]['label']
		);
	}

	/**
	 * Creates the tariff tooltip handler.
	 *
	 * @return Tariff_Tooltips Tariff tooltip handler.
	 */
	private function create_tooltips(): Tariff_Tooltips {
		return new Tariff_Tooltips(
			settings: new Settings()
		);
	}

	/**
	 * Stores tariff settings for a test.
	 *
	 * @param array<string, mixed> $tariffs Tariff settings.
	 * @return void
	 */
	private function set_tariff_settings(
		array $tariffs
	): void {
		$GLOBALS['shurloc_test_options'][ Settings::OPTION_NAME ] = array(
			'tariffs' => $tariffs,
		);
	}

	/**
	 * Asserts that the tariff tooltip assets were enqueued.
	 *
	 * @return void
	 */
	private function assert_assets_enqueued(): void {
		$this->assertSame(
			SHURLOC_SITE_TOOLS_URL . 'assets/checkout/css/shurloc-tariff-tooltips.css',
			$GLOBALS['shurloc_test_styles']['shurloc-tariff-tooltips']['src']
		);

		$this->assertSame(
			SHURLOC_SITE_TOOLS_VERSION,
			$GLOBALS['shurloc_test_styles']['shurloc-tariff-tooltips']['ver']
		);

		$this->assertCount(
			1,
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		$this->assertSame(
			'shurloc-tariff-tooltips',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['handle']
		);

		$this->assertSame(
			SHURLOC_SITE_TOOLS_URL . 'assets/checkout/js/shurloc-tariff-tooltips.js',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['src']
		);

		$this->assertSame(
			array( 'jquery' ),
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['deps']
		);

		$this->assertSame(
			SHURLOC_SITE_TOOLS_VERSION,
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['ver']
		);

		$this->assertTrue(
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['in_footer']
		);
	}
}
