<?php
/**
 * Tests for the Customer Journey cart-correlation table definition.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the version-two Journey schema contract.
 */
final class JourneySchemaV2Test extends TestCase {

	/**
	 * Verify the cart-link columns and their SQL definitions.
	 *
	 * @return void
	 */
	public function test_columns_match_the_v2_contract(): void {
		$definitions = Journey_Schema_V2::get_table_definitions();

		self::assertSame(
			array( 'shurloc_journey_cart_links' ),
			array_keys( $definitions )
		);
		self::assertSame(
			array(
				'id'              => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
				'cart_token_hash' => 'char(64) NOT NULL',
				'visitor_id'      => 'bigint(20) unsigned NOT NULL',
				'session_id'      => 'bigint(20) unsigned NOT NULL',
				'linked_at'       => 'datetime NOT NULL',
				'last_seen_at'    => 'datetime NOT NULL',
			),
			$definitions['shurloc_journey_cart_links']['columns']
		);
	}

	/**
	 * Verify idempotency, report lookup, and cleanup indexes.
	 *
	 * @return void
	 */
	public function test_indexes_match_the_v2_contract(): void {
		$indexes = Journey_Schema_V2::get_table_definitions()['shurloc_journey_cart_links']['indexes'];

		self::assertSame(
			array(
				'PRIMARY',
				'cart_session',
				'cart_recent',
				'visitor_recent',
				'session_id',
			),
			array_keys( $indexes )
		);
		self::assertSame(
			array(
				'columns' => array( 'id' ),
				'unique'  => true,
			),
			$indexes['PRIMARY']
		);
		self::assertSame(
			array(
				'columns' => array( 'cart_token_hash', 'session_id' ),
				'unique'  => true,
			),
			$indexes['cart_session']
		);
		self::assertSame(
			array(
				'columns' => array( 'cart_token_hash', 'last_seen_at', 'id' ),
				'unique'  => false,
			),
			$indexes['cart_recent']
		);
		self::assertSame(
			array(
				'columns' => array( 'visitor_id', 'last_seen_at', 'id' ),
				'unique'  => false,
			),
			$indexes['visitor_recent']
		);
		self::assertSame(
			array(
				'columns' => array( 'session_id' ),
				'unique'  => false,
			),
			$indexes['session_id']
		);
	}

	/**
	 * Verify supplied prefix, collation, InnoDB, and dbDelta key formatting.
	 *
	 * @return void
	 */
	public function test_statement_uses_prefix_innodb_and_dbdelta_format(): void {
		$statements = Journey_Schema_V2::get_create_table_statements(
			table_prefix: 'store_42_',
			charset_collate: 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
		);
		$statement  = $statements['store_42_shurloc_journey_cart_links'];

		self::assertSame(
			array( 'store_42_shurloc_journey_cart_links' ),
			array_keys( $statements )
		);
		self::assertStringStartsWith(
			"CREATE TABLE store_42_shurloc_journey_cart_links (\n",
			$statement
		);
		self::assertStringContainsString(
			"\nPRIMARY KEY  (id)",
			$statement
		);
		self::assertStringContainsString(
			'UNIQUE KEY cart_session (cart_token_hash,session_id)',
			$statement
		);
		self::assertStringContainsString(
			'KEY cart_recent (cart_token_hash,last_seen_at,id)',
			$statement
		);
		self::assertStringContainsString(
			'KEY visitor_recent (visitor_id,last_seen_at,id)',
			$statement
		);
		self::assertStringContainsString(
			'KEY session_id (session_id)',
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
