<?php
/**
 * Tests for Customer Journey report deletion administration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Visitor_Repository;
use Shurloc_Test_WPDB;

/**
 * Verify deletion authorization, dispatch, redirect feedback, and notices.
 */
final class JourneyReportDeletionControllerTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Controller under test.
	 *
	 * @var Journey_Report_Controller
	 */
	private Journey_Report_Controller $controller;

	/** Prepare a ready Journey schema and authorized administrator. */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options']              = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_action_metadata']      = array();
		$GLOBALS['shurloc_test_admin_referer_checks'] = array();
		$GLOBALS['shurloc_test_nonce_valid']          = true;
		$GLOBALS['shurloc_test_redirects']            = array();
		$GLOBALS['shurloc_test_throw_on_redirect']    = true;
		$GLOBALS['shurloc_test_user_capabilities']    = array( Journey_Report_Controller::CAPABILITY => true );
		$GLOBALS['shurloc_test_wp_die_messages']      = array();
		$_GET  = array();
		$_POST = array();

		$this->database         = new Shurloc_Test_WPDB();
		$this->database->prefix = 'shop_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;

		$timezone         = new DateTimeZone( 'America/Los_Angeles' );
		$this->controller = new Journey_Report_Controller(
			report_repository: new Journey_Report_Repository(),
			session_repository: new Journey_Report_Session_Repository(),
			visitor_repository: new Journey_Report_Visitor_Repository(),
			page_builder: new Journey_Report_Page_Builder(),
			renderer: new Journey_Report_Renderer(),
			timezone: $timezone,
			now: new DateTimeImmutable( '2026-09-21 12:00:00', $timezone )
		);
	}

	/** Restore shared request and WordPress test state. */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options']              = array();
		$GLOBALS['shurloc_test_actions']              = array();
		$GLOBALS['shurloc_test_action_metadata']      = array();
		$GLOBALS['shurloc_test_admin_referer_checks'] = array();
		$GLOBALS['shurloc_test_nonce_valid']          = true;
		$GLOBALS['shurloc_test_redirects']            = array();
		$GLOBALS['shurloc_test_throw_on_redirect']    = false;
		$GLOBALS['shurloc_test_user_capabilities']    = array();
		$GLOBALS['shurloc_test_wp_die_messages']      = array();
		$_GET  = array();
		$_POST = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/** The controller registers both of its admin hooks. */
	public function test_registers_deletion_and_asset_hooks(): void {
		$this->controller->register();

		self::assertSame(
			array( $this->controller, 'handle_delete' ),
			$GLOBALS['shurloc_test_actions'][ 'admin_post_' . Journey_Report_Controller::DELETE_ACTION ][0]
		);
		self::assertSame(
			array( $this->controller, 'enqueue_assets' ),
			$GLOBALS['shurloc_test_actions']['admin_enqueue_scripts'][0]
		);
	}

	/** An authorized, verified request deletes one session and keeps report context. */
	public function test_deletes_one_journey_and_redirects_to_the_filtered_report(): void {
		$this->seed_session();
		$_POST = array(
			'journey_session_id' => '20',
			'journey_subject'    => 'customer',
			'journey_user_id'    => '7',
			'journey_from'       => '2026-09-01',
			'journey_to'         => '2026-09-21',
		);

		$redirect = $this->handle_and_capture_redirect();

		self::assertArrayNotHasKey( 20, $this->database->sessions );
		self::assertArrayNotHasKey( 100, $this->database->events );
		self::assertSame( array( Journey_Report_Controller::DELETE_ACTION ), $GLOBALS['shurloc_test_admin_referer_checks'] );
		self::assertStringContainsString( 'page=shurloc-site-tools-customers', $redirect );
		self::assertStringContainsString( 'tab=journeys', $redirect );
		self::assertStringContainsString( 'journey_delete_result=deleted', $redirect );
		self::assertStringContainsString( 'journey_subject=customer', $redirect );
		self::assertStringContainsString( 'journey_user_id=7', $redirect );
		self::assertStringContainsString( 'journey_from=2026-09-01', $redirect );
		self::assertStringContainsString( 'journey_to=2026-09-21', $redirect );
		self::assertSame( 'COMMIT', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/** Missing and failed deletions receive distinct redirect results. */
	public function test_redirects_with_missing_and_failed_results(): void {
		$_POST = array( 'journey_session_id' => '99' );
		self::assertStringContainsString( 'journey_delete_result=missing', $this->handle_and_capture_redirect() );

		$GLOBALS['shurloc_test_redirects'] = array();
		$this->seed_session();
		$this->database->fail_event_delete = true;
		$_POST                             = array( 'journey_session_id' => '20' );
		self::assertStringContainsString( 'journey_delete_result=failed', $this->handle_and_capture_redirect() );
		self::assertArrayHasKey( 20, $this->database->sessions );
		self::assertArrayHasKey( 100, $this->database->events );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/** Capability, nonce, and ID validation prevent deletion before database work. */
	public function test_rejects_unauthorized_unverified_and_invalid_requests(): void {
		$_POST = array( 'journey_session_id' => '20' );
		$GLOBALS['shurloc_test_user_capabilities'][ Journey_Report_Controller::CAPABILITY ] = false;
		$this->assert_handler_dies_with( 'permission to delete Customer Journeys' );

		$GLOBALS['shurloc_test_user_capabilities'][ Journey_Report_Controller::CAPABILITY ] = true;
		$GLOBALS['shurloc_test_nonce_valid'] = false;
		$this->assert_handler_dies_with( 'deletion request could not be verified' );

		$GLOBALS['shurloc_test_nonce_valid'] = true;
		$_POST['journey_session_id']         = 'invalid';
		$this->assert_handler_dies_with( 'valid Customer Journey' );

		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $GLOBALS['shurloc_test_redirects'] );
	}

	/** Report rendering displays only recognized deletion feedback. */
	public function test_renders_deletion_result_notices(): void {
		$expectations = array(
			'deleted' => array( 'notice-success', 'Customer Journey deleted.' ),
			'missing' => array( 'notice-warning', 'already been deleted' ),
			'failed'  => array( 'notice-error', 'could not be deleted' ),
		);

		foreach ( $expectations as $result => $expected ) {
			$_GET                         = array( 'journey_delete_result' => $result );
			$this->database->result_queue = array( array(), array() );
			$output                       = $this->render();
			self::assertStringContainsString( $expected[0], $output );
			self::assertStringContainsString( $expected[1], $output );
		}

		$_GET                         = array( 'journey_delete_result' => 'forged' );
		$this->database->result_queue = array( array(), array() );
		self::assertStringNotContainsString( 'Customer Journey deleted.', $this->render() );
	}

	/** Seed one session and event for an individual deletion request. */
	private function seed_session(): void {
		$this->database->sessions[20] = array(
			'id'                     => 20,
			'visitor_id'             => 12,
			'identity_period_id'     => 3,
			'user_id_at_start'       => 7,
			'began_authenticated'    => 1,
			'started_at'             => '2026-09-01 12:00:00',
			'last_activity_at'       => '2026-09-01 12:05:00',
			'ended_at'               => null,
			'landing_path'           => '/products',
			'referrer_host'          => null,
			'utm_source'             => null,
			'utm_medium'             => null,
			'utm_campaign'           => null,
			'utm_term'               => null,
			'utm_content'            => null,
			'page_view_count'        => 1,
			'product_view_count'     => 0,
			'cart_add_count'         => 0,
			'cart_remove_count'      => 0,
			'added_quantity'         => '0.0000',
			'removed_quantity'       => '0.0000',
			'checkout_started_count' => 0,
			'order_created_count'    => 0,
			'active_ms'              => 1000,
			'event_count'            => 1,
		);
		$this->database->events[100]  = array(
			'id'         => 100,
			'session_id' => 20,
		);
	}

	/** Execute a valid handler through the redirect boundary. */
	private function handle_and_capture_redirect(): string {
		try {
			$this->controller->handle_delete();
			self::fail( 'Expected the redirect test double to stop before exit.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Test safe redirect', $exception->getMessage() );
		}

		$redirect = end( $GLOBALS['shurloc_test_redirects'] );
		self::assertIsString( $redirect );
		return $redirect;
	}

	/**
	 * Assert one invalid request terminates through wp_die().
	 *
	 * @param string $message Expected error message fragment.
	 * @return void
	 */
	private function assert_handler_dies_with( string $message ): void {
		try {
			$this->controller->handle_delete();
			self::fail( 'Expected wp_die() to terminate the request.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( $message, $exception->getMessage() );
		}
	}

	/** Capture report output. */
	private function render(): string {
		ob_start();
		$this->controller->render();
		return (string) ob_get_clean();
	}
}
