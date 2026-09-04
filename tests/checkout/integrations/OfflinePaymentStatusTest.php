<?php
/**
 * Tests for Offline_Payment_Status.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * Tests offline payment order status handling.
 */
final class OfflinePaymentStatusTest extends TestCase {

	/**
	 * Sets up each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
	}

	/**
	 * Cleans up after each test.
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['shurloc_test_filters'],
			$GLOBALS['shurloc_test_filter_metadata']
		);

		parent::tearDown();
	}

	/**
	 * Tests that the cheque payment status filter is registered.
	 */
	public function test_register_adds_cheque_status_filter(): void {
		$offline_status = new Offline_Payment_Status();

		$offline_status->register();

		$this->assertSame(
			array(
				array( $offline_status, 'set_processing_status' ),
			),
			$GLOBALS['shurloc_test_filters']
				['woocommerce_cheque_process_payment_order_status']
		);

		$this->assertSame(
			10,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_cheque_process_payment_order_status'][0]['priority']
		);

		$this->assertSame(
			2,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_cheque_process_payment_order_status'][0]['accepted_args']
		);
	}

	/**
	 * Tests that the BACS payment status filter is registered.
	 */
	public function test_register_adds_bacs_status_filter(): void {
		$offline_status = new Offline_Payment_Status();

		$offline_status->register();

		$this->assertSame(
			array(
				array( $offline_status, 'set_processing_status' ),
			),
			$GLOBALS['shurloc_test_filters']
				['woocommerce_bacs_process_payment_order_status']
		);

		$this->assertSame(
			10,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_bacs_process_payment_order_status'][0]['priority']
		);

		$this->assertSame(
			2,
			$GLOBALS['shurloc_test_filter_metadata']
				['woocommerce_bacs_process_payment_order_status'][0]['accepted_args']
		);
	}

	/**
	 * Tests that an on-hold status is changed to processing.
	 */
	public function test_on_hold_status_is_changed_to_processing(): void {
		$offline_status = new Offline_Payment_Status();
		$order          = new WC_Order();

		$result = $offline_status->set_processing_status(
			'on-hold',
			$order
		);

		$this->assertSame(
			'processing',
			$result
		);
	}

	/**
	 * Tests that another incoming status is also changed to processing.
	 */
	public function test_other_status_is_changed_to_processing(): void {
		$offline_status = new Offline_Payment_Status();
		$order          = new WC_Order();

		$result = $offline_status->set_processing_status(
			'pending',
			$order
		);

		$this->assertSame(
			'processing',
			$result
		);
	}
}
