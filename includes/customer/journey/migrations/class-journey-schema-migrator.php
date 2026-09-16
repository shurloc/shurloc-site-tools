<?php
/**
 * Customer Journey database schema migrator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Applies sequential Journey schema versions without running on page views.
 */
final class Journey_Schema_Migrator {

	/**
	 * Current Journey database schema version, independent of plugin version.
	 */
	public const CURRENT_VERSION = 1;

	/**
	 * Installed Journey schema version option.
	 */
	public const VERSION_OPTION = 'shurloc_customer_journey_db_version';

	/**
	 * Migration lock option.
	 */
	public const LOCK_OPTION = 'shurloc_customer_journey_schema_lock';

	/**
	 * Last migration failure code for a later admin notice and retry controller.
	 */
	public const FAILURE_OPTION = 'shurloc_customer_journey_schema_failure';

	/**
	 * Time after which an abandoned migration lock may be reclaimed.
	 */
	private const LOCK_TIMEOUT = 900;

	/**
	 * The lock value owned by this migrator instance.
	 *
	 * @var string|null
	 */
	private ?string $lock_value = null;

	/**
	 * Runs WordPress dbDelta for one statement.
	 *
	 * @var Closure(string):void
	 */
	private Closure $schema_updater;

	/**
	 * Constructor.
	 *
	 * @param Closure(string):void|null $schema_updater Schema update callback.
	 */
	public function __construct( ?Closure $schema_updater = null ) {
		$this->schema_updater = $schema_updater ?? static function ( string $statement ): void {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			dbDelta( $statement );
		};
	}

	/**
	 * Get the installed schema version.
	 *
	 * @return int Installed version, or zero before installation.
	 */
	public function get_installed_version(): int {
		return max( 0, (int) get_option( self::VERSION_OPTION, 0 ) );
	}

	/**
	 * Determine whether all Journey schema-dependent code may run.
	 *
	 * @return bool True only when this code's schema version is installed.
	 */
	public function is_ready(): bool {
		return self::CURRENT_VERSION === $this->get_installed_version();
	}

	/**
	 * Get a generic, non-sensitive failure code for administration notices.
	 *
	 * @return string Empty when no failure has been recorded.
	 */
	public function get_failure_code(): string {
		$code = get_option( self::FAILURE_OPTION, '' );

		return is_string( $code ) ? $code : '';
	}

	/**
	 * Calculate the ascending versions that an installation must apply.
	 *
	 * @param int $installed_version Installed schema version.
	 * @param int $target_version    Version supplied by the running code.
	 * @return array<int,int> Pending versions in order.
	 */
	public static function get_pending_versions(
		int $installed_version,
		int $target_version
	): array {
		if ( $installed_version >= $target_version ) {
			return array();
		}

		return range( max( 1, $installed_version + 1 ), $target_version );
	}

	/**
	 * Upgrade the schema when needed, leaving the version unchanged on failure.
	 *
	 * @return bool True when the current schema is ready.
	 * @throws RuntimeException If lock release fails outside migration handling.
	 */
	public function migrate(): bool {
		$installed_version = $this->get_installed_version();

		if ( self::CURRENT_VERSION === $installed_version ) {
			return true;
		}

		if ( self::CURRENT_VERSION < $installed_version ) {
			update_option( self::FAILURE_OPTION, 'newer_schema' );
			return false;
		}

		try {
			if ( ! $this->acquire_lock() ) {
				return false;
			}

			// Recheck after acquiring the lock in case another request upgraded first.
			$installed_version = $this->get_installed_version();

			foreach (
				self::get_pending_versions(
					installed_version: $installed_version,
					target_version: self::CURRENT_VERSION,
				) as $version
			) {
				$this->apply_version( version: $version );

				if ( ! update_option( self::VERSION_OPTION, $version ) &&
					$version !== $this->get_installed_version() ) {
					throw new RuntimeException( 'Journey schema version could not be stored.' );
				}
			}

			delete_option( self::FAILURE_OPTION );

			return $this->is_ready();
		} catch ( Throwable $error ) {
			unset( $error );
			update_option( self::FAILURE_OPTION, 'migration_failed' );
			return false;
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Acquire an atomic option lock, reclaiming only an unchanged stale value.
	 *
	 * @return bool True when this instance owns the lock.
	 */
	private function acquire_lock(): bool {
		$this->lock_value = time() . ':' . bin2hex( random_bytes( 16 ) );

		if ( add_option( self::LOCK_OPTION, $this->lock_value, '', false ) ) {
			return true;
		}

		$existing_value = get_option( self::LOCK_OPTION, '' );

		if ( ! is_string( $existing_value ) ) {
			$this->lock_value = null;
			return false;
		}

		$locked_at = (int) strtok( $existing_value, ':' );

		if ( 0 >= $locked_at || time() - $locked_at <= self::LOCK_TIMEOUT ) {
			$this->lock_value = null;
			return false;
		}

		if ( ! $this->delete_lock_value( value: $existing_value ) ) {
			$this->lock_value = null;
			return false;
		}

		if ( ! add_option( self::LOCK_OPTION, $this->lock_value, '', false ) ) {
			$this->lock_value = null;
			return false;
		}

		return true;
	}

	/**
	 * Release the lock only when its stored value still belongs to this instance.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		if ( null === $this->lock_value ) {
			return;
		}

		$this->delete_lock_value( value: $this->lock_value );
		$this->lock_value = null;
	}

	/**
	 * Delete a matching option value atomically and clear its option cache entry.
	 *
	 * @param string $value Value that must still own the lock.
	 * @return bool True when that exact value was deleted.
	 * @phpstan-impure
	 */
	private function delete_lock_value( string $value ): bool {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				self::LOCK_OPTION,
				$value
			)
		);

		if ( 1 !== $deleted ) {
			return false;
		}

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::LOCK_OPTION, 'options' );
		}

		return true;
	}

	/**
	 * Apply one version and verify it before the caller stores that version.
	 *
	 * @param int $version Schema version to apply.
	 * @return void
	 * @throws RuntimeException When the version is unknown or verification fails.
	 */
	private function apply_version( int $version ): void {
		if ( 1 !== $version ) {
			throw new RuntimeException( 'Unknown Journey schema version.' );
		}

		global $wpdb;

		$statements = Journey_Schema_V1::get_create_table_statements(
			table_prefix: $wpdb->prefix,
			charset_collate: $wpdb->get_charset_collate(),
		);

		foreach ( $statements as $statement ) {
			( $this->schema_updater )( $statement );
		}

		$this->verify_schema(
			table_prefix: $wpdb->prefix,
			definitions: Journey_Schema_V1::get_table_definitions(),
		);
	}

	/**
	 * Verify every required column, type, index order, and uniqueness property.
	 *
	 * @param string              $table_prefix WordPress database prefix.
	 * @param array<string,mixed> $definitions  Schema definitions.
	 * @return void
	 * @throws RuntimeException When a required schema element is unavailable.
	 */
	private function verify_schema(
		string $table_prefix,
		array $definitions
	): void {
		global $wpdb;

		foreach ( $definitions as $suffix => $definition ) {
			if ( ! is_array( $definition ) ) {
				throw new RuntimeException( 'Invalid Journey schema definition.' );
			}

			$table_name = $table_prefix . $suffix;
			$columns    = $wpdb->get_results(
				$wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name )
			);
			$indexes    = $wpdb->get_results(
				$wpdb->prepare( 'SHOW INDEX FROM %i', $table_name )
			);

			if ( ! is_array( $columns ) || ! is_array( $indexes ) ) {
				throw new RuntimeException( 'Journey schema table is unavailable.' );
			}

			$this->verify_columns(
				actual_rows: $columns,
				expected_columns: $definition['columns'],
			);
			$this->verify_indexes(
				actual_rows: $indexes,
				expected_indexes: $definition['indexes'],
			);
		}
	}

	/**
	 * Verify the table's required columns and SQL types.
	 *
	 * @param array<int,object>    $actual_rows      SHOW COLUMNS rows.
	 * @param array<string,string> $expected_columns Expected column definitions.
	 * @return void
	 * @throws RuntimeException When a column is missing or incompatible.
	 */
	private function verify_columns(
		array $actual_rows,
		array $expected_columns
	): void {
		$actual = array();

		foreach ( $actual_rows as $row ) {
			$column = (array) $row;

			if ( isset( $column['Field'] ) && is_string( $column['Field'] ) ) {
				$actual[ $column['Field'] ] = $column;
			}
		}

		foreach ( $expected_columns as $name => $definition ) {
			$column = $actual[ $name ] ?? null;

			if ( null === $column || ! isset( $column['Type'], $column['Null'] ) ) {
				throw new RuntimeException( 'Journey schema column is unavailable.' );
			}

			$expected_type = $this->get_expected_type( definition: $definition );

			if (
				! is_string( $column['Type'] ) ||
				$this->normalize_type( type: $column['Type'] ) !==
					$this->normalize_type( type: $expected_type ) ||
				( str_contains( $definition, 'DEFAULT NULL' ) ? 'YES' : 'NO' ) !== $column['Null'] ||
				( str_contains( $definition, 'AUTO_INCREMENT' ) &&
					! str_contains( (string) ( $column['Extra'] ?? '' ), 'auto_increment' ) )
			) {
				throw new RuntimeException( 'Journey schema column is incompatible.' );
			}
		}
	}

	/**
	 * Verify index column order and uniqueness.
	 *
	 * @param array<int,object>   $actual_rows      SHOW INDEX rows.
	 * @param array<string,mixed> $expected_indexes Expected index definitions.
	 * @return void
	 * @throws RuntimeException When an index is missing or incompatible.
	 */
	private function verify_indexes(
		array $actual_rows,
		array $expected_indexes
	): void {
		$actual = array();

		foreach ( $actual_rows as $row ) {
			$index = (array) $row;

			if (
				! isset( $index['Key_name'], $index['Column_name'], $index['Seq_in_index'], $index['Non_unique'] ) ||
				! is_string( $index['Key_name'] ) ||
				! is_string( $index['Column_name'] )
			) {
				continue;
			}

			$actual[ $index['Key_name'] ]['columns'][ (int) $index['Seq_in_index'] ] = $index['Column_name'];
			$actual[ $index['Key_name'] ]['unique']                                  = 0 === (int) $index['Non_unique'];
		}

		foreach ( $expected_indexes as $name => $definition ) {
			if ( ! is_array( $definition ) || ! isset( $actual[ $name ] ) ) {
				throw new RuntimeException( 'Journey schema index is unavailable.' );
			}

			$columns = $actual[ $name ]['columns'];
			ksort( $columns );

			if (
				array_values( $columns ) !== $definition['columns'] ||
				$actual[ $name ]['unique'] !== $definition['unique']
			) {
				throw new RuntimeException( 'Journey schema index is incompatible.' );
			}
		}
	}

	/**
	 * Extract the SQL type from a column definition.
	 *
	 * @param string $definition Column definition.
	 * @return string Column SQL type.
	 * @throws RuntimeException When the declaration has no SQL type.
	 */
	private function get_expected_type( string $definition ): string {
		if ( ! preg_match( '/^[a-z]+(?:\([0-9,]+\))?(?: unsigned)?/', $definition, $matches ) ) {
			throw new RuntimeException( 'Invalid Journey column definition.' );
		}

		return $matches[0];
	}

	/**
	 * Ignore display widths that MySQL may omit for integer columns.
	 *
	 * @param string $type SQL type.
	 * @return string Normalized SQL type.
	 */
	private function normalize_type( string $type ): string {
		$normalized = preg_replace(
			'/\b(bigint|int|tinyint)\([0-9]+\)/',
			'$1',
			strtolower( trim( $type ) )
		);

		return is_string( $normalized ) ? $normalized : '';
	}
}
