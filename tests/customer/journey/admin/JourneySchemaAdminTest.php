<?php
/**
 * Tests for Customer Journey schema administration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Keep the scoped redirect test stub with its controller tests.

/**
 * Capture a redirect before the handler exits the request.
 *
 * @param string $location Redirect destination.
 * @return bool Whether the test redirect was accepted.
 * @throws RuntimeException When the test needs to stop before exit.
 */
function wp_safe_redirect( string $location ): bool {
	$GLOBALS['shurloc_journey_schema_redirect'] = $location;

	if ( $GLOBALS['shurloc_journey_schema_throw_on_redirect'] ) {
		throw new RuntimeException( 'Journey schema redirect' );
	}

	return true;
}

/**
 * Tests the Journey schema notice and retry action.
 */
final class JourneySchemaAdminTest extends TestCase {
	/**
	 * Controller under test.
	 *
	 * @var Journey_Schema_Admin
	 */
	private Journey_Schema_Admin $controller;

	/**
	 * Prepare test options, hooks, and a small lock-release database double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_actions']                     = array();
		$GLOBALS['shurloc_test_action_metadata']             = array();
		$GLOBALS['shurloc_test_options']                     = array();
		$GLOBALS['shurloc_test_nonce_fields']                = array();
		$GLOBALS['shurloc_test_user_capabilities']           = array();
		$GLOBALS['shurloc_test_admin_referer_checks']        = array();
		$GLOBALS['shurloc_test_nonce_valid']                 = true;
		$GLOBALS['shurloc_test_wp_die_messages']             = array();
		$GLOBALS['shurloc_journey_schema_redirect']          = '';
		$GLOBALS['shurloc_journey_schema_throw_on_redirect'] = true;
		$GLOBALS['shurloc_journey_schema_updates']           = 0;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Install a test-only database double.
		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public string $prefix = 'wp_';

			/**
			 * Options table.
			 *
			 * @var string
			 */
			public string $options = 'wp_options';

			/**
			 * Return charset SQL for the attempted migration.
			 *
			 * @return string Charset SQL.
			 */
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}

			/**
			 * Return the query unchanged; only a lock release is expected.
			 *
			 * @param string $query SQL query.
			 * @param mixed  ...$args Placeholder arguments.
			 * @return string Query.
			 */
			public function prepare( string $query, mixed ...$args ): string {
				unset( $args );
				return $query;
			}

			/**
			 * Simulate deleting the migration lock.
			 *
			 * @param string $query SQL query.
			 * @return int Deleted rows.
			 */
			public function query( string $query ): int {
				unset( $query );
				unset( $GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] );
				return 1;
			}
		};

		$migrator = new Journey_Schema_Migrator(
			schema_updater: static function ( string $statement ): void {
				unset( $statement );
				++$GLOBALS['shurloc_journey_schema_updates'];
				throw new RuntimeException( 'Simulated database error.' );
			}
		);

		$this->controller = new Journey_Schema_Admin( migrator: $migrator );
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_actions']                     = array();
		$GLOBALS['shurloc_test_action_metadata']             = array();
		$GLOBALS['shurloc_test_options']                     = array();
		$GLOBALS['shurloc_test_nonce_fields']                = array();
		$GLOBALS['shurloc_test_user_capabilities']           = array();
		$GLOBALS['shurloc_test_admin_referer_checks']        = array();
		$GLOBALS['shurloc_test_nonce_valid']                 = true;
		$GLOBALS['shurloc_test_wp_die_messages']             = array();
		$GLOBALS['shurloc_journey_schema_redirect']          = '';
		$GLOBALS['shurloc_journey_schema_throw_on_redirect'] = false;
		$GLOBALS['shurloc_journey_schema_updates']           = 0;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared test-only database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Verify both admin hooks are registered.
	 *
	 * @return void
	 */
	public function test_register_adds_notice_and_retry_hooks(): void {
		$this->controller->register();

		self::assertContains(
			array( $this->controller, 'render_notice' ),
			$GLOBALS['shurloc_test_actions']['admin_notices']
		);
		self::assertContains(
			array( $this->controller, 'handle_retry' ),
			$GLOBALS['shurloc_test_actions']['admin_post_shurloc_retry_journey_schema']
		);
	}

	/**
	 * Verify no notice appears after the current schema is installed.
	 *
	 * @return void
	 */
	public function test_ready_schema_has_no_notice(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 1;

		ob_start();
		$this->controller->render_notice();
		$output = ob_get_clean();

		self::assertSame( '', $output );
		self::assertSame( array(), $GLOBALS['shurloc_test_nonce_fields'] );
	}

	/**
	 * Verify an uninstalled schema shows a nonce-protected retry form.
	 *
	 * @return void
	 */
	public function test_pending_schema_shows_retry_form_without_running_migration(): void {
		ob_start();
		$this->controller->render_notice();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'notice notice-error', $output );
		self::assertStringContainsString( 'schema needs an upgrade', $output );
		self::assertStringContainsString( 'admin-post.php', $output );
		self::assertStringContainsString( 'shurloc_retry_journey_schema', $output );
		self::assertStringContainsString( 'Retry Journey schema migration', $output );
		self::assertSame(
			array(
				array(
					'action' => 'shurloc_retry_journey_schema',
					'name'   => '_wpnonce',
				),
			),
			$GLOBALS['shurloc_test_nonce_fields']
		);
		self::assertSame( 0, $GLOBALS['shurloc_journey_schema_updates'] );
	}

	/**
	 * Verify a previous migration error is described in the notice.
	 *
	 * @return void
	 */
	public function test_failed_schema_shows_failure_and_retry(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ] =
			'migration_failed';

		ob_start();
		$this->controller->render_notice();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'database migration failed', $output );
		self::assertStringContainsString( 'Retry Journey schema migration', $output );
	}

	/**
	 * Verify older code does not offer to migrate a newer installed schema.
	 *
	 * @return void
	 */
	public function test_newer_schema_shows_notice_without_retry(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 2;

		ob_start();
		$this->controller->render_notice();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'newer than this plugin supports', $output );
		self::assertStringNotContainsString( '<form', $output );
	}

	/**
	 * Verify a user without the admin capability sees no schema details.
	 *
	 * @return void
	 */
	public function test_notice_is_hidden_from_unauthorized_user(): void {
		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;

		ob_start();
		$this->controller->render_notice();
		$output = ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Verify the retry action rejects a user without the admin capability.
	 *
	 * @return void
	 */
	public function test_retry_rejects_unauthorized_user(): void {
		$GLOBALS['shurloc_test_user_capabilities']['manage_options'] = false;

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'not allowed to retry' );

		$this->controller->handle_retry();
	}

	/**
	 * Verify the retry action rejects an invalid nonce before schema work.
	 *
	 * @return void
	 */
	public function test_retry_rejects_invalid_nonce(): void {
		$GLOBALS['shurloc_test_nonce_valid'] = false;

		try {
			$this->controller->handle_retry();
			self::fail( 'Expected invalid nonce to stop the retry.' );
		} catch ( RuntimeException $error ) {
			self::assertStringContainsString( 'could not be verified', $error->getMessage() );
		}

		self::assertSame(
			array( 'shurloc_retry_journey_schema' ),
			$GLOBALS['shurloc_test_admin_referer_checks']
		);
		self::assertSame( 0, $GLOBALS['shurloc_journey_schema_updates'] );
		self::assertSame( '', $GLOBALS['shurloc_journey_schema_redirect'] );
	}

	/**
	 * Verify the retry endpoint rejects a newer schema version.
	 *
	 * @return void
	 */
	public function test_retry_rejects_newer_schema(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 2;

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'A newer Journey schema cannot be retried' );

		$this->controller->handle_retry();
	}

	/**
	 * Verify a retry delegates to the migrator and keeps a failed version pending.
	 *
	 * @return void
	 */
	public function test_retry_records_failure_without_advancing_schema_version(): void {
		try {
			$this->controller->handle_retry();
			self::fail( 'Expected redirect after retry.' );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'Journey schema redirect', $error->getMessage() );
		}

		self::assertSame( 1, $GLOBALS['shurloc_journey_schema_updates'] );
		self::assertSame(
			'migration_failed',
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::FAILURE_OPTION ]
		);
		self::assertArrayNotHasKey(
			Journey_Schema_Migrator::VERSION_OPTION,
			$GLOBALS['shurloc_test_options']
		);
		self::assertSame(
			'https://example.com/wp-admin/admin.php?page=shurloc-site-tools-customers',
			$GLOBALS['shurloc_journey_schema_redirect']
		);
	}

	/**
	 * Verify an already completed schema retry redirects without schema work.
	 *
	 * @return void
	 */
	public function test_retry_is_safe_when_schema_is_already_ready(): void {
		$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::VERSION_OPTION ] = 1;

		try {
			$this->controller->handle_retry();
			self::fail( 'Expected redirect after retry.' );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'Journey schema redirect', $error->getMessage() );
		}

		self::assertSame( 0, $GLOBALS['shurloc_journey_schema_updates'] );
		self::assertSame(
			'https://example.com/wp-admin/admin.php?page=shurloc-site-tools-customers',
			$GLOBALS['shurloc_journey_schema_redirect']
		);
	}
}
