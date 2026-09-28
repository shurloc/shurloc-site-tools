<?php
/**
 * Tests for the Customer Journey client-inspection schema definition.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the version-three Journey schema contract.
 */
final class JourneySchemaV3Test extends TestCase {

	/**
	 * Verify v3 extends the complete session definition with inspection fields.
	 *
	 * @return void
	 */
	public function test_columns_match_the_v3_contract(): void {
		$definitions = Journey_Schema_V3::get_table_definitions();
		$columns     = $definitions['shurloc_journey_sessions']['columns'];

		self::assertSame(
			array( 'shurloc_journey_sessions' ),
			array_keys( $definitions )
		);
		self::assertSame(
			Journey_Schema_V1::get_table_definitions()['shurloc_journey_sessions']['columns'],
			array_intersect_key(
				$columns,
				Journey_Schema_V1::get_table_definitions()['shurloc_journey_sessions']['columns']
			)
		);
		self::assertSame(
			array(
				'user_agent'             => 'varchar(1024) DEFAULT NULL',
				'client_type'            => "varchar(32) NOT NULL DEFAULT 'unknown'",
				'client_name'            => 'varchar(64) DEFAULT NULL',
				'device_type'            => "varchar(32) NOT NULL DEFAULT 'unknown'",
				'classification_version' => 'smallint(5) unsigned NOT NULL DEFAULT 0',
				'event_count'            => 'int(10) unsigned NOT NULL DEFAULT 0',
			),
			array_slice( $columns, -6, null, true )
		);
	}

	/**
	 * Verify v3 preserves every established session index without adding one.
	 *
	 * @return void
	 */
	public function test_indexes_preserve_the_v1_session_contract(): void {
		self::assertSame(
			Journey_Schema_V1::get_table_definitions()['shurloc_journey_sessions']['indexes'],
			Journey_Schema_V3::get_table_definitions()['shurloc_journey_sessions']['indexes']
		);
	}

	/**
	 * Verify supplied prefix, collation, InnoDB, and dbDelta formatting.
	 *
	 * @return void
	 */
	public function test_statement_uses_prefix_innodb_and_dbdelta_format(): void {
		$statements = Journey_Schema_V3::get_create_table_statements(
			table_prefix: 'store_42_',
			charset_collate: 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
		);
		$statement  = $statements['store_42_shurloc_journey_sessions'];

		self::assertSame(
			array( 'store_42_shurloc_journey_sessions' ),
			array_keys( $statements )
		);
		self::assertStringStartsWith(
			"CREATE TABLE store_42_shurloc_journey_sessions (\n",
			$statement
		);
		self::assertStringContainsString(
			"\nPRIMARY KEY  (id)",
			$statement
		);
		self::assertStringContainsString(
			'user_agent varchar(1024) DEFAULT NULL',
			$statement
		);
		self::assertStringContainsString(
			"client_type varchar(32) NOT NULL DEFAULT 'unknown'",
			$statement
		);
		self::assertStringContainsString(
			'event_count int(10) unsigned NOT NULL DEFAULT 0',
			$statement
		);
		self::assertStringContainsString(
			'KEY visitor_started (visitor_id,started_at,id)',
			$statement
		);
		self::assertStringEndsWith(
			') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
			$statement
		);
		self::assertStringNotContainsString( '`', $statement );
		self::assertStringNotContainsString( 'wp_shurloc_', $statement );
	}
}
