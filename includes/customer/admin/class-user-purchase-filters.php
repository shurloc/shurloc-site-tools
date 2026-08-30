<?php
/**
 * User purchase admin filters.
 *
 * Adds Last Purchase and Last Order Status filters to the WordPress Users
 * screen and applies purchase filtering and sorting to user queries.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;
use WP_User_Query;

/**
 * Adds user purchase filters and sorting to the WordPress Users screen.
 */
final class User_Purchase_Filters {

	/**
	 * Last Purchase filter request key.
	 *
	 * @var string
	 */
	private const LAST_PURCHASE_FILTER = 'shurloc_last_purchase';

	/**
	 * Last Order Status filter request key.
	 *
	 * @var string
	 */
	private const LAST_ORDER_STATUS_FILTER = 'shurloc_last_order_status';

	/**
	 * One-day filter value.
	 *
	 * @var string
	 */
	private const FILTER_ONE_DAY = '1';

	/**
	 * Seven-day filter value.
	 *
	 * @var string
	 */
	private const FILTER_SEVEN_DAYS = '7';

	/**
	 * Thirty-day filter value.
	 *
	 * @var string
	 */
	private const FILTER_THIRTY_DAYS = '30';

	/**
	 * One-day exclusion filter value.
	 *
	 * @var string
	 */
	private const FILTER_NOT_ONE_DAY = 'not-1';

	/**
	 * Seven-day exclusion filter value.
	 *
	 * @var string
	 */
	private const FILTER_NOT_SEVEN_DAYS = 'not-7';

	/**
	 * Thirty-day exclusion filter value.
	 *
	 * @var string
	 */
	private const FILTER_NOT_THIRTY_DAYS = 'not-30';

	/**
	 * Never filter value.
	 *
	 * @var string
	 */
	private const FILTER_NEVER = 'never';

	/**
	 * Number of seconds in one day.
	 *
	 * @var int
	 */
	private const DAY_IN_SECONDS = 86400;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			User_Filters::FILTER_CONTROLS_ACTION,
			array(
				$this,
				'render_filters',
			),
			20
		);

		add_action(
			'pre_get_users',
			array(
				$this,
				'modify_user_query',
			)
		);
	}

	/**
	 * Render purchase filter controls.
	 *
	 * The shared User_Filters coordinator owns the surrounding toolbar
	 * container and Filter button.
	 *
	 * @return void
	 */
	public function render_filters(): void {

		$last_purchase_filter = $this->get_purchase_filter();
		?>

		<label
			class="screen-reader-text"
			for="<?php echo esc_attr( self::LAST_PURCHASE_FILTER ); ?>"
		>
			<?php
			echo esc_html__(
				'Filter by last purchase',
				'shurloc-site-tools'
			);
			?>
		</label>

		<select
			name="<?php echo esc_attr( self::LAST_PURCHASE_FILTER ); ?>"
			id="<?php echo esc_attr( self::LAST_PURCHASE_FILTER ); ?>"
		>
			<option value="">
				<?php
				echo esc_html__(
					'All Last Purchases',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_ONE_DAY ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_ONE_DAY ); ?>
			>
				<?php
				echo esc_html__(
					'Purchased Within 1 Day',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_SEVEN_DAYS ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_SEVEN_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Purchased Within 7 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_THIRTY_DAYS ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_THIRTY_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Purchased Within 30 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_NOT_ONE_DAY ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_NOT_ONE_DAY ); ?>
			>
				<?php
				echo esc_html__(
					'Not Purchased Within 1 Day',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_NOT_SEVEN_DAYS ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_NOT_SEVEN_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Not Purchased Within 7 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_NOT_THIRTY_DAYS ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_NOT_THIRTY_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Not Purchased Within 30 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_NEVER ); ?>"
				<?php selected( $last_purchase_filter, self::FILTER_NEVER ); ?>
			>
				<?php
				echo esc_html__(
					'Never Purchased',
					'shurloc-site-tools'
				);
				?>
			</option>
		</select>

		<?php
	}

	/**
	 * Modify the Users query for purchase filtering and sorting.
	 *
	 * @param WP_User_Query $query User query.
	 * @return void
	 */
	public function modify_user_query(
		WP_User_Query $query
	): void {

		$this->apply_sorting(
			query: $query,
		);

		$this->apply_filters(
			query: $query,
		);
	}

	/**
	 * Apply Last Purchase sorting to a Users query.
	 *
	 * @param WP_User_Query $query User query.
	 * @return void
	 */
	private function apply_sorting(
		WP_User_Query $query
	): void {

		$orderby = $query->get( 'orderby' );

		if (
			User_Purchase_Service::LAST_PURCHASE_META_KEY !== $orderby
		) {
			return;
		}

		$query->set(
			'meta_key',
			User_Purchase_Service::LAST_PURCHASE_META_KEY
		);

		$query->set(
			'orderby',
			'meta_value_num'
		);
	}

	/**
	 * Apply purchase filters to a Users query.
	 *
	 * @param WP_User_Query $query User query.
	 * @return void
	 */
	private function apply_filters(
		WP_User_Query $query
	): void {

		$meta_query = $query->get( 'meta_query' );

		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$last_purchase_clause = $this->build_purchase_meta_query_clause(
			filter: $this->get_purchase_filter(),
		);

		if ( null !== $last_purchase_clause ) {
			$meta_query[] = $last_purchase_clause;
		}

		$last_order_status_filter = $this->get_order_status_filter();

		if ( '' !== $last_order_status_filter ) {
			$meta_query[] = array(
				'key'     => User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY,
				'value'   => $last_order_status_filter,
				'compare' => '=',
			);
		}

		if ( empty( $meta_query ) ) {
			return;
		}

		if ( 1 < count( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
		}

		$query->set(
			'meta_query',
			$meta_query
		);
	}

	/**
	 * Build a Last Purchase meta query clause.
	 *
	 * @param string $filter Selected purchase filter.
	 * @return array<string|int,mixed>|null
	 */
	private function build_purchase_meta_query_clause(
		string $filter
	): ?array {

		if ( self::FILTER_NEVER === $filter ) {

			/**
			 * Never-purchased meta query clause.
			 *
			 * @var array<string|int,mixed> $clause
			 */
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'value'   => '',
					'compare' => '=',
				),
			);

			return $clause;
		}

		$filter_parameters = $this->get_purchase_filter_parameters(
			filter: $filter,
		);

		if ( null === $filter_parameters ) {
			return null;
		}

		$minimum_timestamp = time()
			- ( $filter_parameters['days'] * self::DAY_IN_SECONDS );

		if ( $filter_parameters['exclude_recent'] ) {
			return array(
				'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
				'value'   => $minimum_timestamp,
				'compare' => '<',
				'type'    => 'NUMERIC',
			);
		}

		return array(
			'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
			'value'   => $minimum_timestamp,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
	}

	/**
	 * Get purchase filter parameters.
	 *
	 * @param string $filter Selected purchase filter.
	 * @return array{days:int,exclude_recent:bool}|null
	 */
	private function get_purchase_filter_parameters(
		string $filter
	): ?array {

		switch ( $filter ) {

			case self::FILTER_ONE_DAY:
				return array(
					'days'           => 1,
					'exclude_recent' => false,
				);

			case self::FILTER_SEVEN_DAYS:
				return array(
					'days'           => 7,
					'exclude_recent' => false,
				);

			case self::FILTER_THIRTY_DAYS:
				return array(
					'days'           => 30,
					'exclude_recent' => false,
				);

			case self::FILTER_NOT_ONE_DAY:
				return array(
					'days'           => 1,
					'exclude_recent' => true,
				);

			case self::FILTER_NOT_SEVEN_DAYS:
				return array(
					'days'           => 7,
					'exclude_recent' => true,
				);

			case self::FILTER_NOT_THIRTY_DAYS:
				return array(
					'days'           => 30,
					'exclude_recent' => true,
				);

			default:
				return null;
		}
	}

	/**
	 * Get and validate the Last Purchase filter from the request.
	 *
	 * This request value only controls filtering of the Users list and does not
	 * perform a state-changing action, so nonce verification is not required.
	 *
	 * @return string
	 */
	private function get_purchase_filter(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
		if ( ! isset( $_GET[ self::LAST_PURCHASE_FILTER ] ) ) {
			return '';
		}

		$request_value = wp_unslash(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
			$_GET[ self::LAST_PURCHASE_FILTER ]
		);

		$filter = sanitize_key( $request_value );

		if (
			! in_array(
				$filter,
				array(
					self::FILTER_ONE_DAY,
					self::FILTER_SEVEN_DAYS,
					self::FILTER_THIRTY_DAYS,
					self::FILTER_NOT_ONE_DAY,
					self::FILTER_NOT_SEVEN_DAYS,
					self::FILTER_NOT_THIRTY_DAYS,
					self::FILTER_NEVER,
				),
				true
			)
		) {
			return '';
		}

		return $filter;
	}

	/**
	 * Get and validate the Last Order Status filter from the request.
	 *
	 * This request value only controls filtering of the Users list and does not
	 * perform a state-changing action, so nonce verification is not required.
	 *
	 * @return string
	 */
	private function get_order_status_filter(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
		if ( ! isset( $_GET[ self::LAST_ORDER_STATUS_FILTER ] ) ) {
			return '';
		}

		$request_value = wp_unslash(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
			$_GET[ self::LAST_ORDER_STATUS_FILTER ]
		);

		$status = sanitize_key( $request_value );

		if (
			! array_key_exists(
				$status,
				$this->get_order_statuses()
			)
		) {
			return '';
		}

		return $status;
	}

	/**
	 * Get available WooCommerce order statuses.
	 *
	 * WooCommerce status keys normally include the "wc-" prefix. The stored
	 * order status returned by WC_Order::get_status() does not, so normalize
	 * the keys before validating the filter.
	 *
	 * @return array<string,string>
	 */
	private function get_order_statuses(): array {

		$statuses = array();

		foreach ( wc_get_order_statuses() as $status => $label ) {

			if ( str_starts_with( $status, 'wc-' ) ) {
				$status = substr( $status, 3 );
			}

			$statuses[ $status ] = $label;
		}

		return $statuses;
	}
}
