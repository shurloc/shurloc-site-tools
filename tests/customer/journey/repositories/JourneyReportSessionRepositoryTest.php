<?php
/**
 * Tests for Customer Journey report session context reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;
use stdClass;

/**
 * Verify bounded session metadata reads for report event pages.
 */
final class JourneyReportSessionRepositoryTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Prepare a ready Journey schema.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_options'] = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$this->database                  = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;
	}

	/**
	 * Restore shared test globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_options'] = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * One query fetches distinct requested sessions and preserves attribution.
	 *
	 * @return void
	 */
	public function test_loads_bounded_session_context_in_one_query(): void {
		$closed                  = $this->session_row( id: '9', user_id_at_start: '37' );
		$closed->ended_at        = '2026-09-16 12:17:00';
		$this->database->prefix  = 'shop_';
		$this->database->results = array(
			$this->session_row( id: '8', user_id_at_start: null ),
			$closed,
		);

		$contexts = ( new Journey_Report_Session_Repository() )->get_by_ids( session_ids: array( 8, 9, 8 ) );

		self::assertNotNull( $contexts );
		self::assertCount( 2, $contexts );
		self::assertSame( 8, $contexts[8]['id'] );
		self::assertSame( 12, $contexts[8]['visitor_id'] );
		self::assertNull( $contexts[8]['user_id_at_start'] );
		self::assertFalse( $contexts[8]['began_authenticated'] );
		self::assertSame( '/products', $contexts[8]['landing_path'] );
		self::assertSame( 'newsletter', $contexts[8]['utm_source'] );
		self::assertSame( 37, $contexts[9]['user_id_at_start'] );
		self::assertTrue( $contexts[9]['began_authenticated'] );
		self::assertSame( '2026-09-16 12:17:00', $contexts[9]['ended_at'] );

		self::assertCount( 1, $this->database->prepared_queries );
		$query = $this->database->prepared_queries[0];
		self::assertSame( array( 'shop_shurloc_journey_sessions', 8, 9 ), $query['args'] );
		self::assertStringContainsString( 'WHERE id IN (%d, %d) ORDER BY id ASC', $query['query'] );
		self::assertStringNotContainsString( 'page_view_count', $query['query'] );
		self::assertStringNotContainsString( 'active_ms', $query['query'] );
	}

	/**
	 * Empty pages perform no lookup and missing session rows are tolerated.
	 *
	 * @return void
	 */
	public function test_empty_and_missing_sessions(): void {
		$repository = new Journey_Report_Session_Repository();

		self::assertSame( array(), $repository->get_by_ids( session_ids: array() ) );
		self::assertSame( array(), $this->database->prepared_queries );
		self::assertSame( array(), $repository->get_by_ids( session_ids: array( 8 ) ) );
	}

	/**
	 * Invalid IDs, excessive batches, and unavailable schema never query data.
	 *
	 * @return void
	 */
	public function test_invalid_requests_fail_closed(): void {
		$repository = new Journey_Report_Session_Repository();

		self::assertNull( $repository->get_by_ids( session_ids: array( 0 ) ) );
		self::assertNull( $repository->get_by_ids( session_ids: array( -1 ) ) );
		self::assertNull( $repository->get_by_ids( session_ids: range( 1, 101 ) ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertNull( $repository->get_by_ids( session_ids: array( 8 ) ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Unexpected IDs, duplicate rows, and invalid stored values fail closed.
	 *
	 * @return void
	 */
	public function test_malformed_database_rows_fail_closed(): void {
		$repository = new Journey_Report_Session_Repository();

		$this->database->results = array( $this->session_row( id: '9', user_id_at_start: null ) );
		self::assertNull( $repository->get_by_ids( session_ids: array( 8 ) ) );

		$this->database->results = array(
			$this->session_row( id: '8', user_id_at_start: null ),
			$this->session_row( id: '8', user_id_at_start: null ),
		);
		self::assertNull( $repository->get_by_ids( session_ids: array( 8, 9 ) ) );

		$row                     = $this->session_row( id: '8', user_id_at_start: null );
		$row->last_activity_at   = '2026-09-16 11:59:59';
		$this->database->results = array( $row );
		self::assertNull( $repository->get_by_ids( session_ids: array( 8 ) ) );

		$row                      = $this->session_row( id: '8', user_id_at_start: null );
		$row->began_authenticated = '1';
		$this->database->results  = array( $row );
		self::assertNull( $repository->get_by_ids( session_ids: array( 8 ) ) );

		$row                     = $this->session_row( id: '8', user_id_at_start: null );
		$row->ended_at           = '2026-09-16 12:16:59';
		$this->database->results = array( $row );
		self::assertNull( $repository->get_by_ids( session_ids: array( 8 ) ) );
	}

	/**
	 * Build one representative session row from the v1 schema.
	 *
	 * @param string      $id               Session ID.
	 * @param string|null $user_id_at_start User at session start.
	 * @return stdClass Database row.
	 */
	private function session_row( string $id, ?string $user_id_at_start ): stdClass {
		return (object) array(
			'id'                  => $id,
			'visitor_id'          => '12',
			'identity_period_id'  => '4',
			'user_id_at_start'    => $user_id_at_start,
			'began_authenticated' => null === $user_id_at_start ? '0' : '1',
			'started_at'          => '2026-09-16 12:00:00',
			'last_activity_at'    => '2026-09-16 12:17:00',
			'ended_at'            => null,
			'landing_path'        => '/products',
			'referrer_host'       => 'example.org',
			'utm_source'          => 'newsletter',
			'utm_medium'          => 'email',
			'utm_campaign'        => 'autumn',
			'utm_term'            => null,
			'utm_content'         => null,
		);
	}
}
