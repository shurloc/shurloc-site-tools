<?php
/**
 * Cart listing service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Cart_Link_Repository;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use WC_Product;

/**
 * Prepares stored WooCommerce carts for the administrative listing.
 *
 * @phpstan-type ListingCartItem array{
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
 * @phpstan-type ListingCart array{
 *     session_reference:string,
 *     user_id:int,
 *     journey_visitor_id:int|null,
 *     cart_contents:array<int,ListingCartItem>,
 *     item_count:int,
 *     contents_total:float,
 *     expires_at:int,
 *     is_expired:bool,
 *     last_activity_at:int,
 *     status:string
 * }
 * @phpstan-type CartListing array{
 *     items:array<int,ListingCart>,
 *     counts:array{all:int,authenticated:int,unauthenticated:int},
 *     filter:string,
 *     page:int,
 *     per_page:int,
 *     total_items:int,
 *     total_pages:int
 * }
 */
final class Cart_Listing_Service {

	/**
	 * Registered user IDs excluded from the cart report.
	 *
	 * Add customer IDs here when their carts should not appear in the report.
	 * Administrator carts are excluded separately by capability.
	 *
	 * @var int[]
	 */
	public const EXCLUDED_USER_IDS = array();

	/**
	 * All-cart filter.
	 *
	 * @var string
	 */
	public const FILTER_ALL = 'all';

	/**
	 * Authenticated-cart filter.
	 *
	 * @var string
	 */
	public const FILTER_AUTHENTICATED = 'authenticated';

	/**
	 * Unauthenticated-cart filter.
	 *
	 * @var string
	 */
	public const FILTER_UNAUTHENTICATED = 'unauthenticated';

	/**
	 * Expired status.
	 *
	 * @var string
	 */
	public const STATUS_EXPIRED = 'Expired';

	/**
	 * Logged-in status.
	 *
	 * @var string
	 */
	public const STATUS_LOGGED_IN = 'Logged In';

	/**
	 * Active status.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'Active';

	/**
	 * Possibly-abandoned status.
	 *
	 * @var string
	 */
	public const STATUS_POSSIBLY_ABANDONED = 'Possibly Abandoned';

	/**
	 * Default abandonment threshold in seconds.
	 *
	 * @var int
	 */
	public const DEFAULT_ABANDONMENT_THRESHOLD = 3600;

	/**
	 * Default number of carts per page.
	 *
	 * @var int
	 */
	public const DEFAULT_PER_PAGE = 20;

	/**
	 * WooCommerce's default session lifetime in seconds.
	 *
	 * @var int
	 */
	private const DEFAULT_SESSION_LIFETIME = 48 * 3600;

	/**
	 * Maximum number of carts allowed per page.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Cart session repository.
	 *
	 * @var Cart_Session_Repository
	 */
	private Cart_Session_Repository $cart_session_repository;

	/**
	 * Journey cart correlation repository.
	 *
	 * @var Journey_Cart_Link_Repository
	 */
	private Journey_Cart_Link_Repository $cart_link_repository;

	/**
	 * Registered user IDs excluded from the report.
	 *
	 * @var int[]
	 */
	private array $excluded_user_ids;

	/**
	 * Constructor.
	 *
	 * @param Cart_Session_Repository           $cart_session_repository Cart session repository.
	 * @param array<int>                        $excluded_user_ids      Registered user IDs to exclude.
	 * @param Journey_Cart_Link_Repository|null $cart_link_repository  Journey cart correlation repository.
	 */
	public function __construct(
		Cart_Session_Repository $cart_session_repository,
		array $excluded_user_ids = self::EXCLUDED_USER_IDS,
		?Journey_Cart_Link_Repository $cart_link_repository = null
	) {

		$this->cart_session_repository = $cart_session_repository;
		$this->excluded_user_ids       = $excluded_user_ids;
		$this->cart_link_repository    = $cart_link_repository ?? new Journey_Cart_Link_Repository();
	}

	/**
	 * Get a filtered, paginated cart listing.
	 *
	 * @param string $filter   Requested visitor filter.
	 * @param int    $page     Requested page number.
	 * @param int    $per_page Requested page size.
	 * @return CartListing
	 */
	public function get_listing(
		string $filter = self::FILTER_ALL,
		int $page = 1,
		int $per_page = self::DEFAULT_PER_PAGE
	): array {

		$filter   = $this->normalize_filter( filter: $filter );
		$page     = max( 1, $page );
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$stored_carts = array_values(
			array_filter(
				$this->cart_session_repository->find_non_empty(),
				fn ( array $cart ): bool => ! $this->is_excluded( cart: $cart )
			)
		);
		$counts       = $this->get_counts( carts: $stored_carts );
		$filtered     = $this->filter_carts(
			carts: $stored_carts,
			filter: $filter,
		);

		$total_items = count( $filtered );
		$total_pages = (int) ceil( $total_items / $per_page );
		$page        = min( $page, max( 1, $total_pages ) );

		$page_carts = array_slice(
			$filtered,
			( $page - 1 ) * $per_page,
			$per_page
		);

		return array(
			'items'       => $this->prepare_carts( carts: $page_carts ),
			'counts'      => $counts,
			'filter'      => $filter,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
		);
	}

	/**
	 * Normalize a visitor filter.
	 *
	 * @param string $filter Requested filter.
	 * @return string
	 */
	private function normalize_filter(
		string $filter
	): string {

		if (
			in_array(
				$filter,
				array(
					self::FILTER_ALL,
					self::FILTER_AUTHENTICATED,
					self::FILTER_UNAUTHENTICATED,
				),
				true
			)
		) {
			return $filter;
		}

		return self::FILTER_ALL;
	}

	/**
	 * Count carts by visitor type.
	 *
	 * @param array<int,array<string,mixed>> $carts Stored carts.
	 * @return array{all:int,authenticated:int,unauthenticated:int}
	 */
	private function get_counts(
		array $carts
	): array {

		$counts = array(
			'all'             => count( $carts ),
			'authenticated'   => 0,
			'unauthenticated' => 0,
		);

		foreach ( $carts as $cart ) {
			if ( $this->is_authenticated( cart: $cart ) ) {
				++$counts['authenticated'];
			} else {
				++$counts['unauthenticated'];
			}
		}

		return $counts;
	}

	/**
	 * Filter carts by visitor type.
	 *
	 * @param array<int,array<string,mixed>> $carts  Stored carts.
	 * @param string                         $filter Visitor filter.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_carts(
		array $carts,
		string $filter
	): array {

		if ( self::FILTER_ALL === $filter ) {
			return $carts;
		}

		$authenticated = self::FILTER_AUTHENTICATED === $filter;

		return array_values(
			array_filter(
				$carts,
				fn ( array $cart ): bool =>
					$authenticated === $this->is_authenticated( cart: $cart )
			)
		);
	}

	/**
	 * Determine whether a cart belongs to a registered user.
	 *
	 * @param array<string,mixed> $cart Stored cart.
	 * @return bool
	 */
	private function is_authenticated(
		array $cart
	): bool {

		return isset( $cart['user_id'] ) && 0 < (int) $cart['user_id'];
	}

	/**
	 * Determine whether a cart should be hidden from the report.
	 *
	 * @param array<string,mixed> $cart Stored cart.
	 * @return bool
	 */
	private function is_excluded(
		array $cart
	): bool {

		$user_id = isset( $cart['user_id'] ) ? (int) $cart['user_id'] : 0;

		if ( 0 >= $user_id ) {
			return false;
		}

		return in_array( $user_id, $this->excluded_user_ids, true ) ||
			user_can( $user_id, 'manage_options' );
	}

	/**
	 * Prepare page carts for rendering.
	 *
	 * @param array<int,array<string,mixed>> $carts Stored page carts.
	 * @return array<int,ListingCart>
	 */
	private function prepare_carts(
		array $carts
	): array {

		$prepared_carts = array();
		$product_cache  = array();

		foreach ( $carts as $cart ) {
			$expires_at = isset( $cart['expires_at'] )
				? (int) $cart['expires_at']
				: 0;

			$is_expired         = ! empty( $cart['is_expired'] );
			$user_id            = isset( $cart['user_id'] )
				? (int) $cart['user_id']
				: 0;
			$cart_token_hash    = isset( $cart['cart_token_hash'] ) && is_string( $cart['cart_token_hash'] )
				? $cart['cart_token_hash']
				: null;
			$journey_visitor_id = 0 >= $user_id && null !== $cart_token_hash
				? $this->cart_link_repository->find_latest_visitor_id( cart_token_hash: $cart_token_hash )
				: null;

			$prepared_carts[] = array(
				'session_reference'  => isset( $cart['session_reference'] ) && is_string( $cart['session_reference'] )
					? $cart['session_reference']
					: '',
				'user_id'            => $user_id,
				'journey_visitor_id' => $journey_visitor_id,
				'cart_contents'      => $this->prepare_cart_contents(
					contents: isset( $cart['cart_contents'] ) && is_array( $cart['cart_contents'] )
						? $cart['cart_contents']
						: array(),
					product_cache: $product_cache,
				),
				'item_count'         => isset( $cart['item_count'] )
					? (int) $cart['item_count']
					: 0,
				'contents_total'     => isset( $cart['contents_total'] )
					? (float) $cart['contents_total']
					: 0.0,
				'expires_at'         => $expires_at,
				'is_expired'         => $is_expired,
				'last_activity_at'   => $this->get_last_activity( expires_at: $expires_at ),
				'status'             => $this->get_status(
					user_id: $user_id,
					is_expired: $is_expired,
					expires_at: $expires_at,
				),
			);
		}

		return $prepared_carts;
	}

	/**
	 * Prepare stored cart contents for the shared details renderer.
	 *
	 * Product objects are cached by ID across the current page to avoid repeat
	 * lookups without instantiating full cart or session objects.
	 *
	 * @param array<array-key,mixed>      $contents      Stored cart contents.
	 * @param array<int,WC_Product|false> $product_cache Product cache by ID.
	 * @return array<int,ListingCartItem>
	 */
	private function prepare_cart_contents(
		array $contents,
		array &$product_cache
	): array {

		$prepared_contents = array();

		foreach ( $contents as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product_id = isset( $item['product_id'] )
				? (int) $item['product_id']
				: 0;

			$variation_id = isset( $item['variation_id'] )
				? (int) $item['variation_id']
				: 0;

			$lookup_id = 0 < $variation_id
				? $variation_id
				: $product_id;

			if ( ! array_key_exists( $lookup_id, $product_cache ) ) {
				$product = 0 < $lookup_id
					? wc_get_product( $lookup_id )
					: false;

				$product_cache[ $lookup_id ] = $product instanceof WC_Product
					? $product
					: false;
			}

			$product = $product_cache[ $lookup_id ];
			$name    = $product instanceof WC_Product
				? $product->get_name()
				: sprintf(
					/* translators: %d: WooCommerce product ID. */
					__( 'Product #%d', 'shurloc-site-tools' ),
					$lookup_id
				);

			$prepared_contents[] = array(
				'cart_item_key' => isset( $item['cart_item_key'] ) && is_string( $item['cart_item_key'] )
					? $item['cart_item_key']
					: '',
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'name'          => $name,
				'sku'           => $product instanceof WC_Product
					? $product->get_sku()
					: '',
				'quantity'      => isset( $item['quantity'] )
					? (int) $item['quantity']
					: 0,
				'line_subtotal' => isset( $item['line_subtotal'] )
					? (float) $item['line_subtotal']
					: 0.0,
				'line_total'    => isset( $item['line_total'] )
					? (float) $item['line_total']
					: 0.0,
				'variation'     => $this->normalize_variation( item: $item ),
			);
		}

		return $prepared_contents;
	}

	/**
	 * Normalize variation attributes.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return array<string,string>
	 */
	private function normalize_variation(
		array $item
	): array {

		if ( ! isset( $item['variation'] ) || ! is_array( $item['variation'] ) ) {
			return array();
		}

		$variation = array();

		foreach ( $item['variation'] as $attribute => $value ) {
			if ( is_string( $attribute ) && is_string( $value ) ) {
				$variation[ $attribute ] = $value;
			}
		}

		return $variation;
	}

	/**
	 * Derive the best available activity timestamp from session expiry.
	 *
	 * WooCommerce's default session table stores expiry but no independent
	 * last-activity timestamp. Subtracting the configured session lifetime gives
	 * the closest available activity estimate without inventing finer precision.
	 *
	 * @param int $expires_at Session expiry timestamp.
	 * @return int
	 */
	private function get_last_activity(
		int $expires_at
	): int {

		$lifetime = apply_filters(
			'wc_session_expiration',
			self::DEFAULT_SESSION_LIFETIME
		);

		if ( ! is_numeric( $lifetime ) || 0 >= (int) $lifetime ) {
			$lifetime = self::DEFAULT_SESSION_LIFETIME;
		}

		return max( 0, $expires_at - (int) $lifetime );
	}

	/**
	 * Determine informational cart status.
	 *
	 * @param int  $user_id    Registered user ID, or zero for a guest.
	 * @param bool $is_expired Whether the persisted session is expired.
	 * @param int  $expires_at Session expiry timestamp.
	 * @return string
	 */
	private function get_status(
		int $user_id,
		bool $is_expired,
		int $expires_at
	): string {

		if ( $is_expired ) {
			return self::STATUS_EXPIRED;
		}

		if ( 0 < $user_id ) {
			return self::STATUS_LOGGED_IN;
		}

		$threshold = apply_filters(
			'shurloc_site_tools_cart_abandonment_threshold',
			self::DEFAULT_ABANDONMENT_THRESHOLD
		);

		if ( ! is_numeric( $threshold ) || 0 > (int) $threshold ) {
			$threshold = self::DEFAULT_ABANDONMENT_THRESHOLD;
		}

		$last_activity = $this->get_last_activity(
			expires_at: $expires_at,
		);

		return time() - $last_activity > (int) $threshold
			? self::STATUS_POSSIBLY_ABANDONED
			: self::STATUS_ACTIVE;
	}
}
