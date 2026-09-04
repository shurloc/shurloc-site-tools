<?php
/**
 * Payment processing fee calculation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

/**
 * Adds payment processing fees to the WooCommerce cart.
 */
final class Payment_Processing_Fee {

	/**
	 * Standard processing fee rate.
	 */
	private const STANDARD_FEE_RATE = 0.015;

	/**
	 * Higher processing fee rate.
	 */
	private const HIGHER_FEE_RATE = 0.0175;

	/**
	 * Gateways that incur a processing fee.
	 *
	 * @var string[]
	 */
	private const FEE_GATEWAYS = array(
		'paypal',
		'ppec_paypal',
		'paypal_express',
		'paypal_standard',
		'ppcp-gateway',
		'ppcp-card-button-gateway',
		'bacs',
	);

	/**
	 * Gateways that incur the higher processing fee.
	 *
	 * @var string[]
	 */
	private const HIGHER_FEE_GATEWAYS = array(
		'paypal',
		'ppec_paypal',
		'paypal_express',
		'paypal_standard',
		'ppcp-gateway',
	);

	/**
	 * Registers WooCommerce hooks.
	 */
	public function register(): void {
		add_action(
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_processing_fee' ),
			999
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);
	}

	/**
	 * Adds the applicable payment processing fee.
	 */
	public function add_processing_fee(): void {
		if (
			is_admin() &&
			! defined( 'DOING_AJAX' )
		) {
			return;
		}

		if ( ! is_checkout() ) {
			return;
		}

		$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );

		if (
			! is_string( $chosen_payment_method ) ||
			! in_array(
				$chosen_payment_method,
				self::FEE_GATEWAYS,
				true
			)
		) {
			return;
		}

		$fee_rate = $this->get_fee_rate(
			$chosen_payment_method
		);

		$fee_name = sprintf(
			'Payment Processing Fee (%.2f%%)',
			$fee_rate * 100
		);

		$base_amount  = (float) WC()->cart->get_cart_contents_total();
		$base_amount += (float) WC()->cart->get_shipping_total();

		foreach ( WC()->cart->get_fees() as $fee ) {
			if ( $fee_name === $fee->name ) {
				continue;
			}

			$base_amount += (float) $fee->amount;
		}

		$base_amount += array_sum(
			WC()->cart->get_taxes()
		);

		$processing_fee = round(
			$base_amount * $fee_rate,
			2
		);

		WC()->cart->add_fee(
			$fee_name,
			$processing_fee,
			false
		);
	}

	/**
	 * Enqueues payment processing fee assets.
	 */
	public function enqueue_assets(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'shurloc-payment-processing-fee',
			SHURLOC_SITE_TOOLS_URL . 'assets/checkout/js/shurloc-payment-processing-fee.js',
			array( 'jquery' ),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}

	/**
	 * Gets the processing fee rate for a payment gateway.
	 *
	 * @param string $gateway_id Payment gateway ID.
	 * @return float
	 */
	private function get_fee_rate(
		string $gateway_id
	): float {
		if (
			in_array(
				$gateway_id,
				self::HIGHER_FEE_GATEWAYS,
				true
			)
		) {
			return self::HIGHER_FEE_RATE;
		}

		return self::STANDARD_FEE_RATE;
	}
}
