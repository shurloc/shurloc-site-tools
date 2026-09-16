<?php
/**
 * Journey schema database test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

use RuntimeException;

/**
 * Small database double for dbDelta and schema verification.
 */
final class Shurloc_Journey_Schema_Test_WPDB {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * WordPress options table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * Installed table definitions keyed by full name.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public array $tables = array();

	/**
	 * Index name to omit from simulated SHOW INDEX results.
	 *
	 * @var string
	 */
	public string $missing_index = '';

	/**
	 * Column name to omit from simulated SHOW COLUMNS results.
	 *
	 * @var string
	 */
	public string $missing_column = '';

	/**
	 * Unique index to present as non-unique.
	 *
	 * @var string
	 */
	public string $non_unique_index = '';

	/**
	 * Change the lock value immediately before its conditional deletion.
	 *
	 * @var bool
	 */
	public bool $race_on_lock_delete = false;

	/**
	 * Arguments passed to the latest prepared query.
	 *
	 * @var array<int,mixed>
	 */
	private array $prepared_args = array();

	/**
	 * Return a deterministic collation for generated SQL.
	 *
	 * @return string Charset and collation SQL.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * Prepare a limited identifier query for the test double.
	 *
	 * @param string $query Query containing placeholders.
	 * @param mixed  ...$args Placeholder arguments.
	 * @return string Prepared query.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_args = array_values( $args );

		if ( str_starts_with( $query, 'SHOW ' ) ) {
			return str_replace( '%i', '`' . (string) $args[0] . '`', $query );
		}

		return $query;
	}

	/**
	 * Conditionally delete only the lock value passed to prepare().
	 *
	 * @param string $query Prepared SQL.
	 * @return int Number of deleted option rows.
	 */
	public function query( string $query ): int {
		if ( ! str_starts_with( $query, 'DELETE FROM %i' ) ) {
			return 0;
		}

		if ( $this->race_on_lock_delete ) {
			$GLOBALS['shurloc_test_options'][ Journey_Schema_Migrator::LOCK_OPTION ] =
				time() . ':replacement';
			$this->race_on_lock_delete = false;
		}

		$option_name = (string) $this->prepared_args[1];
		$value       = (string) $this->prepared_args[2];

		if (
			! isset( $GLOBALS['shurloc_test_options'][ $option_name ] ) ||
			$value !== $GLOBALS['shurloc_test_options'][ $option_name ]
		) {
			return 0;
		}

		unset( $GLOBALS['shurloc_test_options'][ $option_name ] );
		return 1;
	}

	/**
	 * Install the declaration named by a CREATE TABLE statement.
	 *
	 * @param string $sql dbDelta SQL.
	 * @return void
	 */
	public function install_table( string $sql ): void {
		if ( ! preg_match( '/^CREATE TABLE ([a-zA-Z0-9_]+) \(/', $sql, $matches ) ) {
			return;
		}

		$table_name   = $matches[1];
		$table_suffix = substr( $table_name, strlen( $this->prefix ) );
		$definitions  = Journey_Schema_V1::get_table_definitions();

		if ( isset( $definitions[ $table_suffix ] ) ) {
			$this->tables[ $table_name ] = $definitions[ $table_suffix ];
		}
	}

	/**
	 * Return simulated SHOW COLUMNS or SHOW INDEX rows.
	 *
	 * @param string $query Prepared schema query.
	 * @return array<int,object>|null Schema rows, or null for a missing table.
	 */
	public function get_results( string $query ): ?array {
		if ( ! preg_match( '/^SHOW (COLUMNS|INDEX) FROM `([a-zA-Z0-9_]+)`$/', $query, $matches ) ) {
			return null;
		}

		$table_name = $matches[2];

		if ( ! isset( $this->tables[ $table_name ] ) ) {
			return null;
		}

		$definition = $this->tables[ $table_name ];

		if ( 'COLUMNS' === $matches[1] ) {
			return $this->column_rows( columns: $definition['columns'] );
		}

		return $this->index_rows( indexes: $definition['indexes'] );
	}

	/**
	 * Build SHOW COLUMNS rows from the installed declaration.
	 *
	 * @param array<string,string> $columns Installed columns.
	 * @return array<int,object> Column rows.
	 * @throws RuntimeException When a test column declaration is invalid.
	 */
	private function column_rows( array $columns ): array {
		$rows = array();

		foreach ( $columns as $name => $sql ) {
			if ( $name === $this->missing_column ) {
				continue;
			}

			if ( ! preg_match( '/^[a-z]+(?:\([0-9,]+\))?(?: unsigned)?/', $sql, $matches ) ) {
				throw new RuntimeException( 'Invalid test column definition.' );
			}

			$rows[] = (object) array(
				'Field' => $name,
				'Type'  => $matches[0],
				'Null'  => str_contains( $sql, 'DEFAULT NULL' ) ? 'YES' : 'NO',
				'Extra' => str_contains( $sql, 'AUTO_INCREMENT' ) ? 'auto_increment' : '',
			);
		}

		return $rows;
	}

	/**
	 * Build SHOW INDEX rows from the installed declaration.
	 *
	 * @param array<string,array<string,mixed>> $indexes Installed indexes.
	 * @return array<int,object> Index rows.
	 */
	private function index_rows( array $indexes ): array {
		$rows = array();

		foreach ( $indexes as $name => $index ) {
			if ( $name === $this->missing_index ) {
				continue;
			}

			foreach ( $index['columns'] as $position => $column_name ) {
				$rows[] = (object) array(
					'Key_name'     => $name,
					'Column_name'  => $column_name,
					'Seq_in_index' => $position + 1,
					'Non_unique'   => $index['unique'] && $name !== $this->non_unique_index ? 0 : 1,
				);
			}
		}

		return $rows;
	}
}
