<?php
/**
 * WooCommerce session test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout;

/**
 * Test double for a WooCommerce session.
 */
final class Test_Session {

	/**
	 * Session data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Gets a session value.
	 *
	 * @param string $key     Session key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get(
		string $key,
		mixed $default_value = null
	): mixed {
		return $this->data[ $key ] ?? $default_value;
	}

	/**
	 * Sets a session value.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 */
	public function set(
		string $key,
		mixed $value
	): void {
		$this->data[ $key ] = $value;
	}
}
