<?php
/**
 * Tests for the plugin bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Tests the plugin bootstrap.
 */
final class BootstrapTest extends TestCase {

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_filter_metadata']  = array();
		$GLOBALS['shurloc_test_activation_hooks'] = array();
		$GLOBALS['shurloc_test_options']          = array();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_filters']          = array();
		$GLOBALS['shurloc_test_filter_metadata']  = array();
		$GLOBALS['shurloc_test_activation_hooks'] = array();
		$GLOBALS['shurloc_test_options']          = array();

		parent::tearDown();
	}

	/**
	 * Verify the plugin bootstrap registers the shared admin menu.
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_shared_admin_menu(): void {

		shurloc_site_tools_bootstrap();

		self::assertArrayHasKey(
			'admin_menu',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['admin_menu'][0]['priority']
		);
	}

	/**
	 * Verify the plugin bootstrap registers its domains.
	 *
	 * @return void
	 */
	public function test_bootstrap_registers_domains(): void {

		shurloc_site_tools_bootstrap();

		/*
		 * Checkout domain.
		 */

		self::assertArrayHasKey(
			'admin_init',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_cart_calculate_fees',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'woocommerce_gateway_title',
			$GLOBALS['shurloc_test_filters']
		);

		/*
		 * Customer domain.
		 */

		self::assertArrayHasKey(
			'manage_users_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'pre_get_users',
			$GLOBALS['shurloc_test_actions']
		);

		self::assertArrayHasKey(
			'admin_post_shurloc_run_purchase_migration',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * Media domain.
		 */

		self::assertArrayHasKey(
			'manage_upload_columns',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'admin_enqueue_scripts',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * SEO domain.
		 */

		self::assertArrayHasKey(
			'wp_head',
			$GLOBALS['shurloc_test_actions']
		);

		/*
		 * Product domain.
		 */

		self::assertArrayHasKey(
			'woocommerce_structured_data_product',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'woocommerce_related_products',
			$GLOBALS['shurloc_test_filters']
		);

		self::assertArrayHasKey(
			'add_meta_boxes_product',
			$GLOBALS['shurloc_test_actions']
		);
	}

	/**
	 * Verify the main plugin file registers a working Journey activation hook.
	 *
	 * @return void
	 */
	public function test_plugin_activation_runs_journey_schema_migrator(): void {
		$plugin_file = realpath( dirname( __DIR__ ) . '/shurloc-site-tools.php' );
		self::assertIsString( $plugin_file );
		require_once $plugin_file;

		self::assertArrayHasKey(
			$plugin_file,
			$GLOBALS['shurloc_test_activation_hooks']
		);
		self::assertSame(
			__NAMESPACE__ . '\\shurloc_site_tools_activate_journey_schema',
			$GLOBALS['shurloc_test_activation_hooks'][ $plugin_file ]
		);

		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] =
			Journey_Schema_Migrator::CURRENT_VERSION + 1;
		$activation_callback = $GLOBALS['shurloc_test_activation_hooks'][ $plugin_file ];
		$activation_callback();

		self::assertSame(
			'newer_schema',
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ]
		);
		self::assertSame(
			Journey_Schema_Migrator::CURRENT_VERSION + 1,
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ]
		);
	}
}
