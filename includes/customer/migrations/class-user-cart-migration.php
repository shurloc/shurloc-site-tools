<?php
/**
 * User cart migration.
 *
 * Seeds cart snapshot metadata for existing registered customers from stored
 * WooCommerce sessions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Migrations;

defined( 'ABSPATH' ) || exit;

use Exception;
use Shurloc\SiteTools\Customer\Services\User_Cart_Service;

/**
 * Seeds cart tracking data for existing users.
 *
 * @phpstan-type CartSnapshotItem array{
 *     cart_item_key:string,
 *     product_id:int,
 *     variation_id:int,
 *     name:string,
 *     sku:string,
 *     quantity:int,
 *     line_subtotal:float,
 *     line_total:float,
 *     variation:array<string,string>
 * }
 */
final class User_Cart_Migration {

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
	 * This preserves the option used by the original Code Snippet.
	 *
	 * @var string
	 */
	public const LAST_RUN_OPTION = 'sl_cart_snapshot_seeded';

	/**
	 * Option storing the migration version used for the most recent run.
	 *
	 * @var string
	 */
	public const LAST_RUN_VERSION_OPTION =
		'sl_cart_snapshot_seeded_version';

	/**
	 * Option storing the timestamp when the migration lock was acquired.
	 *
	 * @var string
	 */
	public const LOCK_OPTION =
		'sl_cart_snapshot_migration_lock';

	/**
	 * Maximum age of a migration lock in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 900;

	/**
	 * Cart service.
	 *
	 * @var User_Cart_Service
	 */
	private User_Cart_Service $cart_service;

	/**
	 * Constructor.
	 *
	 * @param User_Cart_Service $cart_service Cart service.
	 */
	public function __construct(
		User_Cart_Service $cart_service
	) {

		$this->cart_service = $cart_service;
	}

	/**
	 * Run the cart snapshot migration.
	 *
	 * Stored WooCommerce sessions belonging to registered users are examined.
	 * Valid non-empty carts are normalized and written through the cart
	 * service.
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

		$rows = $this->get_session_rows();

		foreach ( $rows as $row ) {

			++$result['examined'];

			try {

				$user_id = $this->get_session_user_id(
					session_key: $row->session_key,
				);

				if ( 0 >= $user_id ) {
					++$result['skipped'];
					continue;
				}

				if ( ! get_userdata( $user_id ) ) {
					++$result['skipped'];
					continue;
				}

				$session = maybe_unserialize(
					$row->session_value
				);

				if (
					! is_array( $session ) ||
					empty( $session['cart'] )
				) {
					++$result['skipped'];
					continue;
				}

				$stored_cart = maybe_unserialize(
					$session['cart']
				);

				if (
					! is_array( $stored_cart ) ||
					empty( $stored_cart )
				) {
					++$result['skipped'];
					continue;
				}

				$cart_contents = $this->normalize_cart_contents(
					stored_cart: $stored_cart,
				);

				if ( empty( $cart_contents ) ) {
					++$result['skipped'];
					continue;
				}

				$contents_total = $this->get_contents_total(
					session: $session,
					cart_contents: $cart_contents,
				);

				$stored = $this->cart_service->store_cart_snapshot(
					user_id: $user_id,
					cart_contents: $cart_contents,
					contents_total: $contents_total,
					updated_at: time(),
					expires_at: (int) $row->session_expiry,
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
	 * Stale locks are automatically removed.
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

	/**
	 * Retrieve stored WooCommerce session rows.
	 *
	 * @return array<int,object{
	 *     session_key:string,
	 *     session_value:string,
	 *     session_expiry:string|int
	 * }>
	 */
	private function get_session_rows(): array {

		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_sessions';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_key, session_value, session_expiry FROM %i',
				$table_name
			)
		);

		return is_array( $rows )
			? $rows
			: array();
	}

	/**
	 * Get a registered user ID from a WooCommerce session key.
	 *
	 * Guest sessions use non-numeric session keys and are ignored.
	 *
	 * @param string $session_key WooCommerce session key.
	 * @return int User ID, or 0 for a guest session.
	 */
	private function get_session_user_id(
		string $session_key
	): int {

		if ( ! ctype_digit( $session_key ) ) {
			return 0;
		}

		return (int) $session_key;
	}

	/**
	 * Normalize stored WooCommerce cart contents.
	 *
	 * @param array<string,mixed> $stored_cart Stored WooCommerce cart data.
	 * @return array<int,CartSnapshotItem>
	 */
	private function normalize_cart_contents(
		array $stored_cart
	): array {

		$cart_contents = array();

		foreach ( $stored_cart as $item_key => $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$product_id = isset( $item['product_id'] )
				? (int) $item['product_id']
				: 0;

			$variation_id = isset( $item['variation_id'] )
				? (int) $item['variation_id']
				: 0;

			$product = wc_get_product(
				0 < $variation_id
					? $variation_id
					: $product_id
			);

			if ( ! $product ) {
				continue;
			}

			$quantity = isset( $item['quantity'] )
				? (int) $item['quantity']
				: 0;

			if ( 0 >= $quantity ) {
				continue;
			}

			$cart_contents[] = array(
				'cart_item_key' => (string) $item_key,
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'name'          => $product->get_name(),
				'sku'           => $product->get_sku(),
				'quantity'      => $quantity,
				'line_subtotal' => isset( $item['line_subtotal'] )
					? (float) $item['line_subtotal']
					: 0.0,
				'line_total'    => isset( $item['line_total'] )
					? (float) $item['line_total']
					: 0.0,
				'variation'     => $this->normalize_variation_attributes(
					item: $item,
				),
			);
		}

		return $cart_contents;
	}

	/**
	 * Normalize variation attributes from stored cart data.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return array<string,string>
	 */
	private function normalize_variation_attributes(
		array $item
	): array {

		if (
			! isset( $item['variation'] ) ||
			! is_array( $item['variation'] )
		) {
			return array();
		}

		$variation = array();

		foreach (
			$item['variation']
			as $attribute => $value
		) {

			if (
				! is_string( $attribute ) ||
				! is_string( $value )
			) {
				continue;
			}

			$variation[ $attribute ] = $value;
		}

		return $variation;
	}

	/**
	 * Determine the cart contents total for a stored session.
	 *
	 * The WooCommerce cart totals value is preferred when available. If it
	 * cannot be used, the total is calculated from the normalized line totals.
	 *
	 * @param array<string,mixed>         $session       Stored session data.
	 * @param array<int,CartSnapshotItem> $cart_contents Normalized cart data.
	 * @return float Cart contents total.
	 */
	private function get_contents_total(
		array $session,
		array $cart_contents
	): float {

		if ( ! empty( $session['cart_totals'] ) ) {

			$totals = maybe_unserialize(
				$session['cart_totals']
			);

			if (
				is_array( $totals ) &&
				isset( $totals['cart_contents_total'] )
			) {
				return (float) $totals['cart_contents_total'];
			}
		}

		$contents_total = 0.0;

		foreach ( $cart_contents as $item ) {
			$contents_total += $item['line_total'];
		}

		return $contents_total;
	}
}
