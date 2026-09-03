<?php
/**
 * WooCommerce order test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * WooCommerce order test double.
 */
class WC_Order extends WC_Abstract_Order {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Customer user ID.
	 *
	 * @var int
	 */
	private int $customer_id = 0;

	/**
	 * Billing company.
	 *
	 * @var string
	 */
	private string $billing_company = '';

	/**
	 * Order status.
	 *
	 * @var string
	 */
	private string $status = '';

	/**
	 * Order creation date.
	 *
	 * @var WC_DateTime|null
	 */
	private ?WC_DateTime $date_created = null;

	/**
	 * Order total.
	 *
	 * @var float
	 */
	private float $total = 0.0;

	/**
	 * Payment method ID.
	 *
	 * @var string
	 */
	private string $payment_method = '';

	/**
	 * Constructor.
	 *
	 * @param int $order_id Order ID.
	 */
	public function __construct(
		int $order_id = 0
	) {
		$this->id = $order_id;
	}

	/**
	 * Get the order ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Set the customer user ID.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return void
	 */
	public function set_customer_id(
		int $customer_id
	): void {
		$this->customer_id = $customer_id;
	}

	/**
	 * Get the customer user ID.
	 *
	 * @return int
	 */
	public function get_customer_id(): int {
		return $this->customer_id;
	}

	/**
	 * Set the billing company.
	 *
	 * @param string $company Billing company.
	 * @return void
	 */
	public function set_billing_company(
		string $company
	): void {
		$this->billing_company = $company;
	}

	/**
	 * Get the billing company.
	 *
	 * @return string
	 */
	public function get_billing_company(): string {
		return $this->billing_company;
	}

	/**
	 * Get the customer user ID.
	 *
	 * @param mixed $context Access context.
	 * @return int
	 */
	public function get_user_id(
		mixed $context = 'view'
	): int {
		unset( $context );

		return $this->customer_id;
	}

	/**
	 * Set the order status.
	 *
	 * @param mixed $new_status Order status.
	 * @return array{from: string, to: string} Status transition details.
	 */
	public function set_status(
		mixed $new_status
	): array {
		$old_status   = $this->status;
		$this->status = (string) $new_status;

		return array(
			'from' => $old_status,
			'to'   => $this->status,
		);
	}

	/**
	 * Get the order status.
	 *
	 * @param mixed $context Access context.
	 * @return string
	 */
	public function get_status(
		mixed $context = 'view'
	): string {
		unset( $context );

		return $this->status;
	}

	/**
	 * Set the order creation date.
	 *
	 * @param mixed $date Order creation timestamp.
	 * @return void
	 */
	public function set_date_created(
		mixed $date = null
	): void {

		if ( null === $date ) {
			$this->date_created = null;
			return;
		}

		$this->date_created = new WC_DateTime(
			'@' . (int) $date
		);
	}

	/**
	 * Get the order creation date.
	 *
	 * @param mixed $context Access context.
	 * @return WC_DateTime|null
	 */
	public function get_date_created(
		mixed $context = 'view'
	): ?WC_DateTime {
		unset( $context );

		return $this->date_created;
	}

	/**
	 * Set the order total.
	 *
	 * @param mixed $value      Order total.
	 * @param mixed $deprecated Deprecated argument.
	 * @return void
	 */
	public function set_total(
		mixed $value,
		mixed $deprecated = ''
	): void {
		unset( $deprecated );

		$this->total = (float) $value;
	}

	/**
	 * Get the order total.
	 *
	 * @param mixed $context Access context.
	 * @return float
	 */
	public function get_total(
		mixed $context = 'view'
	): float {
		unset( $context );

		return $this->total;
	}

	/**
	 * Set the payment method ID.
	 *
	 * @param string $payment_method Payment method ID.
	 * @return void
	 */
	public function set_payment_method(
		string $payment_method
	): void {
		$this->payment_method = $payment_method;
	}

	/**
	 * Get the payment method ID.
	 *
	 * @return string
	 */
	public function get_payment_method(): string {
		return $this->payment_method;
	}
}
