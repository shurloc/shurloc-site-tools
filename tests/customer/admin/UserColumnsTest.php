<?php
/**
 * Tests for user admin columns.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests the user admin columns.
 */
final class UserColumnsTest extends TestCase {

	/**
	 * User columns class under test.
	 *
	 * @var User_Columns
	 */
	private User_Columns $user_columns;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		$this->user_columns = new User_Columns();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the Users columns filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_manage_users_columns_filter(): void {

		$this->user_columns->register();

		self::assertContains(
			array(
				$this->user_columns,
				'remove_unused_columns',
			),
			$GLOBALS['shurloc_test_filters']['manage_users_columns']
		);
	}

	/**
	 * Verify the Users columns filter uses the expected priority.
	 *
	 * @return void
	 */
	public function test_register_uses_late_priority(): void {

		$this->user_columns->register();

		self::assertSame(
			100,
			$GLOBALS['shurloc_test_filter_metadata']
				['manage_users_columns'][0]['priority']
		);
	}

	/**
	 * Verify the Jetpack WordPress.com account column is removed.
	 *
	 * @return void
	 */
	public function test_jetpack_account_column_is_removed(): void {

		$result = $this->user_columns->remove_unused_columns(
			columns: array(
				'username'     => 'Username',
				'email'        => 'Email',
				'user_jetpack' => 'WordPress.com account',
				'role'         => 'Role',
			),
		);

		self::assertArrayNotHasKey(
			'user_jetpack',
			$result
		);
	}

	/**
	 * Verify unrelated columns are preserved.
	 *
	 * @return void
	 */
	public function test_unrelated_columns_are_preserved(): void {

		$columns = array(
			'username'     => 'Username',
			'email'        => 'Email',
			'user_jetpack' => 'WordPress.com account',
			'role'         => 'Role',
		);

		$result = $this->user_columns->remove_unused_columns(
			columns: $columns,
		);

		self::assertSame(
			'Username',
			$result['username']
		);

		self::assertSame(
			'Email',
			$result['email']
		);

		self::assertSame(
			'Role',
			$result['role']
		);
	}

	/**
	 * Verify the original column order is otherwise preserved.
	 *
	 * @return void
	 */
	public function test_column_order_is_preserved(): void {

		$result = $this->user_columns->remove_unused_columns(
			columns: array(
				'cb'           => 'Checkbox',
				'username'     => 'Username',
				'email'        => 'Email',
				'user_jetpack' => 'WordPress.com account',
				'phone'        => 'Phone',
				'role'         => 'Role',
			),
		);

		self::assertSame(
			array(
				'cb',
				'username',
				'email',
				'phone',
				'role',
			),
			array_keys( $result )
		);
	}

	/**
	 * Verify the method is safe when the Jetpack column is absent.
	 *
	 * @return void
	 */
	public function test_missing_jetpack_column_is_ignored(): void {

		$columns = array(
			'username' => 'Username',
			'email'    => 'Email',
			'role'     => 'Role',
		);

		self::assertSame(
			$columns,
			$this->user_columns->remove_unused_columns(
				columns: $columns,
			)
		);
	}
}
