<?php
/**
 * Customer Journey data truncation migration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Truncates every Customer Journey data table.
 */
final class Journey_Data_Truncation_Migration {

	/** Current migration version. */
	public const VERSION = 1;

	/** Option storing the timestamp of the most recent successful run. */
	public const LAST_RUN_OPTION =
		'shurloc_journey_data_truncation_last_run';

	/** Option storing the version used for the most recent successful run. */
	public const LAST_RUN_VERSION_OPTION =
		'shurloc_journey_data_truncation_last_run_version';

	/** Shared lock for destructive Journey maintenance migrations. */
	public const LOCK_OPTION =
		'shurloc_journey_maintenance_migration_lock';

	/** Maximum age of a migration lock in seconds. */
	private const LOCK_TIMEOUT = 900;

	/**
	 * Journey table suffixes in child-to-parent truncation order.
	 *
	 * @var list<string>
	 */
	private const TABLE_SUFFIXES = array(
		'shurloc_journey_cart_links',
		'shurloc_journey_events',
		'shurloc_journey_sessions',
		'shurloc_journey_identity_periods',
		'shurloc_journey_visitors',
	);

	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null $schema_migrator Journey schema check.
	 */
	public function __construct(
		?Journey_Schema_Migrator $schema_migrator = null
	) {
		$this->schema_migrator =
			$schema_migrator ?? new Journey_Schema_Migrator();
	}

	/**
	 * Truncate all Journey data tables.
	 *
	 * Truncation cannot be rolled back. Stopping on the first failure avoids
	 * removing parent rows while a dependent table could not be emptied.
	 *
	 * @return array{truncated:int,errors:int} Migration result.
	 */
	public function run(): array {
		$result = array(
			'truncated' => 0,
			'errors'    => 0,
		);

		if ( ! $this->schema_migrator->is_ready() ) {
			$result['errors'] = 1;
			return $result;
		}

		global $wpdb;

		foreach ( self::TABLE_SUFFIXES as $table_suffix ) {
			$truncated = $wpdb->query(
				$wpdb->prepare(
					'TRUNCATE TABLE %i',
					$wpdb->prefix . $table_suffix
				)
			);

			if ( false === $truncated ) {
				$result['errors'] = 1;
				return $result;
			}

			++$result['truncated'];
		}

		update_option( self::LAST_RUN_OPTION, time() );
		update_option( self::LAST_RUN_VERSION_OPTION, self::VERSION );

		return $result;
	}

	/**
	 * Determine whether a Journey maintenance migration is locked.
	 *
	 * @return bool True when an active lock exists.
	 */
	public function is_locked(): bool {
		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );

		if ( 0 === $locked_at ) {
			return false;
		}

		if ( time() - $locked_at > self::LOCK_TIMEOUT ) {
			delete_option( self::LOCK_OPTION );
			return false;
		}

		return true;
	}

	/**
	 * Attempt to acquire the Journey maintenance migration lock.
	 *
	 * @return bool True when the lock was acquired.
	 */
	public function acquire_lock(): bool {
		if ( $this->is_locked() ) {
			return false;
		}

		return add_option(
			self::LOCK_OPTION,
			time(),
			'',
			false
		);
	}

	/**
	 * Release the Journey maintenance migration lock.
	 *
	 * @return void
	 */
	public function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Get the timestamp of the most recent successful run.
	 *
	 * @return int Last-run timestamp, or zero when never run.
	 */
	public function get_last_run(): int {
		return (int) get_option( self::LAST_RUN_OPTION, 0 );
	}

	/**
	 * Get the version used for the most recent successful run.
	 *
	 * @return int Last-run version, or zero when never run.
	 */
	public function get_last_run_version(): int {
		return (int) get_option( self::LAST_RUN_VERSION_OPTION, 0 );
	}
}
