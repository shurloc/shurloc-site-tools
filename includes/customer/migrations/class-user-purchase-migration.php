<?php
/**
 * User purchase migration.
 *
 * Seeds last-purchase metadata for existing registered customers from their
 * most recent qualifying WooCommerce order.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Migrations;

defined( 'ABSPATH' ) || exit;

use Exception;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;

/**
 * Seeds purchase tracking data for existing users.
 */
final class User_Purchase_Migration {

	/**
	 * Current migration version.
	 *
	 * Increment when the migration behavior changes in a way that may warrant
	 * rerunning it.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Option storing the timestamp of the most recent migration run.
	 *
	 * This preserves the option used by the original Code Snippet.
	 *
	 * @var string
	 */
	public const LAST_RUN_OPTION = 'sl_last_purchase_seeded';

	/**
	 * Option storing the migration version used for the most recent run.
	 *
	 * @var string
	 */
	public const LAST_RUN_VERSION_OPTION = 'sl_last_purchase_seeded_version';

	/**
	 * Option storing the timestamp when the migration lock was acquired.
	 *
	 * @var string
	 */
	public const LOCK_OPTION = 'sl_last_purchase_migration_lock';

	/**
	 * Maximum age of a migration lock in seconds.
	 *
	 * A stale lock is automatically removed so an interrupted request cannot
	 * permanently prevent future migration runs.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 900;

	/**
	 * Purchase service.
	 *
	 * @var User_Purchase_Service
	 */
	private User_Purchase_Service $purchase_service;

	/**
	 * Constructor.
	 *
	 * @param User_Purchase_Service $purchase_service Purchase service.
	 */
	public function __construct(
		User_Purchase_Service $purchase_service
	) {

		$this->purchase_service = $purchase_service;
	}

	/**
	 * Run the purchase tracking migration.
	 *
	 * Each registered WordPress user is examined. When the user has at least
	 * one qualifying WooCommerce order, their purchase snapshot is replaced
	 * with data from their most recent qualifying order.
	 *
	 * This migration is intentionally rerunnable. Concurrent execution is
	 * controlled separately through the migration lock methods.
	 *
	 * @return array{
	 *     examined:int,
	 *     updated:int,
	 *     skipped:int,
	 *     errors:int
	 * }
	 */
	public function run(): array {

		$result = array(
			'examined' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => 0,
		);

		$user_ids = get_users(
			array(
				'fields' => 'ids',
			)
		);

		foreach ( $user_ids as $user_id ) {

			++$result['examined'];

			try {

				$orders = wc_get_orders(
					array(
						'customer_id' => (int) $user_id,
						'status'      =>
							User_Purchase_Service::QUALIFYING_STATUSES,
						'orderby'     => 'date',
						'order'       => 'DESC',
						'limit'       => 1,
						'return'      => 'objects',
					)
				);

				if ( empty( $orders ) ) {
					++$result['skipped'];
					continue;
				}

				$order = reset( $orders );

				if ( false === $order ) {
					++$result['skipped'];
					continue;
				}

				$stored = $this->purchase_service
					->store_purchase_from_order(
						user_id: (int) $user_id,
						order: $order,
					);

				if ( ! $stored ) {
					++$result['skipped'];
					continue;
				}

				++$result['updated'];

			} catch ( Exception $exception ) {

				unset( $exception );

				++$result['errors'];

				continue;
			}
		}

		update_option(
			self::LAST_RUN_OPTION,
			time()
		);

		update_option(
			self::LAST_RUN_VERSION_OPTION,
			self::VERSION
		);

		return $result;
	}

	/**
	 * Determine whether the migration is currently locked.
	 *
	 * Locks older than the configured timeout are considered stale and are
	 * automatically removed.
	 *
	 * @return bool True when an active migration lock exists.
	 */
	public function is_locked(): bool {

		$locked_at = (int) get_option(
			self::LOCK_OPTION,
			0
		);

		if ( 0 === $locked_at ) {
			return false;
		}

		if (
			time() - $locked_at >
			self::LOCK_TIMEOUT
		) {
			delete_option(
				self::LOCK_OPTION
			);

			return false;
		}

		return true;
	}

	/**
	 * Attempt to acquire the migration lock.
	 *
	 * Function add_option() is used so creation of the lock is atomic at the
	 * database level. If another request creates the option first, this request
	 * cannot acquire the lock.
	 *
	 * @return bool True when the migration lock was acquired.
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
	 * Release the migration lock.
	 *
	 * @return void
	 */
	public function release_lock(): void {

		delete_option(
			self::LOCK_OPTION
		);
	}

	/**
	 * Get the timestamp of the most recent migration run.
	 *
	 * @return int Last-run timestamp, or 0 if the migration has never run.
	 */
	public function get_last_run(): int {

		return (int) get_option(
			self::LAST_RUN_OPTION,
			0
		);
	}

	/**
	 * Get the migration version used for the most recent run.
	 *
	 * @return int Last-run version, or 0 if no version has been recorded.
	 */
	public function get_last_run_version(): int {

		return (int) get_option(
			self::LAST_RUN_VERSION_OPTION,
			0
		);
	}
}
