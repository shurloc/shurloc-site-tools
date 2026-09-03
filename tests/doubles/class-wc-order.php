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
class WC_Order {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	private int $id;

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
	 * @var string
	 */
	private string $total = '0';

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
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->customer_id;
	}

	/**
	 * Set the order status.
	 *
	 * @param string $status Order status.
	 * @return void
	 */
	public function set_status(
		string $status
	): void {
		$this->status = $status;
	}

	/**
	 * Get the order status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Set the order creation date.
	 *
	 * @param int|null $timestamp Order creation timestamp.
	 * @return void
	 */
	public function set_date_created(
		?int $timestamp
	): void {

		if ( null === $timestamp ) {
			$this->date_created = null;
			return;
		}

		$this->date_created = new WC_DateTime(
			'@' . $timestamp
		);
	}

	/**
	 * Get the order creation date.
	 *
	 * @return WC_DateTime|null
	 */
	public function get_date_created(): ?WC_DateTime {
		return $this->date_created;
	}

	/**
	 * Set the order total.
	 *
	 * @param string $total Order total.
	 * @return void
	 */
	public function set_total(
		string $total
	): void {
		$this->total = $total;
	}

	/**
	 * Get the order total.
	 *
	 * @return string
	 */
	public function get_total(): string {
		return $this->total;
	}
}
