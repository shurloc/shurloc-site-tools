<?php
/**
 * User activity admin columns.
 *
 * Adds Last Activity information to the WordPress Users table.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Services\User_Activity_Service;

/**
 * Adds user activity columns to the WordPress Users table.
 */
final class User_Activity_Columns {

	/**
	 * Last Login column key.
	 *
	 * @var string
	 */
	public const LAST_LOGIN_COLUMN = 'shurloc_last_login';

	/**
	 * Last Activity column key.
	 *
	 * @var string
	 */
	public const LAST_ACTIVITY_COLUMN = 'shurloc_last_activity';

	/**
	 * Relative time formatter.
	 *
	 * @var Relative_Time_Formatter
	 */
	private Relative_Time_Formatter $time_formatter;

	/**
	 * Constructor.
	 *
	 * @param Relative_Time_Formatter $time_formatter Relative time formatter.
	 */
	public function __construct(
		Relative_Time_Formatter $time_formatter
	) {

		$this->time_formatter = $time_formatter;
	}

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
				'add_columns',
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

		add_filter(
			'manage_users_sortable_columns',
			array(
				$this,
				'register_sortable_columns',
			)
		);
	}

	/**
	 * Add activity columns to the Users table.
	 *
	 * @param array<string,string> $columns Existing Users table columns.
	 * @return array<string,string>
	 */
	public function add_columns(
		array $columns
	): array {

		$columns[ self::LAST_ACTIVITY_COLUMN ] = __(
			'Last Activity',
			'shurloc-site-tools'
		);

		return $columns;
	}

	/**
	 * Render a custom Users table column.
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

		switch ( $column_name ) {

			case self::LAST_ACTIVITY_COLUMN:
				return $this->render_timestamp_column(
					user_id: $user_id,
					meta_key: User_Activity_Service::LAST_ACTIVITY_META_KEY,
					empty_label: __(
						'Never Active',
						'shurloc-site-tools'
					),
				);

			default:
				return $output;
		}
	}

	/**
	 * Register activity columns as sortable.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string>
	 */
	public function register_sortable_columns(
		array $columns
	): array {

		$columns[ self::LAST_ACTIVITY_COLUMN ] =
			User_Activity_Service::LAST_ACTIVITY_META_KEY;

		return $columns;
	}

	/**
	 * Render a timestamp-backed user column.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $meta_key    User meta key.
	 * @param string $empty_label Label when no timestamp is stored.
	 * @return string
	 */
	private function render_timestamp_column(
		int $user_id,
		string $meta_key,
		string $empty_label
	): string {

		$timestamp = (int) get_user_meta(
			$user_id,
			$meta_key,
			true
		);

		if ( 0 >= $timestamp ) {
			return esc_html( $empty_label );
		}

		return esc_html(
			$this->time_formatter->format(
				timestamp: $timestamp,
			)
		);
	}
}
