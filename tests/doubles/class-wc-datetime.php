<?php
/**
 * WooCommerce date/time test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

use DateTimeImmutable;

/**
 * WooCommerce date/time test double.
 */
class WC_DateTime {

	/**
	 * Date/time value.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $date_time;

	/**
	 * Constructor.
	 *
	 * @param string $datetime Date/time string.
	 */
	public function __construct(
		string $datetime = 'now'
	) {
		$this->date_time = new DateTimeImmutable(
			$datetime
		);
	}

	/**
	 * Get the Unix timestamp.
	 *
	 * @return int
	 */
	public function getTimestamp(): int { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Matches WooCommerce WC_DateTime API.
		return $this->date_time->getTimestamp();
	}
}
