<?php
/**
 * Tests for the initial Customer Journey table definitions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use PHPUnit\Framework\TestCase;

/**
 * Tests the version-one Journey schema contract.
 */
final class JourneySchemaV1Test extends TestCase {

	/**
	 * Verify all four tables require InnoDB and use the supplied prefix and collation.
	 *
	 * @return void
	 */
	public function test_statements_use_supplied_prefix_and_collation(): void {
		$statements = Journey_Schema_V1::get_create_table_statements(
			table_prefix: 'store_42_',
			charset_collate: 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
		);

		self::assertSame(
			array(
				'store_42_shurloc_journey_visitors',
				'store_42_shurloc_journey_identity_periods',
				'store_42_shurloc_journey_sessions',
				'store_42_shurloc_journey_events',
			),
			array_keys( $statements )
		);

		foreach ( $statements as $table_name => $statement ) {
			self::assertStringStartsWith(
				'CREATE TABLE ' . $table_name . " (\n",
				$statement
			);
			self::assertStringEndsWith(
				') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
				$statement
			);
			self::assertStringNotContainsString( 'wp_shurloc_', $statement );
		}
	}

	/**
	 * Verify the table columns needed for identity, reporting, and future events.
	 *
	 * @return void
	 */
	public function test_columns_match_the_v1_contract(): void {
		$definitions = Journey_Schema_V1::get_table_definitions();

		self::assertSame(
			array(
				'id',
				'visitor_uuid',
				'created_at',
				'last_seen_at',
				'first_touch_at',
				'first_landing_path',
				'first_referrer_host',
				'first_utm_source',
				'first_utm_medium',
				'first_utm_campaign',
				'first_utm_term',
				'first_utm_content',
			),
			array_keys( $definitions['shurloc_journey_visitors']['columns'] )
		);

		self::assertSame(
			array( 'id', 'visitor_id', 'user_id', 'started_at', 'linked_at', 'ended_at' ),
			array_keys( $definitions['shurloc_journey_identity_periods']['columns'] )
		);

		self::assertSame(
			array(
				'id',
				'visitor_id',
				'identity_period_id',
				'user_id_at_start',
				'began_authenticated',
				'started_at',
				'last_activity_at',
				'ended_at',
				'landing_path',
				'referrer_host',
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'page_view_count',
				'product_view_count',
				'cart_add_count',
				'cart_remove_count',
				'added_quantity',
				'removed_quantity',
				'checkout_started_count',
				'order_created_count',
				'active_ms',
			),
			array_keys( $definitions['shurloc_journey_sessions']['columns'] )
		);

		self::assertSame(
			array(
				'id',
				'session_id',
				'visitor_id',
				'user_id_at_event',
				'event_type',
				'occurred_at',
				'page_path',
				'post_id',
				'product_id',
				'variation_id',
				'quantity',
				'order_id',
				'related_object_id',
				'active_ms',
				'source',
				'idempotency_key',
			),
			array_keys( $definitions['shurloc_journey_events']['columns'] )
		);

		self::assertSame(
			'char(64) DEFAULT NULL',
			$definitions['shurloc_journey_events']['columns']['idempotency_key']
		);
		self::assertSame(
			'decimal(16,4) DEFAULT NULL',
			$definitions['shurloc_journey_events']['columns']['quantity']
		);
		self::assertSame(
			'bigint(20) unsigned DEFAULT NULL',
			$definitions['shurloc_journey_events']['columns']['related_object_id']
		);
	}

	/**
	 * Verify chronological indexes and the revised idempotency constraint.
	 *
	 * @return void
	 */
	public function test_indexes_match_the_v1_contract(): void {
		$definitions = Journey_Schema_V1::get_table_definitions();

		self::assertSame(
			array( 'PRIMARY', 'visitor_uuid', 'last_seen' ),
			array_keys( $definitions['shurloc_journey_visitors']['indexes'] )
		);
		self::assertSame(
			array( 'PRIMARY', 'visitor_started', 'user_started', 'ended_at' ),
			array_keys( $definitions['shurloc_journey_identity_periods']['indexes'] )
		);
		self::assertSame(
			array( 'PRIMARY', 'visitor_started', 'period_started', 'last_activity' ),
			array_keys( $definitions['shurloc_journey_sessions']['indexes'] )
		);
		self::assertSame(
			array(
				'PRIMARY',
				'idempotency_key',
				'session_time',
				'visitor_time',
				'user_time',
				'event_time',
				'type_time',
				'product_type_time',
				'order_lookup',
			),
			array_keys( $definitions['shurloc_journey_events']['indexes'] )
		);

		$events_sql = Journey_Schema_V1::get_create_table_statements(
			table_prefix: 'wp_',
			charset_collate: 'DEFAULT CHARACTER SET utf8mb4',
		)['wp_shurloc_journey_events'];

		self::assertStringContainsString(
			'UNIQUE KEY idempotency_key (idempotency_key)',
			$events_sql
		);
		self::assertStringContainsString(
			'KEY order_lookup (order_id,occurred_at,id)',
			$events_sql
		);
		self::assertStringNotContainsString(
			'UNIQUE KEY order',
			$events_sql
		);
	}

	/**
	 * Verify dbDelta's primary-key spacing and key declaration format.
	 *
	 * @return void
	 */
	public function test_statements_use_dbdelta_key_format(): void {
		$statements = Journey_Schema_V1::get_create_table_statements(
			table_prefix: 'wp_',
			charset_collate: 'DEFAULT CHARACTER SET utf8mb4',
		);

		foreach ( $statements as $statement ) {
			self::assertStringContainsString(
				"\nPRIMARY KEY  (id)",
				$statement
			);
			self::assertStringNotContainsString( '`', $statement );
			self::assertMatchesRegularExpression(
				'/\n(?:UNIQUE KEY|KEY) [a-z_]+ \([a-z_,]+\)/',
				$statement
			);
		}
	}
}
