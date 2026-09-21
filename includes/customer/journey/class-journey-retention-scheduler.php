<?php
/**
 * Customer Journey retention scheduling.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Services\Journey_Retention_Service;
use Throwable;

/**
 * Schedules and serializes bounded Journey retention cleanup runs.
 */
final class Journey_Retention_Scheduler {
	/** Daily retention cleanup hook. */
	public const CRON_HOOK = 'shurloc_site_tools_journey_retention_cleanup';

	/** Single-event hook used while more expired rows remain. */
	public const CONTINUATION_HOOK = 'shurloc_site_tools_journey_retention_continue';

	/** Non-autoloaded option used as an atomic cleanup lock. */
	public const LOCK_OPTION = 'shurloc_customer_journey_retention_lock';

	/** Time after which an abandoned cleanup lock may be reclaimed. */
	public const LOCK_TIMEOUT_SECONDS = 15 * MINUTE_IN_SECONDS;

	/** Delay before the first daily cleanup event. */
	public const INITIAL_DELAY_SECONDS = 5 * MINUTE_IN_SECONDS;

	/** Delay between bounded continuation batches. */
	public const CONTINUATION_DELAY_SECONDS = MINUTE_IN_SECONDS;

	/**
	 * Bounded retention cleanup orchestration.
	 *
	 * @var Journey_Retention_Service
	 */
	private Journey_Retention_Service $retention;

	/**
	 * Lock value owned by this instance.
	 *
	 * @var string|null
	 */
	private ?string $lock_value = null;

	/**
	 * Constructor.
	 *
	 * @param Journey_Retention_Service|null $retention Retention cleanup service.
	 */
	public function __construct( ?Journey_Retention_Service $retention = null ) {
		$this->retention = $retention ?? new Journey_Retention_Service();
	}

	/**
	 * Register cleanup hooks and ensure the daily event exists.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( self::CONTINUATION_HOOK, array( $this, 'run' ) );

		$this->schedule();
	}

	/**
	 * Ensure exactly one recurring daily cleanup event is scheduled.
	 *
	 * @return bool Whether the event exists or was scheduled successfully.
	 */
	public function schedule(): bool {
		if ( false !== wp_next_scheduled( self::CRON_HOOK ) ) {
			return true;
		}

		return true === wp_schedule_event(
			time() + self::INITIAL_DELAY_SECONDS,
			'daily',
			self::CRON_HOOK
		);
	}

	/**
	 * Remove recurring and pending continuation events on deactivation.
	 *
	 * Journey tables and stored data are not changed.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
	}

	/**
	 * Run one serialized cleanup batch and continue only when more work exists.
	 *
	 * An unexpected exception is contained at the cron boundary. Repository
	 * failures wait for the next daily run rather than creating a retry loop.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			$result = $this->retention->run_batch();

			if ( $result['success'] && $result['has_more'] ) {
				$this->schedule_continuation();
			}
		} catch ( Throwable $error ) {
			unset( $error );
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Schedule one near-term continuation unless one is already pending.
	 *
	 * @return void
	 */
	private function schedule_continuation(): void {
		if ( false !== wp_next_scheduled( self::CONTINUATION_HOOK ) ) {
			return;
		}

		wp_schedule_single_event(
			time() + self::CONTINUATION_DELAY_SECONDS,
			self::CONTINUATION_HOOK
		);
	}

	/**
	 * Acquire the cleanup lock, reclaiming only an unchanged stale value.
	 *
	 * @return bool Whether this instance owns the lock.
	 */
	private function acquire_lock(): bool {
		try {
			$this->lock_value = time() . ':' . bin2hex( random_bytes( 16 ) );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->lock_value = null;
			return false;
		}

		if ( add_option( self::LOCK_OPTION, $this->lock_value, '', false ) ) {
			return true;
		}

		$existing_value = get_option( self::LOCK_OPTION, '' );
		if ( ! is_string( $existing_value ) ) {
			$this->lock_value = null;
			return false;
		}

		$locked_at = (int) strtok( $existing_value, ':' );
		if ( 0 >= $locked_at || time() - $locked_at <= self::LOCK_TIMEOUT_SECONDS ) {
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
	 * Release only the lock value owned by this scheduler instance.
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
	 * Delete a matching lock value atomically and clear its option cache.
	 *
	 * @param string $value Lock value that must still be stored.
	 * @return bool Whether that exact lock value was deleted.
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
}
