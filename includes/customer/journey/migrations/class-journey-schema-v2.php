<?php
/**
 * Customer Journey cart-correlation table definition.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the second Journey schema migration.
 *
 * @phpstan-type IndexDefinition array{columns:non-empty-list<string>, unique:bool}
 * @phpstan-type TableDefinition array{columns:array<string,string>, indexes:array<string,IndexDefinition>}
 */
final class Journey_Schema_V2 {

	/**
	 * Get table definitions for migration and post-migration verification.
	 *
	 * @return array<string,TableDefinition> Definitions keyed by table suffix.
	 */
	public static function get_table_definitions(): array {
		return array(
			'shurloc_journey_cart_links' => array(
				'columns' => array(
					'id'              => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'cart_token_hash' => 'char(64) NOT NULL',
					'visitor_id'      => 'bigint(20) unsigned NOT NULL',
					'session_id'      => 'bigint(20) unsigned NOT NULL',
					'linked_at'       => 'datetime NOT NULL',
					'last_seen_at'    => 'datetime NOT NULL',
				),
				'indexes' => array(
					'PRIMARY'        => array(
						'columns' => array( 'id' ),
						'unique'  => true,
					),
					'cart_session'   => array(
						'columns' => array( 'cart_token_hash', 'session_id' ),
						'unique'  => true,
					),
					'cart_recent'    => array(
						'columns' => array( 'cart_token_hash', 'last_seen_at', 'id' ),
						'unique'  => false,
					),
					'visitor_recent' => array(
						'columns' => array( 'visitor_id', 'last_seen_at', 'id' ),
						'unique'  => false,
					),
					'session_id'     => array(
						'columns' => array( 'session_id' ),
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
				. implode( ",\n", $lines ) . "\n) ENGINE=InnoDB " . $charset_collate . ';';
		}

		return $statements;
	}
}
