<?php
/**
 * Payment gateway labels.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use WC_Abstract_Order;
use WC_Email;
use WC_Order;

/**
 * Customizes WooCommerce payment gateway labels.
 */
final class Payment_Gateway_Labels {

	/**
	 * PayPal gateway ID.
	 */
	private const PAYPAL_GATEWAY_ID = 'ppcp-gateway';

	/**
	 * PayPal card gateway ID.
	 */
	private const PAYPAL_CARD_GATEWAY_ID = 'ppcp-card-button-gateway';

	/**
	 * PayPal checkout label.
	 */
	private const PAYPAL_CHECKOUT_LABEL = 'PayPal/Venmo';

	/**
	 * PayPal card email label.
	 */
	private const PAYPAL_CARD_EMAIL_LABEL = 'Debit & Credit Cards (PayPal)';

	/**
	 * Whether an order email is currently being rendered.
	 *
	 * @var bool
	 */
	private bool $is_email_context = false;

	/**
	 * Registers WooCommerce hooks.
	 */
	public function register(): void {
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'filter_gateway_title' ),
			10,
			2
		);

		add_action(
			'woocommerce_email_before_order_table',
			array( $this, 'begin_email_context' ),
			10,
			4
		);

		add_action(
			'woocommerce_email_after_order_table',
			array( $this, 'end_email_context' ),
			10,
			4
		);

		add_filter(
			'woocommerce_get_order_item_totals',
			array( $this, 'filter_order_item_totals' ),
			10,
			3
		);
	}

	/**
	 * Customizes the payment gateway title.
	 *
	 * @param string $title      Payment gateway title.
	 * @param string $gateway_id Payment gateway ID.
	 * @return string
	 */
	public function filter_gateway_title(
		string $title,
		string $gateway_id
	): string {
		if ( self::PAYPAL_GATEWAY_ID !== $gateway_id ) {
			return $title;
		}

		return self::PAYPAL_CHECKOUT_LABEL;
	}

	/**
	 * Enables email context for the admin new order email.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether the email is sent to an administrator.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param WC_Email $email         Email.
	 */
	public function begin_email_context(
		WC_Order $order,
		bool $sent_to_admin,
		bool $plain_text,
		WC_Email $email
	): void {
		unset(
			$order,
			$plain_text
		);

		$this->is_email_context =
		$sent_to_admin &&
		'new_order' === $email->id;
	}

	/**
	 * Disables email context after the order table is rendered.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether the email is sent to an administrator.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param WC_Email $email         Email.
	 */
	public function end_email_context(
		WC_Order $order,
		bool $sent_to_admin,
		bool $plain_text,
		WC_Email $email
	): void {
		unset(
			$order,
			$sent_to_admin,
			$plain_text,
			$email
		);

		$this->is_email_context = false;
	}

	/**
	 * Customizes the payment method label in order emails.
	 *
	 * @param array<string|int, mixed> $totals      Order item totals.
	 * @param WC_Abstract_Order        $order       Order.
	 * @param string                   $tax_display Tax display mode.
	 * @return array<string|int, mixed>
	 */
	public function filter_order_item_totals(
		array $totals,
		WC_Abstract_Order $order,
		string $tax_display
	): array {
		unset( $tax_display );

		if ( ! $this->is_email_context ) {
			return $totals;
		}

		if ( ! $order instanceof WC_Order ) {
			return $totals;
		}

		if (
			self::PAYPAL_CARD_GATEWAY_ID !==
			$order->get_payment_method()
		) {
			return $totals;
		}

		if ( ! isset( $totals['payment_method'] ) ) {
			return $totals;
		}

		if ( ! is_array( $totals['payment_method'] ) ) {
			return $totals;
		}

		$totals['payment_method']['value'] =
			self::PAYPAL_CARD_EMAIL_LABEL;

		return $totals;
	}
}
