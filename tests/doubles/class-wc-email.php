<?php
/**
 * WooCommerce email test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * Test double for WC_Email.
 */
class WC_Email {

	/**
	 * Email ID.
	 *
	 * @var string
	 */
	public string $id = '';

	/**
	 * Sets the email ID.
	 *
	 * @param string $email_id Email ID.
	 */
	public function set_id(
		string $email_id
	): void {
		$this->id = $email_id;
	}
}
