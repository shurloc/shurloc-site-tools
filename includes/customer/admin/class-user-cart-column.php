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

		return $this->render_cart(
			user_id: $user_id,
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

	/**
	 * Render a stored cart snapshot.
	 *
	 * @param int              $user_id    User ID.
	 * @param int              $item_count Total cart item quantity.
	 * @param float            $total      Cart contents total.
	 * @param array<int,mixed> $contents   Stored cart contents.
	 * @return string
	 */
	private function render_cart(
		int $user_id,
		int $item_count,
		float $total,
		array $contents
	): string {

		$panel_id = 'shurloc-cart-panel-' . $user_id;

		ob_start();
		?>

		<div class="shurloc-cart-wrap">

			<a
				href="#"
				class="shurloc-cart-toggle"
				data-target="<?php echo esc_attr( $panel_id ); ?>"
			>
				<strong>
					<?php echo esc_html( (string) $item_count ); ?>
					<?php
					echo esc_html(
						_n(
							'item',
							'items',
							$item_count,
							'shurloc-site-tools'
						)
					);
					?>
				</strong>
			</a>

			<br>

			<?php echo wp_kses_post( wc_price( $total ) ); ?>

			<div
				id="<?php echo esc_attr( $panel_id ); ?>"
				class="shurloc-cart-panel"
			>

				<div class="shurloc-cart-panel-header">

					<strong>
						<?php
						echo esc_html__(
							'Cart Details',
							'shurloc-site-tools'
						);
						?>
					</strong>

					<a
						href="#"
						class="shurloc-cart-close"
						aria-label="<?php echo esc_attr__( 'Close cart details', 'shurloc-site-tools' ); ?>"
					>
						&times;
					</a>

				</div>

				<?php foreach ( $contents as $item ) : ?>

					<?php
					if ( ! is_array( $item ) ) {
						continue;
					}

					$this->render_cart_item(
						item: $item,
					);
					?>

				<?php endforeach; ?>

			</div>

		</div>

		<?php

		$output = ob_get_clean();

		return is_string( $output )
			? $output
			: '';
	}

	/**
	 * Render a stored cart item.
	 *
	 * Supports both the original seeded cart snapshot shape and newer
	 * snapshots containing optional variation attributes.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return void
	 */
	private function render_cart_item(
		array $item
	): void {

		$product_id = isset( $item['product_id'] )
			? (int) $item['product_id']
			: 0;

		$variation_id = isset( $item['variation_id'] )
			? (int) $item['variation_id']
			: 0;

		$quantity = isset( $item['quantity'] )
			? (int) $item['quantity']
			: 0;

		$name = isset( $item['name'] ) && is_string( $item['name'] )
			? $item['name']
			: '';

		$sku = isset( $item['sku'] ) && is_string( $item['sku'] )
			? $item['sku']
			: '';

		$product_url = $this->get_product_url(
			product_id: $product_id,
			variation_id: $variation_id,
		);

		$attributes = $this->get_variation_attributes_text(
			item: $item,
		);
		?>

		<div class="shurloc-cart-row">

			<?php echo esc_html( (string) $quantity ); ?> ×

			<?php if ( '' !== $product_url ) : ?>

				<a
					href="<?php echo esc_url( $product_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html( $name ); ?>
				</a>

			<?php else : ?>

				<?php echo esc_html( $name ); ?>

			<?php endif; ?>

			<?php if ( '' !== $sku ) : ?>

				<span class="shurloc-cart-sku">
					(<?php echo esc_html( $sku ); ?>)
				</span>

			<?php endif; ?>

			<?php if ( '' !== $attributes ) : ?>

				<div class="shurloc-cart-attrs">
					<?php echo esc_html( $attributes ); ?>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Get the product URL for a stored cart item.
	 *
	 * Prefer the variation URL when available and fall back to the parent
	 * product URL.
	 *
	 * @param int $product_id   Parent product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	private function get_product_url(
		int $product_id,
		int $variation_id
	): string {

		if ( 0 < $variation_id ) {

			$url = get_permalink( $variation_id );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		if ( 0 >= $product_id ) {
			return '';
		}

		$url = get_permalink( $product_id );

		return is_string( $url )
			? $url
			: '';
	}

	/**
	 * Get formatted variation attributes for a stored cart item.
	 *
	 * Seeded legacy snapshots do not contain variation metadata, so the field
	 * is treated as optional.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return string
	 */
	private function get_variation_attributes_text(
		array $item
	): string {

		if (
			! isset( $item['variation'] ) ||
			! is_array( $item['variation'] )
		) {
			return '';
		}

		$attribute_parts = array();

		foreach ( $item['variation'] as $attribute_key => $attribute_value ) {

			if (
				! is_string( $attribute_key ) ||
				! is_string( $attribute_value ) ||
				'' === $attribute_value
			) {
				continue;
			}

			$attribute_key = str_replace(
				'attribute_',
				'',
				$attribute_key
			);

			$attribute_label = wc_attribute_label(
				$attribute_key
			);

			$attribute_parts[] = sprintf(
				'%1$s: %2$s',
				$attribute_label,
				$attribute_value
			);
		}

		return implode(
			' | ',
			$attribute_parts
		);
	}
}
