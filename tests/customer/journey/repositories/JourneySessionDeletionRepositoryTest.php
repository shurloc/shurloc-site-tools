<?php
/**
 * Tests for individual Customer Journey session deletion.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Tests dependency order, isolation, and rollback for one-session deletion.
 */
final class JourneySessionDeletionRepositoryTest extends TestCase {
	/**
	 * Database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Repository under test.
	 *
	 * @var Journey_Session_Deletion_Repository
	 */
	private Journey_Session_Deletion_Repository $repository;

	/**
	 * Prepare two journeys belonging to one retained visitor identity.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->reset_fixture();
	}

	/**
	 * Restore shared database and option state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * One deletion removes only its dependent records in transaction order.
	 *
	 * @return void
	 */
	public function test_deletes_one_journey_and_preserves_other_history(): void {
		self::assertSame( 1, $this->repository->delete_by_id( 20 ) );

		self::assertArrayNotHasKey( 20, $this->database->sessions );
		self::assertArrayHasKey( 21, $this->database->sessions );
		self::assertSame( array( 102 ), array_keys( $this->database->events ) );
		self::assertSame( array( 202 ), array_keys( $this->database->cart_links ) );
		self::assertArrayHasKey( 3, $this->database->periods );
		self::assertArrayHasKey( 12, $this->database->visitors );
		self::assertSame(
			array(
				'START TRANSACTION',
				'DELETE FROM %i WHERE session_id IN (%d)',
				'DELETE FROM %i WHERE session_id = %d',
				'DELETE FROM %i WHERE id = %d AND visitor_id = %d',
				'COMMIT',
			),
			$this->database->queries
		);
		self::assertSame( array( 'shop_shurloc_journey_sessions', 20 ), $this->database->prepared_queries[0]['args'] );
		self::assertSame( array( 'shop_shurloc_journey_cart_links', 20 ), $this->database->prepared_queries[1]['args'] );
		self::assertSame( array( 'shop_shurloc_journey_events', 20 ), $this->database->prepared_queries[2]['args'] );
		self::assertSame( array( 'shop_shurloc_journey_sessions', 20, 12 ), $this->database->prepared_queries[3]['args'] );
		self::assertStringContainsString( 'LIMIT 2 FOR UPDATE', $this->database->prepared_queries[0]['query'] );
	}

	/**
	 * Selected customers and visitors delete every unique matching session.
	 *
	 * @return void
	 */
	public function test_deletes_all_journeys_for_selected_subjects_atomically(): void {
		$this->database->result_queue       = array(
			array(
				(object) array( 'id' => '20' ),
				(object) array( 'id' => '21' ),
			),
			array(
				(object) array( 'id' => '21' ),
				(object) array( 'id' => '22' ),
			),
		);
		$this->database->query_result_queue = array( 6, 3 );

		$deleted = $this->repository->delete_by_subjects(
			array(
				array(
					'type' => 'customer',
					'id'   => 7,
				),
				array(
					'type' => 'visitor',
					'id'   => 12,
				),
			)
		);

		self::assertSame( 3, $deleted );
		self::assertSame(
			array(
				'START TRANSACTION',
				'DELETE FROM %i WHERE session_id IN (%d, %d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d, %d)',
				'DELETE FROM %i WHERE %i IN (%d, %d, %d)',
				'COMMIT',
			),
			$this->database->queries
		);

		$customer_query = $this->database->prepared_queries[0];
		self::assertSame(
			array(
				'shop_shurloc_journey_sessions',
				'shop_shurloc_journey_identity_periods',
				7,
				7,
				'shop_shurloc_journey_events',
				7,
			),
			$customer_query['args']
		);
		self::assertStringContainsString( 'p.user_id = %d OR s.user_id_at_start = %d', $customer_query['query'] );
		self::assertStringContainsString( 'e.user_id_at_event = %d', $customer_query['query'] );
		self::assertStringContainsString( 'FOR UPDATE', $customer_query['query'] );

		$visitor_query = $this->database->prepared_queries[1];
		self::assertSame(
			array(
				'shop_shurloc_journey_sessions',
				'shop_shurloc_journey_visitors',
				12,
				'shop_shurloc_journey_identity_periods',
			),
			$visitor_query['args']
		);
		self::assertStringContainsString( 'p.user_id IS NOT NULL', $visitor_query['query'] );
		self::assertStringContainsString( 'FOR UPDATE', $visitor_query['query'] );

		self::assertSame(
			array( 'shop_shurloc_journey_cart_links', 20, 21, 22 ),
			$this->database->prepared_queries[2]['args']
		);
		self::assertSame(
			array( 'shop_shurloc_journey_events', 'session_id', 20, 21, 22 ),
			$this->database->prepared_queries[3]['args']
		);
		self::assertSame(
			array( 'shop_shurloc_journey_sessions', 'id', 20, 21, 22 ),
			$this->database->prepared_queries[4]['args']
		);
	}

	/**
	 * A selected subject without sessions commits as a successful no-op.
	 *
	 * @return void
	 */
	public function test_bulk_deletion_with_no_matching_journeys_returns_zero(): void {
		$this->database->result_queue = array( array() );

		self::assertSame(
			0,
			$this->repository->delete_by_subjects(
				array(
					array(
						'type' => 'customer',
						'id'   => 7,
					),
				)
			)
		);
		self::assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->database->queries );
	}

	/**
	 * Invalid subject selections fail before starting a transaction.
	 *
	 * @return void
	 */
	public function test_invalid_bulk_subjects_fail_closed(): void {
		$valid = array(
			array(
				'type' => 'customer',
				'id'   => 7,
			),
		);

		self::assertNull( $this->repository->delete_by_subjects( array() ) );
		self::assertNull( $this->repository->delete_by_subjects( array_fill( 0, 51, $valid[0] ) ) );
		self::assertNull( $this->repository->delete_by_subjects( array( $valid[0], $valid[0] ) ) );
		self::assertNull(
			$this->repository->delete_by_subjects(
				array(
					array(
						'type' => 'unknown',
						'id'   => 7,
					),
				)
			)
		);
		self::assertNull(
			$this->repository->delete_by_subjects(
				array(
					array(
						'type' => 'visitor',
						'id'   => 0,
					),
				)
			)
		);
		self::assertNull(
			$this->repository->delete_by_subjects(
				array(
					array(
						'id'   => 7,
						'type' => 'customer',
					),
				)
			)
		);

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $this->repository->delete_by_subjects( $valid ) );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Every bulk dependency failure rolls back the whole subject selection.
	 *
	 * @return void
	 */
	public function test_bulk_deletion_failures_roll_back(): void {
		$subjects = array(
			array(
				'type' => 'customer',
				'id'   => 7,
			),
		);
		$failures = array(
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->result_queue = array( null );
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->result_queue         = array( array( (object) array( 'id' => '20' ) ) );
				$database->fail_cart_link_delete = true;
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->result_queue       = array( array( (object) array( 'id' => '20' ) ) );
				$database->query_result_queue = array( false );
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->result_queue       = array( array( (object) array( 'id' => '20' ) ) );
				$database->query_result_queue = array( 2, 0 );
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->result_queue       = array( array( (object) array( 'id' => '20' ) ) );
				$database->query_result_queue = array( 2, 1 );
				$database->fail_commit        = true;
			},
		);

		foreach ( $failures as $failure ) {
			$this->reset_fixture();
			$failure( $this->database );

			self::assertNull( $this->repository->delete_by_subjects( $subjects ) );
			self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
		}
	}

	/**
	 * Repeating deletion for an absent journey is a successful no-op.
	 *
	 * @return void
	 */
	public function test_missing_journey_returns_zero_without_touching_dependents(): void {
		$original_sessions   = $this->database->sessions;
		$original_events     = $this->database->events;
		$original_cart_links = $this->database->cart_links;

		self::assertSame( 0, $this->repository->delete_by_id( 99 ) );
		self::assertSame( $original_sessions, $this->database->sessions );
		self::assertSame( $original_events, $this->database->events );
		self::assertSame( $original_cart_links, $this->database->cart_links );
		self::assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->database->queries );
	}

	/**
	 * Invalid input and unavailable schema prevent all database work.
	 *
	 * @return void
	 */
	public function test_invalid_input_and_schema_gate_fail_before_transaction(): void {
		self::assertNull( $this->repository->delete_by_id( 0 ) );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $this->repository->delete_by_id( 20 ) );
		self::assertSame( array(), $this->database->queries );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Every dependency and commit failure restores the complete journey.
	 *
	 * @return void
	 */
	public function test_transaction_failures_roll_back_all_dependent_records(): void {
		$failures = array(
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->fail_session_select = true;
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->fail_cart_link_delete = true;
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->fail_event_delete = true;
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->fail_session_delete = true;
			},
			static function ( Shurloc_Test_WPDB $database ): void {
				$database->fail_commit = true;
			},
		);

		foreach ( $failures as $failure ) {
			$this->reset_fixture();
			$failure( $this->database );

			self::assertNull( $this->repository->delete_by_id( 20 ) );
			self::assertArrayHasKey( 20, $this->database->sessions );
			self::assertArrayHasKey( 100, $this->database->events );
			self::assertArrayHasKey( 200, $this->database->cart_links );
			self::assertSame( 'ROLLBACK', $this->database->queries[ count( $this->database->queries ) - 1 ] );
		}
	}

	/**
	 * A transaction-start failure leaves the journey untouched.
	 *
	 * @return void
	 */
	public function test_transaction_start_failure_returns_null_without_rollback(): void {
		$this->database->fail_start = true;

		self::assertNull( $this->repository->delete_by_id( 20 ) );
		self::assertArrayHasKey( 20, $this->database->sessions );
		self::assertSame( array( 'START TRANSACTION' ), $this->database->queries );
	}

	/**
	 * Replace the shared database with a complete two-session fixture.
	 *
	 * @return void
	 */
	private function reset_fixture(): void {
		$this->database               = new Shurloc_Test_WPDB();
		$this->database->prefix       = 'shop_';
		$this->database->visitors[12] = true;
		$this->database->periods[3]   = array(
			'id'         => 3,
			'visitor_id' => 12,
			'user_id'    => null,
			'started_at' => '2026-09-01 12:00:00',
			'linked_at'  => null,
			'ended_at'   => null,
		);
		$this->database->sessions[20] = $this->session_row( id: 20, started_at: '2026-09-01 12:00:00' );
		$this->database->sessions[21] = $this->session_row( id: 21, started_at: '2026-09-02 12:00:00' );
		$this->database->events       = array(
			100 => array(
				'id'         => 100,
				'session_id' => 20,
			),
			101 => array(
				'id'         => 101,
				'session_id' => 20,
			),
			102 => array(
				'id'         => 102,
				'session_id' => 21,
			),
		);
		$this->database->cart_links   = array(
			200 => $this->cart_link_row( id: 200, session_id: 20 ),
			201 => $this->cart_link_row( id: 201, session_id: 20 ),
			202 => $this->cart_link_row( id: 202, session_id: 21 ),
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only wpdb replacement.
		$GLOBALS['wpdb']  = $this->database;
		$this->repository = new Journey_Session_Deletion_Repository();
	}

	/**
	 * Build a complete session fixture.
	 *
	 * @param int    $id         Session ID.
	 * @param string $started_at Start timestamp.
	 * @return array Session row.
	 * @phpstan-return array{id:int,visitor_id:int,identity_period_id:int,user_id_at_start:null,began_authenticated:int,started_at:string,last_activity_at:string,ended_at:null,landing_path:null,referrer_host:null,utm_source:null,utm_medium:null,utm_campaign:null,utm_term:null,utm_content:null,page_view_count:int,product_view_count:int,cart_add_count:int,cart_remove_count:int,added_quantity:string,removed_quantity:string,checkout_started_count:int,order_created_count:int,active_ms:int,event_count:int}
	 */
	private function session_row( int $id, string $started_at ): array {
		return array(
			'id'                     => $id,
			'visitor_id'             => 12,
			'identity_period_id'     => 3,
			'user_id_at_start'       => null,
			'began_authenticated'    => 0,
			'started_at'             => $started_at,
			'last_activity_at'       => $started_at,
			'ended_at'               => null,
			'landing_path'           => null,
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
	}

	/**
	 * Build one cart-link fixture.
	 *
	 * @param int $id         Link ID.
	 * @param int $session_id Session ID.
	 * @return array{id:int,cart_token_hash:string,visitor_id:int,session_id:int,linked_at:string,last_seen_at:string} Cart link.
	 */
	private function cart_link_row( int $id, int $session_id ): array {
		return array(
			'id'              => $id,
			'cart_token_hash' => hash( 'sha256', 'token-' . $id ),
			'visitor_id'      => 12,
			'session_id'      => $session_id,
			'linked_at'       => '2026-09-01 12:00:00',
			'last_seen_at'    => '2026-09-01 12:05:00',
		);
	}
}
