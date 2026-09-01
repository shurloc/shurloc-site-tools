<?php
/**
 * Tests for the user phone admin column.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the user phone admin column.
 */
final class UserPhoneColumnTest extends TestCase {

	/**
	 * Phone column under test.
	 *
	 * @var User_Phone_Column
	 */
	private User_Phone_Column $phone_column;

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

		$this->phone_column = new User_Phone_Column();
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

		parent::tearDown();
	}

	/**
	 * Verify the Users columns filter is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_users_columns_filter(): void {

		$this->phone_column->register();

		self::assertContains(
			array(
				$this->phone_column,
				'add_column',
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

		$this->phone_column->register();

		self::assertContains(
			array(
				$this->phone_column,
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
	 * Verify the Phone column is inserted after Email.
	 *
	 * @return void
	 */
	public function test_phone_column_is_added_after_email(): void {

		$result = $this->phone_column->add_column(
			columns: array(
				'cb'       => '<input type="checkbox" />',
				'username' => 'Username',
				'name'     => 'Name',
				'email'    => 'Email',
				'role'     => 'Role',
			),
		);

		self::assertSame(
			array(
				'cb',
				'username',
				'name',
				'email',
				User_Phone_Column::PHONE_COLUMN,
				'role',
			),
			array_keys( $result )
		);

		self::assertSame(
			'Phone',
			$result[ User_Phone_Column::PHONE_COLUMN ]
		);
	}

	/**
	 * Verify the Phone column is appended when Email is unavailable.
	 *
	 * @return void
	 */
	public function test_phone_column_is_appended_when_email_column_is_missing(): void {

		$result = $this->phone_column->add_column(
			columns: array(
				'username' => 'Username',
				'role'     => 'Role',
			),
		);

		self::assertSame(
			array(
				'username',
				'role',
				User_Phone_Column::PHONE_COLUMN,
			),
			array_keys( $result )
		);
	}

	/**
	 * Verify unrelated custom column output is preserved.
	 *
	 * @return void
	 */
	public function test_unrelated_column_preserves_existing_output(): void {

		$result = $this->phone_column->render_column(
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
	 * Verify a missing phone number renders an em dash.
	 *
	 * @return void
	 */
	public function test_missing_phone_renders_em_dash(): void {

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'&mdash;',
			$result
		);
	}

	/**
	 * Verify United States phone numbers are normalized for display.
	 *
	 * @param string $phone          Stored phone number.
	 * @param string $expected_phone Expected display phone number.
	 * @return void
	 */
	#[DataProvider( 'us_phone_number_provider' )]
	public function test_us_phone_number_is_normalized_for_display(
		string $phone,
		string $expected_phone
	): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] = $phone;

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'>' . $expected_phone . '</a>',
			$result
		);
	}

	/**
	 * Provide United States phone number formats.
	 *
	 * @return array<string,array{string,string}>
	 */
	public static function us_phone_number_provider(): array {

		return array(
			'digits only'              => array(
				'9415386941',
				'(941) 538-6941',
			),
			'hyphenated'               => array(
				'941-538-6941',
				'(941) 538-6941',
			),
			'dotted'                   => array(
				'941.538.6941',
				'(941) 538-6941',
			),
			'already formatted'        => array(
				'(941) 538-6941',
				'(941) 538-6941',
			),
			'leading one with hyphens' => array(
				'1-941-538-6941',
				'(941) 538-6941',
			),
			'leading plus one'         => array(
				'+1 941 538 6941',
				'(941) 538-6941',
			),
			'eleven digits'            => array(
				'19415386941',
				'(941) 538-6941',
			),
			'surrounding whitespace'   => array(
				'  9415386941  ',
				'(941) 538-6941',
			),
		);
	}

	/**
	 * Verify the full United States number is preserved in the tel URI.
	 *
	 * @return void
	 */
	public function test_us_country_code_is_preserved_in_tel_uri(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] =
			'+1 (555) 123-4567';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="tel:+15551234567"',
			$result
		);
	}

	/**
	 * Verify formatting characters are removed from a local tel URI.
	 *
	 * @return void
	 */
	public function test_local_phone_uri_contains_only_digits(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] =
			'(555) 123-4567';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="tel:5551234567"',
			$result
		);
	}

	/**
	 * Verify a non-US international phone number is preserved for display.
	 *
	 * @return void
	 */
	public function test_non_us_phone_number_is_preserved_for_display(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] =
			'+44 20 7123 4567';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'>+44 20 7123 4567</a>',
			$result
		);
	}

	/**
	 * Verify a non-US international phone number is normalized for the tel URI.
	 *
	 * @return void
	 */
	public function test_non_us_phone_number_is_normalized_for_tel_uri(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] =
			'+44 20 7123 4567';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringContainsString(
			'href="tel:+442071234567"',
			$result
		);
	}

	/**
	 * Verify non-phone characters do not produce a tel link.
	 *
	 * @return void
	 */
	public function test_phone_without_digits_renders_without_link(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] = 'Unknown';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertSame(
			'Unknown',
			$result
		);

		self::assertStringNotContainsString(
			'<a ',
			$result
		);
	}

	/**
	 * Verify the phone display value is escaped.
	 *
	 * @return void
	 */
	public function test_phone_display_is_escaped(): void {

		$GLOBALS['shurloc_test_user_meta'][101]['billing_phone'] =
			'+44 20 7123 4567<script>alert(1)</script>';

		$result = $this->phone_column->render_column(
			output: '',
			column_name: User_Phone_Column::PHONE_COLUMN,
			user_id: 101,
		);

		self::assertStringNotContainsString(
			'<script>',
			$result
		);

		self::assertStringContainsString(
			'&lt;script&gt;',
			$result
		);
	}
}
