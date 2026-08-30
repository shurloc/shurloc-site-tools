<?php
/**
 * User activity admin filters.
 *
 * Adds Last Activity filters to the WordPress Users screen and applies
 * activity filtering and sorting to user queries.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Services\User_Activity_Service;
use WP_User_Query;

/**
 * Adds user activity filters and sorting to the WordPress Users screen.
 */
final class User_Activity_Filters {

	/**
	 * Last Login filter request key.
	 *
	 * @var string
	 */
	private const LAST_LOGIN_FILTER = 'shurloc_last_login';

	/**
	 * Last Activity filter request key.
	 *
	 * @var string
	 */
	private const LAST_ACTIVITY_FILTER = 'shurloc_last_activity';

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
			10
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
	 * Render activity filter controls.
	 *
	 * The shared User_Filters coordinator owns the surrounding toolbar
	 * container and Filter button.
	 *
	 * @return void
	 */
	public function render_filters(): void {

		$last_activity_filter = $this->get_request_filter(
			request_key: self::LAST_ACTIVITY_FILTER,
		);
		?>

		<label
			class="screen-reader-text"
			for="<?php echo esc_attr( self::LAST_ACTIVITY_FILTER ); ?>"
		>
			<?php
			echo esc_html__(
				'Filter by last activity',
				'shurloc-site-tools'
			);
			?>
		</label>

		<select
			name="<?php echo esc_attr( self::LAST_ACTIVITY_FILTER ); ?>"
			id="<?php echo esc_attr( self::LAST_ACTIVITY_FILTER ); ?>"
		>
			<option value="">
				<?php
				echo esc_html__(
					'All Last Activity',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_ONE_DAY ); ?>"
				<?php selected( $last_activity_filter, self::FILTER_ONE_DAY ); ?>
			>
				<?php
				echo esc_html__(
					'Active Within 1 Day',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_SEVEN_DAYS ); ?>"
				<?php selected( $last_activity_filter, self::FILTER_SEVEN_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Active Within 7 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_THIRTY_DAYS ); ?>"
				<?php selected( $last_activity_filter, self::FILTER_THIRTY_DAYS ); ?>
			>
				<?php
				echo esc_html__(
					'Active Within 30 Days',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_NEVER ); ?>"
				<?php selected( $last_activity_filter, self::FILTER_NEVER ); ?>
			>
				<?php
				echo esc_html__(
					'Never Active',
					'shurloc-site-tools'
				);
				?>
			</option>
		</select>

		<?php
	}

	/**
	 * Modify the Users query for activity filtering and sorting.
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
	 * Apply activity sorting to a Users query.
	 *
	 * @param WP_User_Query $query User query.
	 * @return void
	 */
	private function apply_sorting(
		WP_User_Query $query
	): void {

		$orderby = $query->get( 'orderby' );

		if (
			User_Activity_Service::LAST_LOGIN_META_KEY !== $orderby &&
			User_Activity_Service::LAST_ACTIVITY_META_KEY !== $orderby
		) {
			return;
		}

		$query->set(
			'meta_key',
			$orderby
		);

		$query->set(
			'orderby',
			'meta_value_num'
		);
	}

	/**
	 * Apply Last Login and Last Activity filters to a Users query.
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

		$last_login_filter = $this->get_request_filter(
			request_key: self::LAST_LOGIN_FILTER,
		);

		$last_activity_filter = $this->get_request_filter(
			request_key: self::LAST_ACTIVITY_FILTER,
		);

		$last_login_clause = $this->build_meta_query_clause(
			meta_key: User_Activity_Service::LAST_LOGIN_META_KEY,
			filter: $last_login_filter,
		);

		if ( null !== $last_login_clause ) {
			$meta_query[] = $last_login_clause;
		}

		$last_activity_clause = $this->build_meta_query_clause(
			meta_key: User_Activity_Service::LAST_ACTIVITY_META_KEY,
			filter: $last_activity_filter,
		);

		if ( null !== $last_activity_clause ) {
			$meta_query[] = $last_activity_clause;
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
	 * Build a user meta query clause for an activity filter.
	 *
	 * @param string $meta_key User meta key.
	 * @param string $filter   Selected filter.
	 * @return array<string|int,mixed>|null
	 */
	private function build_meta_query_clause(
		string $meta_key,
		string $filter
	): ?array {

		if ( self::FILTER_NEVER === $filter ) {

			/**
			 * Never-activity meta query clause.
			 *
			 * @var array<string|int,mixed> $clause
			 */
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $meta_key,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => $meta_key,
					'value'   => '',
					'compare' => '=',
				),
			);

			return $clause;
		}

		$days = $this->get_filter_days(
			filter: $filter,
		);

		if ( null === $days ) {
			return null;
		}

		$minimum_timestamp = time()
			- ( $days * self::DAY_IN_SECONDS );

		return array(
			'key'     => $meta_key,
			'value'   => $minimum_timestamp,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
	}

	/**
	 * Get the number of days represented by a filter.
	 *
	 * @param string $filter Selected filter.
	 * @return int|null
	 */
	private function get_filter_days(
		string $filter
	): ?int {

		switch ( $filter ) {

			case self::FILTER_ONE_DAY:
				return 1;

			case self::FILTER_SEVEN_DAYS:
				return 7;

			case self::FILTER_THIRTY_DAYS:
				return 30;

			default:
				return null;
		}
	}

	/**
	 * Get and validate an activity filter from the request.
	 *
	 * This request value only controls filtering of the Users list and does not
	 * perform a state-changing action, so nonce verification is not required.
	 *
	 * @param string $request_key Request parameter name.
	 * @return string
	 */
	private function get_request_filter(
		string $request_key
	): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
		if ( ! isset( $_GET[ $request_key ] ) ) {
			return '';
		}

		$request_value = wp_unslash(
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Users screen filter.
			$_GET[ $request_key ]
		);

		$filter = sanitize_key( $request_value );

		if (
			! in_array(
				$filter,
				array(
					self::FILTER_ONE_DAY,
					self::FILTER_SEVEN_DAYS,
					self::FILTER_THIRTY_DAYS,
					self::FILTER_NEVER,
				),
				true
			)
		) {
			return '';
		}

		return $filter;
	}
}
