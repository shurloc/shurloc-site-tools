<?php
/**
 * Tests for Payment_Processing_Fee.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Checkout\Test_Fee;
use Shurloc\SiteTools\Checkout\Test_WooCommerce;

/**
 * Tests payment processing fee calculation.
 */
final class PaymentProcessingFeeTest extends TestCase {

	/**
	 * Test WooCommerce instance.
	 *
	 * @var Test_WooCommerce
	 */
	private Test_WooCommerce $woocommerce;

	/**
	 * Sets up each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->woocommerce = new Test_WooCommerce();

		$GLOBALS['shurloc_test_woocommerce']      = $this->woocommerce;
		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_is_admin']         = false;
		$GLOBALS['shurloc_test_is_checkout']      = true;
		$GLOBALS['shurloc_test_enqueued_scripts'] = array();
	}

	/**
	 * Cleans up after each test.
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_woocommerce'] = null;

		unset(
			$GLOBALS['shurloc_test_actions'],
			$GLOBALS['shurloc_test_action_metadata'],
			$GLOBALS['shurloc_test_is_admin'],
			$GLOBALS['shurloc_test_is_checkout'],
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		parent::tearDown();
	}

	/**
	 * Tests that the WooCommerce fee hook is registered at priority 999.
	 */
	public function test_register_adds_cart_calculate_fees_action(): void {
		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->register();

		$this->assertSame(
			array(
				array( $processing_fee, 'add_processing_fee' ),
			),
			$GLOBALS['shurloc_test_actions']
				['woocommerce_cart_calculate_fees']
		);

		$this->assertSame(
			999,
			$GLOBALS['shurloc_test_action_metadata']
				['woocommerce_cart_calculate_fees'][0]['priority']
		);

		$this->assertSame(
			array(
				array( $processing_fee, 'enqueue_assets' ),
			),
			$GLOBALS['shurloc_test_actions']['wp_enqueue_scripts']
		);

		$this->assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['wp_enqueue_scripts'][0]['priority']
		);
	}

	/**
	 * Tests that processing fees JS is enqueued on checkout.
	 */
	public function test_processing_fee_script_is_enqueued_on_checkout(): void {
		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->enqueue_assets();

		$this->assertCount(
			1,
			$GLOBALS['shurloc_test_enqueued_scripts']
		);

		$this->assertSame(
			'shurloc-payment-processing-fee',
			$GLOBALS['shurloc_test_enqueued_scripts'][0]['handle']
		);
	}

	/**
	 * Tests that the processing fee script is not enqueued outside checkout.
	 */
	public function test_processing_fee_script_is_not_enqueued_outside_checkout(): void {
		$GLOBALS['shurloc_test_is_checkout'] = false;

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->enqueue_assets();

		$this->assertSame(
			array(),
			$GLOBALS['shurloc_test_enqueued_scripts']
		);
	}

	/**
	 * Tests that processing fees are not added outside checkout.
	 */
	public function test_no_fee_is_added_outside_checkout(): void {
		$GLOBALS['shurloc_test_is_checkout'] = false;

		$this->set_payment_method( 'bacs' );
		$this->woocommerce->cart->set_cart_contents_total( 100.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that processing fees are not added during normal admin requests.
	 */
	public function test_no_fee_is_added_during_admin_request(): void {
		$GLOBALS['shurloc_test_is_admin'] = true;

		$this->set_payment_method( 'bacs' );
		$this->woocommerce->cart->set_cart_contents_total( 100.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that unsupported payment gateways do not receive a fee.
	 */
	public function test_unsupported_gateway_adds_no_fee(): void {
		$this->set_payment_method( 'cod' );
		$this->woocommerce->cart->set_cart_contents_total( 100.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that BACS receives the standard processing fee.
	 */
	public function test_bacs_adds_one_point_five_percent_fee(): void {
		$this->set_payment_method( 'bacs' );
		$this->woocommerce->cart->set_cart_contents_total( 100.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(
				array(
					'name'    => 'Payment Processing Fee (1.50%)',
					'amount'  => 1.50,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that the PayPal card button gateway receives the standard fee.
	 */
	public function test_paypal_card_button_adds_one_point_five_percent_fee(): void {
		$this->set_payment_method( 'ppcp-card-button-gateway' );
		$this->woocommerce->cart->set_cart_contents_total( 200.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(
				array(
					'name'    => 'Payment Processing Fee (1.50%)',
					'amount'  => 3.00,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that the PayPal gateway receives the higher processing fee.
	 */
	public function test_paypal_gateway_adds_one_point_seven_five_percent_fee(): void {
		$this->set_payment_method( 'ppcp-gateway' );
		$this->woocommerce->cart->set_cart_contents_total( 100.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			array(
				array(
					'name'    => 'Payment Processing Fee (1.75%)',
					'amount'  => 1.75,
					'taxable' => false,
				),
			),
			$this->woocommerce->cart->get_added_fees()
		);
	}

	/**
	 * Tests that shipping is included in the fee base.
	 */
	public function test_shipping_is_included_in_fee_base(): void {
		$this->set_payment_method( 'bacs' );

		$this->woocommerce->cart->set_cart_contents_total( 100.00 );
		$this->woocommerce->cart->set_shipping_total( 20.00 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			1.80,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that existing fees are included in the fee base.
	 */
	public function test_existing_fees_are_included_in_fee_base(): void {
		$this->set_payment_method( 'bacs' );

		$this->woocommerce->cart->set_cart_contents_total( 100.00 );
		$this->woocommerce->cart->set_existing_fees(
			array(
				new Test_Fee(
					'Raw material import tariff',
					3.00
				),
			)
		);

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			1.55,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that taxes are included in the fee base.
	 */
	public function test_taxes_are_included_in_fee_base(): void {
		$this->set_payment_method( 'bacs' );

		$this->woocommerce->cart->set_cart_contents_total( 100.00 );
		$this->woocommerce->cart->set_taxes(
			array(
				'CA' => 8.25,
			)
		);

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			1.62,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests the complete processing fee base calculation.
	 */
	public function test_fee_base_includes_contents_shipping_existing_fees_and_taxes(): void {
		$this->set_payment_method( 'ppcp-gateway' );

		$this->woocommerce->cart->set_cart_contents_total( 100.00 );
		$this->woocommerce->cart->set_shipping_total( 15.00 );
		$this->woocommerce->cart->set_existing_fees(
			array(
				new Test_Fee(
					'Raw material import tariff',
					3.00
				),
			)
		);
		$this->woocommerce->cart->set_taxes(
			array(
				'tax-1' => 8.00,
			)
		);

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			2.21,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that the processing fee does not include itself.
	 */
	public function test_existing_processing_fee_is_excluded_from_fee_base(): void {
		$this->set_payment_method( 'bacs' );

		$this->woocommerce->cart->set_cart_contents_total( 100.00 );
		$this->woocommerce->cart->set_existing_fees(
			array(
				new Test_Fee(
					'Payment Processing Fee (1.50%)',
					1.50
				),
			)
		);

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			1.50,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Tests that the final fee is rounded to two decimal places.
	 */
	public function test_processing_fee_is_rounded_to_two_decimal_places(): void {
		$this->set_payment_method( 'bacs' );
		$this->woocommerce->cart->set_cart_contents_total( 99.99 );

		$processing_fee = new Payment_Processing_Fee();

		$processing_fee->add_processing_fee();

		$this->assertSame(
			1.50,
			$this->woocommerce->cart->get_added_fees()[0]['amount']
		);
	}

	/**
	 * Sets the chosen payment method.
	 *
	 * @param string $payment_method Payment method ID.
	 */
	private function set_payment_method(
		string $payment_method
	): void {
		$this->woocommerce->session->set(
			'chosen_payment_method',
			$payment_method
		);
	}
}
