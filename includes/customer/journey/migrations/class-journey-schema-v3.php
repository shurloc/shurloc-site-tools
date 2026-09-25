<?php
/**
 * Customer Journey client-inspection and event-total schema definition.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the third Journey schema migration.
 *
 * @phpstan-type IndexDefinition array{columns:non-empty-list<string>, unique:bool}
 * @phpstan-type TableDefinition array{columns:array<string,string>, indexes:array<string,IndexDefinition>}
 */
final class Journey_Schema_V3 {

	/**
	 * Get the evolved session definition for migration and verification.
	 *
	 * The complete table definition lets dbDelta add the new columns while the
	 * migrator verifies that every earlier session column and index remains.
	 *
	 * @return array<string,TableDefinition> Definitions keyed by table suffix.
	 */
	public static function get_table_definitions(): array {
		$session = Journey_Schema_V1::get_table_definitions()['shurloc_journey_sessions'];

		$session['columns']['user_agent']             = 'varchar(1024) DEFAULT NULL';
		$session['columns']['client_type']            = "varchar(32) NOT NULL DEFAULT 'unknown'";
		$session['columns']['client_name']            = 'varchar(64) DEFAULT NULL';
		$session['columns']['device_type']            = "varchar(32) NOT NULL DEFAULT 'unknown'";
		$session['columns']['classification_version'] = 'smallint(5) unsigned NOT NULL DEFAULT 0';
		$session['columns']['event_count']            = 'int(10) unsigned NOT NULL DEFAULT 0';

		return array(
			'shurloc_journey_sessions' => $session,
		);
	}

	/**
	 * Build the dbDelta-compatible session CREATE TABLE statement.
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
