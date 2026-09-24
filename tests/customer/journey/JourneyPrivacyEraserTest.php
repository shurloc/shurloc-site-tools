<?php
/**
 * Tests for the Customer Journey WordPress personal-data eraser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc_Test_WPDB;

/**
 * Verify eraser registration, account resolution, batching, and failures.
 */
final class JourneyPrivacyEraserTest extends TestCase {
	/**
	 * Shared database double.
	 *
	 * @var Shurloc_Test_WPDB
	 */
	private Shurloc_Test_WPDB $database;

	/**
	 * Eraser under test.
	 *
	 * @var Journey_Privacy_Eraser
	 */
	private Journey_Privacy_Eraser $eraser;

	/**
	 * Prepare a ready schema, one test account, and clean hooks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_options']         = array(
			Journey_Schema_Migrator::VERSION_OPTION => Journey_Schema_Migrator::CURRENT_VERSION,
		);
		$GLOBALS['shurloc_test_users']           = array( 7 => true );
		$GLOBALS['shurloc_test_user_data']       = array(
			7 => array( 'user_email' => 'private@example.com' ),
		);

		$this->database = new Shurloc_Test_WPDB();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only database replacement.
		$GLOBALS['wpdb'] = $this->database;

		$this->eraser = new Journey_Privacy_Eraser();
	}

	/**
	 * Restore shared test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_options']         = array();
		$GLOBALS['shurloc_test_users']           = array();
		$GLOBALS['shurloc_test_user_data']       = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore test-only database replacement.
		$GLOBALS['wpdb'] = new Shurloc_Test_WPDB();

		parent::tearDown();
	}

	/**
	 * Registration preserves existing erasers and adds a callable Journey entry.
	 *
	 * @return void
	 */
	public function test_registers_wordpress_personal_data_eraser(): void {
		$this->eraser->register();

		self::assertCount( 1, $GLOBALS['shurloc_test_filters']['wp_privacy_personal_data_erasers'] );
		$filter = $GLOBALS['shurloc_test_filters']['wp_privacy_personal_data_erasers'][0];
		self::assertIsCallable( $filter );

		$other_callback = static fn (): array => array();
		$erasers        = $filter(
			array(
				'other-plugin' => array(
					'eraser_friendly_name' => 'Other plugin',
					'callback'             => $other_callback,
				),
			)
		);

		self::assertArrayHasKey( 'other-plugin', $erasers );
		self::assertArrayHasKey( Journey_Privacy_Eraser::ERASER_KEY, $erasers );
		$journey = $erasers[ Journey_Privacy_Eraser::ERASER_KEY ];
		self::assertSame( 'Customer Journey data', $journey['eraser_friendly_name'] );
		self::assertIsArray( $journey['callback'] );
		self::assertSame( $this->eraser, $journey['callback'][0] );
		self::assertSame( 'erase', $journey['callback'][1] );
		self::assertSame( 10, $GLOBALS['shurloc_test_filter_metadata']['wp_privacy_personal_data_erasers'][0]['priority'] );
	}

	/**
	 * An unknown email has no resolvable Journey identity and is complete.
	 *
	 * @return void
	 */
	public function test_unknown_email_completes_without_database_work(): void {
		self::assertSame(
			$this->expected_result( done: true ),
			$this->eraser->erase( email_address: 'unknown@example.com' )
		);
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * A successful empty repository page completes the WordPress eraser.
	 *
	 * @return void
	 */
	public function test_empty_repository_page_completes_erasure(): void {
		$this->database->results = array();

		self::assertSame(
			$this->expected_result( done: true ),
			$this->eraser->erase( email_address: 'PRIVATE@example.com', page: 1 )
		);
		self::assertSame( 7, $this->database->prepared_queries[0]['args'][1] );
	}

	/**
	 * Removed records request another WordPress page until the final empty check.
	 *
	 * @return void
	 */
	public function test_removed_batch_reports_progress_and_uses_filtered_size(): void {
		add_filter(
			Journey_Privacy_Eraser::BATCH_SIZE_FILTER,
			static fn ( int $batch_size ): int => min( $batch_size, 1 )
		);
		$this->database->result_queue       = array(
			array( $this->period_row() ),
			array( (object) array( 'id' => '3' ) ),
			array( $this->period_row() ),
			array( (object) array( 'id' => '21' ) ),
		);
		$this->database->query_result_queue = array( 1 );

		self::assertSame(
			$this->expected_result( items_removed: true, done: false ),
			$this->eraser->erase( email_address: 'private@example.com', page: 2 )
		);
		self::assertSame( 1, $this->database->prepared_queries[3]['args'][3] );
	}

	/**
	 * Invalid filtered sizes fall back to the central default.
	 *
	 * @return void
	 */
	public function test_invalid_filtered_batch_size_uses_default(): void {
		add_filter(
			Journey_Privacy_Eraser::BATCH_SIZE_FILTER,
			static fn (): string => 'invalid'
		);
		$this->database->result_queue       = array(
			array( $this->period_row() ),
			array( (object) array( 'id' => '3' ) ),
			array( $this->period_row() ),
			array( (object) array( 'id' => '21' ) ),
		);
		$this->database->query_result_queue = array( 1 );

		$this->eraser->erase( email_address: 'private@example.com' );

		self::assertSame(
			Journey_Privacy_Eraser::DEFAULT_BATCH_SIZE,
			$this->database->prepared_queries[3]['args'][3]
		);
	}

	/**
	 * Invalid pages and repository failures report retained data and stop safely.
	 *
	 * @return void
	 */
	public function test_invalid_page_and_repository_failure_report_retained_data(): void {
		$failure = $this->expected_result(
			items_retained: true,
			messages: array(
				'Customer Journey data could not be erased. The remaining data was retained.',
			),
			done: true,
		);

		self::assertSame( $failure, $this->eraser->erase( email_address: 'private@example.com', page: 0 ) );
		self::assertSame( array(), $this->database->prepared_queries );

		$GLOBALS['shurloc_test_options'] = array();
		self::assertSame( $failure, $this->eraser->erase( email_address: 'private@example.com' ) );
		self::assertSame( array(), $this->database->prepared_queries );
	}

	/**
	 * Return a valid identity-period database row.
	 *
	 * @return object Database row.
	 */
	private function period_row(): object {
		return (object) array(
			'id'         => '4',
			'visitor_id' => '3',
		);
	}

	/**
	 * Build an expected WordPress eraser result.
	 *
	 * @param bool  $items_removed  Whether records were removed.
	 * @param bool  $items_retained Whether records were retained.
	 * @param array $messages       Administrator messages.
	 * @param bool  $done           Whether erasure is complete.
	 * @phpstan-param list<string> $messages
	 * @return array<string,bool|list<string>> Result.
	 */
	private function expected_result(
		bool $items_removed = false,
		bool $items_retained = false,
		array $messages = array(),
		bool $done = false
	): array {
		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}
}
