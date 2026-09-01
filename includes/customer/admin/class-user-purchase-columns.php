<?php
/**
 * User purchase admin columns.
 *
 * Adds a Last Purchase column to the WordPress Users table.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;

/**
 * Adds purchase information to the WordPress Users table.
 */
final class User_Purchase_Columns {

	/**
	 * Last Purchase column key.
	 *
	 * @var string
	 */
	public const LAST_PURCHASE_COLUMN = 'shurloc_last_purchase';

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
	 * Add the Last Purchase column to the Users table.
	 *
	 * @param array<string,string> $columns Existing Users table columns.
	 * @return array<string,string>
	 */
	public function add_columns(
		array $columns
	): array {

		$columns[ self::LAST_PURCHASE_COLUMN ] = __(
			'Last Purchase',
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

		if ( self::LAST_PURCHASE_COLUMN !== $column_name ) {
			return $output;
		}

		$timestamp = (int) get_user_meta(
			$user_id,
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			true
		);

		if ( 0 >= $timestamp ) {
			return esc_html__(
				'Never',
				'shurloc-site-tools'
			);
		}

		$order_id = (int) get_user_meta(
			$user_id,
			User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY,
			true
		);

		$status = (string) get_user_meta(
			$user_id,
			User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY,
			true
		);

		$total = (float) get_user_meta(
			$user_id,
			User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY,
			true
		);

		return $this->render_purchase(
			timestamp: $timestamp,
			order_id: $order_id,
			status: $status,
			total: $total,
		);
	}

	/**
	 * Register the Last Purchase column as sortable.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string>
	 */
	public function register_sortable_columns(
		array $columns
	): array {

		$columns[ self::LAST_PURCHASE_COLUMN ] =
			User_Purchase_Service::LAST_PURCHASE_META_KEY;

		return $columns;
	}

	/**
	 * Render the Last Purchase column value.
	 *
	 * @param int    $timestamp Purchase timestamp.
	 * @param int    $order_id  Order ID.
	 * @param string $status    Order status.
	 * @param float  $total     Order total.
	 * @return string
	 */
	private function render_purchase(
		int $timestamp,
		int $order_id,
		string $status,
		float $total
	): string {

		$time_text = $this->time_formatter->format(
			timestamp: $timestamp,
		);

		$status_text = $this->format_status(
			status: $status,
		);

		$total_text = $this->format_total(
			total: $total,
		);

		if ( 0 >= $order_id ) {
			return sprintf(
				'%s — %s',
				esc_html( $time_text ),
				esc_html( $total_text )
			);
		}

		$order_url = $this->get_order_url(
			order_id: $order_id,
		);

		return sprintf(
			'%1$s (<a href="%2$s">#%3$d</a> – %4$s) — %5$s',
			esc_html( $time_text ),
			esc_url( $order_url ),
			$order_id,
			esc_html( $status_text ),
			esc_html( $total_text )
		);
	}

	/**
	 * Format an order status for display.
	 *
	 * @param string $status Order status.
	 * @return string
	 */
	private function format_status(
		string $status
	): string {

		return wc_get_order_status_name( $status );
	}

	/**
	 * Format an order total for display.
	 *
	 * @param float $total Order total.
	 * @return string
	 */
	private function format_total(
		float $total
	): string {

		return wp_strip_all_tags(
			wc_price( $total )
		);
	}

	/**
	 * Get the WooCommerce order editor URL.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function get_order_url(
		int $order_id
	): string {

		return add_query_arg(
			array(
				'action' => 'edit',
				'post'   => $order_id,
			),
			admin_url( 'post.php' )
		);
	}
}
