<?php
/**
 * User admin columns.
 *
 * Customizes columns displayed on the WordPress Users screen.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Customizes WordPress Users table columns.
 */
final class User_Columns {

	/**
	 * Jetpack WordPress.com account column key.
	 *
	 * @var string
	 */
	private const JETPACK_ACCOUNT_COLUMN = 'user_jetpack';

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
				'remove_unused_columns',
			),
			100
		);
	}

	/**
	 * Remove unused columns from the Users table.
	 *
	 * @param array<string,string> $columns Existing Users table columns.
	 * @return array<string,string>
	 */
	public function remove_unused_columns(
		array $columns
	): array {

		unset(
			$columns[ self::JETPACK_ACCOUNT_COLUMN ]
		);

		return $columns;
	}
}
