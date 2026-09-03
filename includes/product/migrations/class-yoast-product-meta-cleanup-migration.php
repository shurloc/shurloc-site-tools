<?php
/**
 * Yoast product meta cleanup migration.
 *
 * Clears legacy Yoast product metadata from WooCommerce products.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Migrations;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Clears legacy Yoast product metadata.
 */
final class Yoast_Product_Meta_Cleanup_Migration {

	/**
	 * Current migration version.
	 *
	 * Increment when migration behavior changes in a way that may warrant
	 * rerunning it.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Option storing the timestamp of the most recent migration run.
	 *
	 * @var string
	 */
	public const LAST_RUN_OPTION =
		'shurloc_yoast_product_meta_cleanup_last_run';

	/**
	 * Option storing the migration version used for the most recent run.
	 *
	 * @var string
	 */
	public const LAST_RUN_VERSION_OPTION =
		'shurloc_yoast_product_meta_cleanup_last_run_version';

	/**
	 * Option storing the timestamp when the migration lock was acquired.
	 *
	 * @var string
	 */
	public const LOCK_OPTION =
		'shurloc_yoast_product_meta_cleanup_lock';

	/**
	 * Maximum age of a migration lock in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 900;

	/**
	 * Product post type.
	 *
	 * @var string
	 */
	private const POST_TYPE = 'product';

	/**
	 * Yoast product metadata cleared by this migration.
	 *
	 * @var string[]
	 */
	private const META_KEYS = array(
		'_yoast_wpseo_primary_category',
		'_yoast_wpseo_primary_product_cat',
		'_yoast_wpseo_content_score',
	);

	/**
	 * Run the Yoast product metadata cleanup.
	 *
	 * Every WooCommerce product is examined. Products with one or more of the
	 * target Yoast metadata values have those values removed.
	 *
	 * This migration is intentionally rerunnable. Concurrent execution is
	 * controlled separately through the migration lock methods.
	 *
	 * @return array{
	 *     examined: int,
	 *     updated: int,
	 *     skipped: int,
	 *     errors: int
	 * }
	 */
	public function run(): array {

		$result = array(
			'examined' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => 0,
		);

		$product_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $product_ids as $product_id ) {

			++$result['examined'];

			try {

				$updated = false;

				foreach ( self::META_KEYS as $meta_key ) {

					if (
						! metadata_exists(
							'post',
							(int) $product_id,
							$meta_key
						)
					) {
						continue;
					}

					delete_post_meta(
						(int) $product_id,
						$meta_key
					);

					$updated = true;
				}

				if ( ! $updated ) {
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
	 * Function add_option() provides atomic lock creation at the database level.
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
