<?php
/**
 * Tests for user purchase admin filters.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;
use WP_User_Query;

/**
 * Tests the user purchase admin filters.
 */
final class UserPurchaseFiltersTest extends TestCase {

	/**
	 * Filters class under test.
	 *
	 * @var User_Purchase_Filters
	 */
	private User_Purchase_Filters $filters;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$_GET = array();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_time']            = 1_000_000;

		$this->filters = new User_Purchase_Filters();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$_GET = array();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_time']            = 0;

		parent::tearDown();
	}

	/**
	 * Verify the shared user filters action is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_user_filters_action(): void {

		$this->filters->register();

		self::assertContains(
			array(
				$this->filters,
				'render_filters',
			),
			$GLOBALS['shurloc_test_actions']
				[ User_Filters::FILTER_CONTROLS_ACTION ]
		);

		self::assertSame(
			20,
			$GLOBALS['shurloc_test_action_metadata']
				[ User_Filters::FILTER_CONTROLS_ACTION ][0]['priority']
		);
	}

	/**
	 * Verify the user query hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_pre_get_users_action(): void {

		$this->filters->register();

		self::assertContains(
			array(
				$this->filters,
				'modify_user_query',
			),
			$GLOBALS['shurloc_test_actions']['pre_get_users']
		);
	}

	/**
	 * Verify the Last Purchase filter is rendered.
	 *
	 * @return void
	 */
	public function test_last_purchase_filter_is_rendered(): void {

		ob_start();

		$this->filters->render_filters();

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertStringContainsString(
			'name="shurloc_last_purchase"',
			$output
		);

		self::assertStringContainsString(
			'Purchased Within 1 Day',
			$output
		);

		self::assertStringContainsString(
			'Purchased Within 7 Days',
			$output
		);

		self::assertStringContainsString(
			'Purchased Within 30 Days',
			$output
		);

		self::assertStringContainsString(
			'Not Purchased Within 1 Day',
			$output
		);

		self::assertStringContainsString(
			'Not Purchased Within 7 Days',
			$output
		);

		self::assertStringContainsString(
			'Not Purchased Within 30 Days',
			$output
		);

		self::assertStringContainsString(
			'Never Purchased',
			$output
		);
	}

	/**
	 * Verify the Last Order Status filter is not rendered.
	 *
	 * @return void
	 */
	public function test_last_order_status_filter_is_not_rendered(): void {

		ob_start();

		$this->filters->render_filters();

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertStringNotContainsString(
			'name="shurloc_last_order_status"',
			$output
		);
	}

	/**
	 * Verify a selected Last Purchase filter is preserved.
	 *
	 * @return void
	 */
	public function test_selected_last_purchase_filter_is_preserved(): void {

		$_GET['shurloc_last_purchase'] = 'not-30';

		ob_start();

		$this->filters->render_filters();

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertMatchesRegularExpression(
			'/value="not-30"\s+selected="selected"/',
			$output
		);
	}

	/**
	 * Verify Last Purchase sorting uses numeric user meta.
	 *
	 * @return void
	 */
	public function test_last_purchase_sorting_uses_numeric_meta(): void {

		$query = new WP_User_Query(
			array(
				'orderby' => User_Purchase_Service::LAST_PURCHASE_META_KEY,
			)
		);

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			$query->get( 'meta_key' )
		);

		self::assertSame(
			'meta_value_num',
			$query->get( 'orderby' )
		);
	}

	/**
	 * Verify unrelated sorting is unchanged.
	 *
	 * @return void
	 */
	public function test_unrelated_sorting_is_unchanged(): void {

		$query = new WP_User_Query(
			array(
				'orderby' => 'login',
			)
		);

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			'login',
			$query->get( 'orderby' )
		);

		self::assertNull(
			$query->get( 'meta_key' )
		);
	}

	/**
	 * Verify one-day purchase filtering.
	 *
	 * @return void
	 */
	public function test_one_day_purchase_filter_adds_meta_query(): void {

		$_GET['shurloc_last_purchase'] = '1';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'value'   => 913600,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify seven-day purchase filtering.
	 *
	 * @return void
	 */
	public function test_seven_day_purchase_filter_adds_meta_query(): void {

		$_GET['shurloc_last_purchase'] = '7';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'value'   => 395200,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify thirty-day purchase filtering.
	 *
	 * @return void
	 */
	public function test_thirty_day_purchase_filter_adds_meta_query(): void {

		$GLOBALS['shurloc_test_time'] = 3_000_000;

		$_GET['shurloc_last_purchase'] = '30';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		$meta_query = $query->get( 'meta_query' );

		self::assertIsArray( $meta_query );

		self::assertSame(
			408000,
			$meta_query[0]['value']
		);

		self::assertSame(
			'>=',
			$meta_query[0]['compare']
		);
	}

	/**
	 * Verify one-day not-purchased filtering.
	 *
	 * @return void
	 */
	public function test_not_one_day_purchase_filter_adds_meta_query(): void {

		$_GET['shurloc_last_purchase'] = 'not-1';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_META_KEY,
					'value'   => 913600,
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify seven-day not-purchased filtering.
	 *
	 * @return void
	 */
	public function test_not_seven_day_purchase_filter_adds_meta_query(): void {

		$_GET['shurloc_last_purchase'] = 'not-7';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		$meta_query = $query->get( 'meta_query' );

		self::assertIsArray( $meta_query );

		self::assertSame(
			'<',
			$meta_query[0]['compare']
		);

		self::assertSame(
			395200,
			$meta_query[0]['value']
		);
	}

	/**
	 * Verify thirty-day not-purchased filtering.
	 *
	 * @return void
	 */
	public function test_not_thirty_day_purchase_filter_adds_meta_query(): void {

		$GLOBALS['shurloc_test_time'] = 3_000_000;

		$_GET['shurloc_last_purchase'] = 'not-30';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		$meta_query = $query->get( 'meta_query' );

		self::assertIsArray( $meta_query );

		self::assertSame(
			408000,
			$meta_query[0]['value']
		);

		self::assertSame(
			'<',
			$meta_query[0]['compare']
		);
	}

	/**
	 * Verify Never Purchased matches missing, zero, and empty metadata.
	 *
	 * @return void
	 */
	public function test_never_purchased_filter_is_defensive(): void {

		$_GET['shurloc_last_purchase'] = 'never';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
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
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify order status filtering.
	 *
	 * @return void
	 */
	public function test_order_status_filter_adds_meta_query(): void {

		$_GET['shurloc_last_order_status'] = 'completed';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
					'key'     => User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY,
					'value'   => 'completed',
					'compare' => '=',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify purchase and order status filters are combined with AND.
	 *
	 * @return void
	 */
	public function test_purchase_and_status_filters_are_combined(): void {

		$_GET['shurloc_last_purchase']     = '30';
		$_GET['shurloc_last_order_status'] = 'processing';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		$meta_query = $query->get( 'meta_query' );

		self::assertIsArray( $meta_query );

		self::assertSame(
			'AND',
			$meta_query['relation']
		);

		self::assertCount(
			3,
			$meta_query
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			$meta_query[0]['key']
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY,
			$meta_query[1]['key']
		);
	}

	/**
	 * Verify existing meta query clauses are preserved.
	 *
	 * @return void
	 */
	public function test_existing_meta_query_is_preserved(): void {

		$_GET['shurloc_last_purchase'] = '7';

		$query = new WP_User_Query(
			array(
				'meta_query' => array(
					array(
						'key'     => 'existing_key',
						'value'   => 'existing_value',
						'compare' => '=',
					),
				),
			)
		);

		$this->filters->modify_user_query(
			query: $query,
		);

		$meta_query = $query->get( 'meta_query' );

		self::assertIsArray( $meta_query );

		self::assertSame(
			'existing_key',
			$meta_query[0]['key']
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			$meta_query[1]['key']
		);

		self::assertSame(
			'AND',
			$meta_query['relation']
		);
	}

	/**
	 * Verify an invalid purchase filter is ignored.
	 *
	 * @return void
	 */
	public function test_invalid_purchase_filter_is_ignored(): void {

		$_GET['shurloc_last_purchase'] = 'invalid';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertNull(
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify an invalid order status filter is ignored.
	 *
	 * @return void
	 */
	public function test_invalid_order_status_filter_is_ignored(): void {

		$_GET['shurloc_last_order_status'] = 'not-a-status';

		$query = new WP_User_Query();

		$this->filters->modify_user_query(
			query: $query,
		);

		self::assertNull(
			$query->get( 'meta_query' )
		);
	}
}
