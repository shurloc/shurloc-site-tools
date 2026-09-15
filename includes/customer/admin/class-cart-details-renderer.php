<?php
/**
 * Cart details renderer.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the shared clickable cart summary and details panel.
 *
 * @phpstan-type RenderableCartItem array{
 *     product_id:int,
 *     variation_id:int,
 *     quantity:int,
 *     name:string,
 *     sku:string,
 *     product_url:string,
 *     attributes:string,
 *     line_total:float|null
 * }
 */
final class Cart_Details_Renderer {

	/**
	 * Render a cart summary and details panel.
	 *
	 * @param string           $reference  Non-sensitive cart reference.
	 * @param int              $item_count Total cart item quantity.
	 * @param float            $total      Cart contents total.
	 * @param array<int,mixed> $contents   Normalized cart contents.
	 * @return string
	 */
	public function render(
		string $reference,
		int $item_count,
		float $total,
		array $contents
	): string {

		if ( 0 >= $item_count ) {
			return '&mdash;';
		}

		$panel_id = 'shurloc-cart-panel-' . $this->normalize_reference(
			reference: $reference,
		);

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
	 * Normalize a cart reference for use in an HTML ID.
	 *
	 * @param string $reference Non-sensitive cart reference.
	 * @return string
	 */
	private function normalize_reference(
		string $reference
	): string {

		$normalized_reference = preg_replace(
			'/[^A-Za-z0-9_-]/',
			'-',
			$reference
		);

		if ( ! is_string( $normalized_reference ) || '' === $normalized_reference ) {
			return substr(
				hash( 'sha256', $reference ),
				0,
				12
			);
		}

		return $normalized_reference;
	}

	/**
	 * Normalize a cart item for rendering.
	 *
	 * Supports both original seeded cart snapshots and newer snapshots with
	 * optional variation attributes and line amounts.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return RenderableCartItem
	 */
	private function normalize_cart_item(
		array $item
	): array {

		$product_id = isset( $item['product_id'] ) && is_numeric( $item['product_id'] )
			? (int) $item['product_id']
			: 0;

		$variation_id = isset( $item['variation_id'] ) && is_numeric( $item['variation_id'] )
			? (int) $item['variation_id']
			: 0;

		return array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => isset( $item['quantity'] ) && is_numeric( $item['quantity'] )
				? (int) $item['quantity']
				: 0,
			'name'         => isset( $item['name'] ) && is_string( $item['name'] )
				? $item['name']
				: '',
			'sku'          => isset( $item['sku'] ) && is_string( $item['sku'] )
				? $item['sku']
				: '',
			'product_url'  => $this->get_product_url(
				product_id: $product_id,
				variation_id: $variation_id,
			),
			'attributes'   => $this->get_variation_attributes_text(
				item: $item,
			),
			'line_total'   => isset( $item['line_total'] ) && is_numeric( $item['line_total'] )
				? (float) $item['line_total']
				: null,
		);
	}

	/**
	 * Render a normalized cart item.
	 *
	 * @param array<string,mixed> $item Stored cart item.
	 * @return void
	 */
	private function render_cart_item(
		array $item
	): void {

		$normalized_item = $this->normalize_cart_item(
			item: $item,
		);
		?>

		<div class="shurloc-cart-row">

			<?php echo esc_html( (string) $normalized_item['quantity'] ); ?> ×

			<?php if ( '' !== $normalized_item['product_url'] ) : ?>

				<a
					href="<?php echo esc_url( $normalized_item['product_url'] ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html( $normalized_item['name'] ); ?>
				</a>

			<?php else : ?>

				<?php echo esc_html( $normalized_item['name'] ); ?>

			<?php endif; ?>

			<?php if ( '' !== $normalized_item['sku'] ) : ?>

				<span class="shurloc-cart-sku">
					(<?php echo esc_html( $normalized_item['sku'] ); ?>)
				</span>

			<?php endif; ?>

			<?php if ( null !== $normalized_item['line_total'] ) : ?>

				<span class="shurloc-cart-line-total">
					— <?php echo wp_kses_post( wc_price( $normalized_item['line_total'] ) ); ?>
				</span>

			<?php endif; ?>

			<?php if ( '' !== $normalized_item['attributes'] ) : ?>

				<div class="shurloc-cart-attrs">
					<?php echo esc_html( $normalized_item['attributes'] ); ?>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Get the product URL for a cart item.
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
	 * Get formatted variation attributes for a cart item.
	 *
	 * @param array<string,mixed> $item Normalized cart item.
	 * @return string
	 */
	private function get_variation_attributes_text(
		array $item
	): string {

		if ( ! isset( $item['variation'] ) || ! is_array( $item['variation'] ) ) {
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

			$attribute_parts[] = sprintf(
				'%1$s: %2$s',
				wc_attribute_label( $attribute_key ),
				$attribute_value
			);
		}

		return implode( ' | ', $attribute_parts );
	}
}
