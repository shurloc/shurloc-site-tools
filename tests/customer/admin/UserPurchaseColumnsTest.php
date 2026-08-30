<?php
/**
 * Tests for user purchase admin columns.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Services\User_Purchase_Service;

/**
 * Tests the user purchase admin columns.
 */
final class UserPurchaseColumnsTest extends TestCase {

	/**
	 * Columns class under test.
	 *
	 * @var User_Purchase_Columns
	 */
	private User_Purchase_Columns $columns;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_user_meta']       = array();
		$GLOBALS['shurloc_test_time']            = 1_000_000;

		$time_formatter = new Relative_Time_Formatter();

		$this->columns = new User_Purchase_Columns(
			time_formatter: $time_formatter,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_user_meta']       = array();
		$GLOBALS['shurloc_test_time']            = 0;

		parent::tearDown();
	}

	/**
	 * Verify the Users columns filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_users_columns_filter(): void {

		$this->columns->register();

		self::assertContains(
			array(
				$this->columns,
				'add_columns',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_columns']
		);
	}

	/**
	 * Verify the custom column rendering filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_custom_column_filter(): void {

		$this->columns->register();

		self::assertContains(
			array(
				$this->columns,
				'render_column',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_custom_column']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_filter_metadata']
				['manage_users_custom_column'][0]['priority']
		);

		self::assertSame(
			3,
			$GLOBALS['shurloc_test_filter_metadata']
				['manage_users_custom_column'][0]['accepted_args']
		);
	}

	/**
	 * Verify the sortable columns filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_sortable_columns_filter(): void {

		$this->columns->register();

		self::assertContains(
			array(
				$this->columns,
				'register_sortable_columns',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_sortable_columns']
		);
	}

	/**
	 * Verify the Last Purchase column is added.
	 *
	 * @return void
	 */
	public function test_add_columns_adds_last_purchase_column(): void {

		$result = $this->columns->add_columns(
			columns: array(
				'username' => 'Username',
				'email'    => 'Email',
			),
		);

		self::assertSame(
			array(
				'username'              => 'Username',
				'email'                 => 'Email',
				'shurloc_last_purchase' => 'Last Purchase',
			),
			$result
		);
	}

	/**
	 * Verify unrelated columns preserve their existing output.
	 *
	 * @return void
	 */
	public function test_unrelated_column_preserves_existing_output(): void {

		$result = $this->columns->render_column(
			output: 'Existing output',
			column_name: 'email',
			user_id: 101,
		);

		self::assertSame(
			'Existing output',
			$result
		);
	}

	/**
	 * Verify a user with no purchase displays Never.
	 *
	 * @return void
	 */
	public function test_missing_purchase_timestamp_renders_never(): void {

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'Never',
			$result
		);
	}

	/**
	 * Verify a zero purchase timestamp displays Never.
	 *
	 * @return void
	 */
	public function test_zero_purchase_timestamp_renders_never(): void {

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Purchase_Service::LAST_PURCHASE_META_KEY ] = 0;

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'Never',
			$result
		);
	}

	/**
	 * Verify the purchase time is formatted.
	 *
	 * @return void
	 */
	public function test_last_purchase_renders_formatted_time(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 3600,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'1 hour ago',
			$result
		);
	}

	/**
	 * Verify the order number is rendered.
	 *
	 * @return void
	 */
	public function test_last_purchase_renders_order_number(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 3600,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'#200',
			$result
		);
	}

	/**
	 * Verify the order number links to the order editor.
	 *
	 * @return void
	 */
	public function test_last_purchase_links_to_order_editor(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 3600,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'post=200',
			$result
		);

		self::assertStringContainsString(
			'action=edit',
			$result
		);
	}

	/**
	 * Verify the human-readable order status is rendered.
	 *
	 * @return void
	 */
	public function test_last_purchase_renders_order_status(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 3600,
			status: 'on-hold',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'On Hold',
			$result
		);
	}

	/**
	 * Verify the formatted order total is rendered.
	 *
	 * @return void
	 */
	public function test_last_purchase_renders_order_total(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 3600,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'$125.50',
			$result
		);
	}

	/**
	 * Verify the complete purchase output is rendered.
	 *
	 * @return void
	 */
	public function test_last_purchase_renders_composite_value(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 200,
			timestamp: 1_000_000 - 86400,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'Yesterday',
			$result
		);

		self::assertStringContainsString(
			'#200',
			$result
		);

		self::assertStringContainsString(
			'Completed',
			$result
		);

		self::assertStringContainsString(
			'$125.50',
			$result
		);
	}

	/**
	 * Verify a missing order ID still renders purchase time and total.
	 *
	 * @return void
	 */
	public function test_missing_order_id_renders_purchase_without_link(): void {

		$this->seed_purchase_meta(
			user_id: 101,
			order_id: 0,
			timestamp: 1_000_000 - 3600,
			status: 'completed',
			total: 125.50,
		);

		$result = $this->columns->render_column(
			output: '',
			column_name: User_Purchase_Columns::LAST_PURCHASE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'1 hour ago',
			$result
		);

		self::assertStringContainsString(
			'$125.50',
			$result
		);

		self::assertStringNotContainsString(
			'<a ',
			$result
		);
	}

	/**
	 * Verify Last Purchase is sortable by purchase timestamp.
	 *
	 * @return void
	 */
	public function test_last_purchase_column_is_sortable(): void {

		$result = $this->columns->register_sortable_columns(
			columns: array(),
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			$result[ User_Purchase_Columns::LAST_PURCHASE_COLUMN ]
		);
	}

	/**
	 * Verify existing sortable columns are preserved.
	 *
	 * @return void
	 */
	public function test_sortable_columns_preserve_existing_columns(): void {

		$result = $this->columns->register_sortable_columns(
			columns: array(
				'username' => 'login',
			),
		);

		self::assertSame(
			'login',
			$result['username']
		);

		self::assertSame(
			User_Purchase_Service::LAST_PURCHASE_META_KEY,
			$result[ User_Purchase_Columns::LAST_PURCHASE_COLUMN ]
		);
	}

	/**
	 * Seed purchase metadata for a test user.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $order_id  Order ID.
	 * @param int    $timestamp Purchase timestamp.
	 * @param string $status    Order status.
	 * @param float  $total     Order total.
	 * @return void
	 */
	private function seed_purchase_meta(
		int $user_id,
		int $order_id,
		int $timestamp,
		string $status,
		float $total
	): void {

		$GLOBALS['shurloc_test_user_meta'][ $user_id ] = array(
			User_Purchase_Service::LAST_PURCHASE_META_KEY =>
				$timestamp,
			User_Purchase_Service::LAST_PURCHASE_ORDER_META_KEY =>
				$order_id,
			User_Purchase_Service::LAST_PURCHASE_STATUS_META_KEY =>
				$status,
			User_Purchase_Service::LAST_PURCHASE_TOTAL_META_KEY =>
				$total,
		);
	}
}
