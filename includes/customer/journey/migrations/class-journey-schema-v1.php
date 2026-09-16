<?php
/**
 * Initial Customer Journey table definitions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the first Journey schema from a single set of columns and indexes.
 *
 * @phpstan-type IndexDefinition array{columns:non-empty-list<string>, unique:bool}
 * @phpstan-type TableDefinition array{columns:array<string,string>, indexes:array<string,IndexDefinition>}
 */
final class Journey_Schema_V1 {

	/**
	 * Get table definitions for migration and post-migration verification.
	 *
	 * @return array<string,TableDefinition> Definitions keyed by table suffix.
	 */
	public static function get_table_definitions(): array {
		return array(
			'shurloc_journey_visitors'         => array(
				'columns' => array(
					'id'                  => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'visitor_uuid'        => 'char(36) NOT NULL',
					'created_at'          => 'datetime NOT NULL',
					'last_seen_at'        => 'datetime NOT NULL',
					'first_touch_at'      => 'datetime DEFAULT NULL',
					'first_landing_path'  => 'varchar(1024) DEFAULT NULL',
					'first_referrer_host' => 'varchar(255) DEFAULT NULL',
					'first_utm_source'    => 'varchar(191) DEFAULT NULL',
					'first_utm_medium'    => 'varchar(191) DEFAULT NULL',
					'first_utm_campaign'  => 'varchar(191) DEFAULT NULL',
					'first_utm_term'      => 'varchar(191) DEFAULT NULL',
					'first_utm_content'   => 'varchar(191) DEFAULT NULL',
				),
				'indexes' => array(
					'PRIMARY'      => array(
						'columns' => array( 'id' ),
						'unique'  => true,
					),
					'visitor_uuid' => array(
						'columns' => array( 'visitor_uuid' ),
						'unique'  => true,
					),
					'last_seen'    => array(
						'columns' => array( 'last_seen_at', 'id' ),
						'unique'  => false,
					),
				),
			),
			'shurloc_journey_identity_periods' => array(
				'columns' => array(
					'id'         => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'visitor_id' => 'bigint(20) unsigned NOT NULL',
					'user_id'    => 'bigint(20) unsigned DEFAULT NULL',
					'started_at' => 'datetime NOT NULL',
					'linked_at'  => 'datetime DEFAULT NULL',
					'ended_at'   => 'datetime DEFAULT NULL',
				),
				'indexes' => array(
					'PRIMARY'         => array(
						'columns' => array( 'id' ),
						'unique'  => true,
					),
					'visitor_started' => array(
						'columns' => array( 'visitor_id', 'started_at', 'id' ),
						'unique'  => false,
					),
					'user_started'    => array(
						'columns' => array( 'user_id', 'started_at', 'id' ),
						'unique'  => false,
					),
					'ended_at'        => array(
						'columns' => array( 'ended_at', 'id' ),
						'unique'  => false,
					),
				),
			),
			'shurloc_journey_sessions'         => array(
				'columns' => array(
					'id'                     => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'visitor_id'             => 'bigint(20) unsigned NOT NULL',
					'identity_period_id'     => 'bigint(20) unsigned NOT NULL',
					'user_id_at_start'       => 'bigint(20) unsigned DEFAULT NULL',
					'began_authenticated'    => 'tinyint(1) NOT NULL DEFAULT 0',
					'started_at'             => 'datetime NOT NULL',
					'last_activity_at'       => 'datetime NOT NULL',
					'ended_at'               => 'datetime DEFAULT NULL',
					'landing_path'           => 'varchar(1024) DEFAULT NULL',
					'referrer_host'          => 'varchar(255) DEFAULT NULL',
					'utm_source'             => 'varchar(191) DEFAULT NULL',
					'utm_medium'             => 'varchar(191) DEFAULT NULL',
					'utm_campaign'           => 'varchar(191) DEFAULT NULL',
					'utm_term'               => 'varchar(191) DEFAULT NULL',
					'utm_content'            => 'varchar(191) DEFAULT NULL',
					'page_view_count'        => 'int(10) unsigned NOT NULL DEFAULT 0',
					'product_view_count'     => 'int(10) unsigned NOT NULL DEFAULT 0',
					'cart_add_count'         => 'int(10) unsigned NOT NULL DEFAULT 0',
					'cart_remove_count'      => 'int(10) unsigned NOT NULL DEFAULT 0',
					'added_quantity'         => 'decimal(16,4) NOT NULL DEFAULT 0.0000',
					'removed_quantity'       => 'decimal(16,4) NOT NULL DEFAULT 0.0000',
					'checkout_started_count' => 'int(10) unsigned NOT NULL DEFAULT 0',
					'order_created_count'    => 'int(10) unsigned NOT NULL DEFAULT 0',
					'active_ms'              => 'bigint(20) unsigned NOT NULL DEFAULT 0',
				),
				'indexes' => array(
					'PRIMARY'         => array(
						'columns' => array( 'id' ),
						'unique'  => true,
					),
					'visitor_started' => array(
						'columns' => array( 'visitor_id', 'started_at', 'id' ),
						'unique'  => false,
					),
					'period_started'  => array(
						'columns' => array( 'identity_period_id', 'started_at', 'id' ),
						'unique'  => false,
					),
					'last_activity'   => array(
						'columns' => array( 'last_activity_at', 'id' ),
						'unique'  => false,
					),
				),
			),
			'shurloc_journey_events'           => array(
				'columns' => array(
					'id'                => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'session_id'        => 'bigint(20) unsigned NOT NULL',
					'visitor_id'        => 'bigint(20) unsigned NOT NULL',
					'user_id_at_event'  => 'bigint(20) unsigned DEFAULT NULL',
					'event_type'        => 'varchar(32) NOT NULL',
					'occurred_at'       => 'datetime NOT NULL',
					'page_path'         => 'varchar(1024) DEFAULT NULL',
					'post_id'           => 'bigint(20) unsigned DEFAULT NULL',
					'product_id'        => 'bigint(20) unsigned DEFAULT NULL',
					'variation_id'      => 'bigint(20) unsigned DEFAULT NULL',
					'quantity'          => 'decimal(16,4) DEFAULT NULL',
					'order_id'          => 'bigint(20) unsigned DEFAULT NULL',
					'related_object_id' => 'bigint(20) unsigned DEFAULT NULL',
					'active_ms'         => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'source'            => 'varchar(32) DEFAULT NULL',
					'idempotency_key'   => 'char(64) DEFAULT NULL',
				),
				'indexes' => array(
					'PRIMARY'           => array(
						'columns' => array( 'id' ),
						'unique'  => true,
					),
					'idempotency_key'   => array(
						'columns' => array( 'idempotency_key' ),
						'unique'  => true,
					),
					'session_time'      => array(
						'columns' => array( 'session_id', 'occurred_at', 'id' ),
						'unique'  => false,
					),
					'visitor_time'      => array(
						'columns' => array( 'visitor_id', 'occurred_at', 'id' ),
						'unique'  => false,
					),
					'user_time'         => array(
						'columns' => array( 'user_id_at_event', 'occurred_at', 'id' ),
						'unique'  => false,
					),
					'event_time'        => array(
						'columns' => array( 'occurred_at', 'id' ),
						'unique'  => false,
					),
					'type_time'         => array(
						'columns' => array( 'event_type', 'occurred_at' ),
						'unique'  => false,
					),
					'product_type_time' => array(
						'columns' => array( 'product_id', 'event_type', 'occurred_at' ),
						'unique'  => false,
					),
					'order_lookup'      => array(
						'columns' => array( 'order_id', 'occurred_at', 'id' ),
						'unique'  => false,
					),
				),
			),
		);
	}

	/**
	 * Build dbDelta-compatible CREATE TABLE statements.
	 *
	 * @param string $table_prefix    Current WordPress database table prefix.
	 * @param string $charset_collate WordPress charset and collation SQL.
	 * @return array<string,string> Statements keyed by full table name.
	 */
	public static function get_create_table_statements(
		string $table_prefix,
		string $charset_collate
	): array {
		$statements = array();

		foreach ( self::get_table_definitions() as $suffix => $definition ) {
			$lines = array();

			foreach ( $definition['columns'] as $name => $column_sql ) {
				$lines[] = $name . ' ' . $column_sql;
			}

			foreach ( $definition['indexes'] as $name => $index ) {
				$columns = implode( ',', $index['columns'] );

				if ( 'PRIMARY' === $name ) {
					$lines[] = 'PRIMARY KEY  (' . $columns . ')';
				} elseif ( $index['unique'] ) {
					$lines[] = 'UNIQUE KEY ' . $name . ' (' . $columns . ')';
				} else {
					$lines[] = 'KEY ' . $name . ' (' . $columns . ')';
				}
			}

			$table_name                = $table_prefix . $suffix;
			$statements[ $table_name ] = 'CREATE TABLE ' . $table_name . " (\n"
				. implode( ",\n", $lines ) . "\n) " . $charset_collate . ';';
		}

		return $statements;
	}
}
