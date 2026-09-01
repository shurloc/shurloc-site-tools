<?php
/**
 * User phone admin column.
 *
 * Adds a Phone column to the WordPress Users table.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a customer phone number column to the WordPress Users table.
 */
final class User_Phone_Column {

	/**
	 * Phone column key.
	 *
	 * @var string
	 */
	public const PHONE_COLUMN = 'shurloc_phone';

	/**
	 * Billing phone user meta key.
	 *
	 * @var string
	 */
	private const BILLING_PHONE_META_KEY = 'billing_phone';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'manage_users_columns',
			array(
				$this,
				'add_column',
			)
		);

		add_filter(
			'manage_users_custom_column',
			array(
				$this,
				'render_column',
			),
			10,
			3
		);
	}

	/**
	 * Add the Phone column after Email.
	 *
	 * @param array<string,string> $columns Existing Users table columns.
	 * @return array<string,string>
	 */
	public function add_column(
		array $columns
	): array {

		$updated_columns = array();

		foreach ( $columns as $column_key => $column_label ) {

			$updated_columns[ $column_key ] = $column_label;

			if ( 'email' !== $column_key ) {
				continue;
			}

			$updated_columns[ self::PHONE_COLUMN ] = __(
				'Phone',
				'shurloc-site-tools'
			);
		}

		if ( ! isset( $updated_columns[ self::PHONE_COLUMN ] ) ) {
			$updated_columns[ self::PHONE_COLUMN ] = __(
				'Phone',
				'shurloc-site-tools'
			);
		}

		return $updated_columns;
	}

	/**
	 * Render the Phone column.
	 *
	 * @param string $output      Existing column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_column(
		string $output,
		string $column_name,
		int $user_id
	): string {

		if ( self::PHONE_COLUMN !== $column_name ) {
			return $output;
		}

		$phone = (string) get_user_meta(
			$user_id,
			self::BILLING_PHONE_META_KEY,
			true
		);

		$phone = trim( $phone );

		if ( '' === $phone ) {
			return '&mdash;';
		}

		$display_phone = $this->format_display_phone(
			phone: $phone,
		);
		$phone_uri     = $this->format_phone_uri(
			phone: $phone,
		);

		if ( '' === $phone_uri ) {
			return esc_html( $display_phone );
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( 'tel:' . $phone_uri ),
			esc_html( $display_phone )
		);
	}

	/**
	 * Format a phone number for display.
	 *
	 * Formats United States phone numbers consistently while preserving
	 * unrecognized or international phone numbers as originally entered.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function format_display_phone(
		string $phone
	): string {

		$phone = trim( $phone );

		$digits = preg_replace(
			'/\D+/',
			'',
			$phone
		);

		if ( null === $digits ) {
			return $phone;
		}

		if (
			11 === strlen( $digits ) &&
			'1' === $digits[0]
		) {
			$digits = substr( $digits, 1 );
		}

		if ( 10 !== strlen( $digits ) ) {
			return $phone;
		}

		return sprintf(
			'(%s) %s-%s',
			substr( $digits, 0, 3 ),
			substr( $digits, 3, 3 ),
			substr( $digits, 6, 4 )
		);
	}

	/**
	 * Format a phone number for use in a tel URI.
	 *
	 * Preserves a leading plus sign and removes all other characters that
	 * are not digits.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function format_phone_uri(
		string $phone
	): string {

		$phone = trim( $phone );

		$has_leading_plus = str_starts_with( $phone, '+' );

		$digits = preg_replace(
			'/\D+/',
			'',
			$phone
		);

		if ( null === $digits || '' === $digits ) {
			return '';
		}

		return $has_leading_plus
			? '+' . $digits
			: $digits;
	}
}
