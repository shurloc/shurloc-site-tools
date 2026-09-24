<?php
/**
 * Tests for Customer Journey cart-session correlation storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests cart-link validation, idempotency, and transaction boundaries.
 *
 * @phpstan-import-type SessionRow from \Shurloc_Test_WPDB
 */
final class JourneyCartLinkRepositoryTest extends TestCase {

	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready schema and one Journey session.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database                  = new Shurloc_Test_WPDB();
		$this->database->sessions[20]    = $this->session( id: 20, visitor_id: 12 );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the shared test database double.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Verify a link locks its session and uses the current table prefix.
	 *
	 * @return void
	 */
	public function test_link_stores_row_with_current_prefix(): void {
		$this->database->prefix = 'shop_';
		$hash                   = str_repeat( 'a', 64 );

		self::assertTrue(
			( new Journey_Cart_Link_Repository() )->link(
				cart_token_hash: $hash,
				visitor_id: 12,
				session_id: 20,
				observed_at: '2026-09-24 12:00:00'
			)
		);
		self::assertSame(
			array(
				'id'              => 1,
				'cart_token_hash' => $hash,
				'visitor_id'      => 12,
				'session_id'      => 20,
				'linked_at'       => '2026-09-24 12:00:00',
				'last_seen_at'    => '2026-09-24 12:00:00',
			),
			$this->database->cart_links[1]
		);
		self::assertSame(
			array( 'shop_shurloc_journey_sessions', 20, 12 ),
			$this->database->prepared_queries[0]['args']
		);
		self::assertSame(
			'shop_shurloc_journey_cart_links',
			$this->database->prepared_queries[1]['args'][0]
		);
		self::assertSame( 'START TRANSACTION', $this->database->queries[0] );
		self::assertStringStartsWith( 'INSERT INTO %i', $this->database->queries[1] );
		self::assertSame( 'COMMIT', $this->database->queries[2] );
	}

	/**
	 * Verify retries preserve the first link and never regress activity time.
	 *
	 * @return void
	 */
	public function test_repeated_and_out_of_order_links_are_idempotent(): void {
		$repository = new Journey_Cart_Link_Repository();
		$hash       = str_repeat( 'b', 64 );

		self::assertTrue( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );
		self::assertTrue( $repository->link( $hash, 12, 20, '2026-09-24 11:59:00' ) );
		self::assertTrue( $repository->link( $hash, 12, 20, '2026-09-24 12:05:00' ) );

		self::assertCount( 1, $this->database->cart_links );
		self::assertSame( '2026-09-24 12:00:00', $this->database->cart_links[1]['linked_at'] );
		self::assertSame( '2026-09-24 12:05:00', $this->database->cart_links[1]['last_seen_at'] );
	}

	/**
	 * Verify the unique pair permits several Journey sessions per cart token.
	 *
	 * @return void
	 */
	public function test_cart_token_can_link_to_multiple_sessions(): void {
		$this->database->sessions[21] = $this->session( id: 21, visitor_id: 12 );
		$repository                   = new Journey_Cart_Link_Repository();
		$hash                         = str_repeat( 'c', 64 );

		self::assertTrue( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );
		self::assertTrue( $repository->link( $hash, 12, 21, '2026-09-24 13:00:00' ) );
		self::assertCount( 2, $this->database->cart_links );
		self::assertSame( 20, $this->database->cart_links[1]['session_id'] );
		self::assertSame( 21, $this->database->cart_links[2]['session_id'] );
	}

	/**
	 * Verify a missing or mismatched session cannot create an orphan link.
	 *
	 * @return void
	 */
	public function test_session_must_belong_to_visitor(): void {
		$repository = new Journey_Cart_Link_Repository();
		$hash       = str_repeat( 'd', 64 );

		self::assertFalse( $repository->link( $hash, 13, 20, '2026-09-24 12:00:00' ) );
		self::assertFalse( $repository->link( $hash, 12, 99, '2026-09-24 12:00:00' ) );
		self::assertSame( array(), $this->database->cart_links );
		self::assertSame( 'ROLLBACK', $this->database->queries[1] );
		self::assertSame( 'ROLLBACK', $this->database->queries[3] );
	}

	/**
	 * Verify malformed input and unavailable schemas fail before database work.
	 *
	 * @return void
	 */
	public function test_invalid_input_and_unready_schema_fail_before_transaction(): void {
		$repository = new Journey_Cart_Link_Repository();

		self::assertFalse( $repository->link( str_repeat( 'A', 64 ), 12, 20, '2026-09-24 12:00:00' ) );
		self::assertFalse( $repository->link( str_repeat( 'e', 63 ), 12, 20, '2026-09-24 12:00:00' ) );
		self::assertFalse( $repository->link( str_repeat( 'e', 64 ), 0, 20, '2026-09-24 12:00:00' ) );
		self::assertFalse( $repository->link( str_repeat( 'e', 64 ), 12, 0, '2026-09-24 12:00:00' ) );
		self::assertFalse( $repository->link( str_repeat( 'e', 64 ), 12, 20, 'invalid' ) );
		self::assertFalse( $repository->link( str_repeat( 'e', 64 ), 12, 20, '2026-02-30 12:00:00' ) );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertFalse( $repository->link( str_repeat( 'e', 64 ), 12, 20, '2026-09-24 12:00:00' ) );
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Verify database failures roll back all cart-link changes.
	 *
	 * @return void
	 */
	public function test_database_failures_roll_back(): void {
		$repository = new Journey_Cart_Link_Repository();
		$hash       = str_repeat( 'f', 64 );

		$this->database->fail_start = true;
		self::assertFalse( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );

		$this->database->fail_start          = false;
		$this->database->fail_session_select = true;
		self::assertFalse( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );

		$this->database->fail_session_select   = false;
		$this->database->fail_cart_link_upsert = true;
		self::assertFalse( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );

		$this->database->fail_cart_link_upsert = false;
		$this->database->fail_commit           = true;
		self::assertFalse( $repository->link( $hash, 12, 20, '2026-09-24 12:00:00' ) );

		self::assertSame( array(), $this->database->cart_links );
		self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
	}

	/**
	 * Verify cleanup can delete dependent links by session or visitor.
	 *
	 * @return void
	 */
	public function test_cleanup_deletes_links_by_session_and_visitor(): void {
		$this->database->sessions[21] = $this->session( id: 21, visitor_id: 12 );
		$this->database->sessions[22] = $this->session( id: 22, visitor_id: 13 );
		$repository                   = new Journey_Cart_Link_Repository();

		self::assertTrue( $repository->link( str_repeat( 'a', 64 ), 12, 20, '2026-09-24 12:00:00' ) );
		self::assertTrue( $repository->link( str_repeat( 'b', 64 ), 12, 21, '2026-09-24 12:01:00' ) );
		self::assertTrue( $repository->link( str_repeat( 'c', 64 ), 13, 22, '2026-09-24 12:02:00' ) );

		self::assertTrue( $repository->delete_for_sessions( session_ids: array( 20 ) ) );
		self::assertCount( 2, $this->database->cart_links );
		self::assertTrue( $repository->delete_for_visitor( visitor_id: 12 ) );
		self::assertCount( 1, $this->database->cart_links );
		self::assertSame( 13, $this->database->cart_links[3]['visitor_id'] );
		self::assertSame(
			array( 'wp_shurloc_journey_cart_links', 20 ),
			$this->database->prepared_queries[6]['args']
		);
		self::assertSame(
			array( 'wp_shurloc_journey_cart_links', 12 ),
			$this->database->prepared_queries[7]['args']
		);
	}

	/**
	 * Verify cleanup rejects malformed identifiers before database work.
	 *
	 * @return void
	 */
	public function test_cleanup_rejects_invalid_identifiers(): void {
		$repository = new Journey_Cart_Link_Repository();

		self::assertFalse( $repository->delete_for_sessions( session_ids: array( 0 ) ) );
		self::assertFalse( $repository->delete_for_sessions( session_ids: array( 20, 20 ) ) );
		self::assertFalse( $repository->delete_for_visitor( visitor_id: 0 ) );
		self::assertTrue( $repository->delete_for_sessions( session_ids: array() ) );
		self::assertSame( array(), $this->database->queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertFalse( $repository->delete_for_sessions( session_ids: array() ) );
		self::assertFalse( $repository->delete_for_visitor( visitor_id: 12 ) );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Verify cleanup reports database failures to its transaction-owning caller.
	 *
	 * @return void
	 */
	public function test_cleanup_reports_database_failures(): void {
		$repository                            = new Journey_Cart_Link_Repository();
		$this->database->fail_cart_link_delete = true;

		self::assertFalse( $repository->delete_for_sessions( session_ids: array( 20 ) ) );
		self::assertFalse( $repository->delete_for_visitor( visitor_id: 12 ) );
	}

	/**
	 * Build a valid Journey session fixture.
	 *
	 * @param int $id         Session ID.
	 * @param int $visitor_id Visitor ID.
	 * @return SessionRow Session row.
	 */
	private function session( int $id, int $visitor_id ): array {
		return array(
			'id'                     => $id,
			'visitor_id'             => $visitor_id,
			'identity_period_id'     => 3,
			'user_id_at_start'       => null,
			'began_authenticated'    => 0,
			'started_at'             => '2026-09-24 12:00:00',
			'last_activity_at'       => '2026-09-24 12:00:00',
			'ended_at'               => null,
			'landing_path'           => null,
			'referrer_host'          => null,
			'utm_source'             => null,
			'utm_medium'             => null,
			'utm_campaign'           => null,
			'utm_term'               => null,
			'utm_content'            => null,
			'page_view_count'        => 0,
			'product_view_count'     => 0,
			'cart_add_count'         => 0,
			'cart_remove_count'      => 0,
			'added_quantity'         => '0.0000',
			'removed_quantity'       => '0.0000',
			'checkout_started_count' => 0,
			'order_created_count'    => 0,
			'active_ms'              => 0,
		);
	}
}
