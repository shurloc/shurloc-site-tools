<?php
/**
 * User cart admin column.
 *
 * Adds a Cart column to the WordPress Users table and renders a clickable
 * cart details panel for users with a stored cart snapshot.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Services\User_Cart_Service;

/**
 * Adds cart information to the WordPress Users table.
 */
final class User_Cart_Column {

	/**
	 * Cart column key.
	 *
	 * @var string
	 */
	public const CART_COLUMN = 'shurloc_cart';

	/**
	 * Asset handle.
	 *
	 * @var string
	 */
	private const ASSET_HANDLE = 'shurloc-user-cart-column';

	/**
	 * Shared cart details renderer.
	 *
	 * @var Cart_Details_Renderer
	 */
	private Cart_Details_Renderer $cart_details_renderer;

	/**
	 * Constructor.
	 *
	 * @param Cart_Details_Renderer $cart_details_renderer Shared cart details renderer.
	 */
	public function __construct(
		Cart_Details_Renderer $cart_details_renderer
	) {

		$this->cart_details_renderer = $cart_details_renderer;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'manage_users_columns',
			array(
				$this,
				'add_column',
			)
		);

		add_filter(
			'manage_users_custom_column',
			array(
				$this,
				'render_column',
			),
			10,
			3
		);

		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_assets',
			)
		);
	}

	/**
	 * Add the Cart column to the Users table.
	 *
	 * @param array<string,string> $columns Existing Users table columns.
	 * @return array<string,string>
	 */
	public function add_column(
		array $columns
	): array {

		$columns[ self::CART_COLUMN ] = __(
			'Cart',
			'shurloc-site-tools'
		);

		return $columns;
	}

	/**
	 * Render the Cart column.
	 *
	 * @param string $output      Existing column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_column(
		string $output,
		string $column_name,
		int $user_id
	): string {

		if ( self::CART_COLUMN !== $column_name ) {
			return $output;
		}

		$item_count = (int) get_user_meta(
			$user_id,
			User_Cart_Service::CART_COUNT_META_KEY,
			true
		);

		if ( 0 >= $item_count ) {
			return '&mdash;';
		}

		$total = (float) get_user_meta(
			$user_id,
			User_Cart_Service::CART_TOTAL_META_KEY,
			true
		);

		$contents = get_user_meta(
			$user_id,
			User_Cart_Service::CART_ITEMS_META_KEY,
			true
		);

		if ( ! is_array( $contents ) ) {
			$contents = array();
		}

		return $this->cart_details_renderer->render(
			reference: (string) $user_id,
			item_count: $item_count,
			total: $total,
			contents: $contents,
		);
	}

	/**
	 * Enqueue Cart column assets on the Users screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets(
		string $hook_suffix
	): void {

		if ( 'users.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/css/shurloc-user-cart-column.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/js/shurloc-user-cart-column.js',
			array(),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}
}
