<?php
/**
 * Tests for the Customer Journey retention policy.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests central, filterable, and fail-closed retention configuration.
 */
final class JourneyRetentionPolicyTest extends TestCase {
	/**
	 * Policy under test.
	 *
	 * @var Journey_Retention_Policy
	 */
	private Journey_Retention_Policy $policy;

	/**
	 * Reset filter state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		$this->policy = new Journey_Retention_Policy();
	}

	/**
	 * Restore shared filter state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Unapproved retention periods remain unset by default.
	 *
	 * @return void
	 */
	public function test_all_retention_periods_are_unset_by_default(): void {
		self::assertSame(
			array(
				Journey_Retention_Policy::ANONYMOUS_HISTORY => null,
				Journey_Retention_Policy::RAW_EVENTS => null,
				Journey_Retention_Policy::IDENTIFIED_HISTORY => null,
			),
			$this->policy->get_retention_days()
		);
	}

	/**
	 * Sites can configure every retention period in one filter.
	 *
	 * @return void
	 */
	public function test_filter_configures_positive_day_counts(): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static function ( array $retention_days ): array {
				$retention_days[ Journey_Retention_Policy::ANONYMOUS_HISTORY ]  = 30;
				$retention_days[ Journey_Retention_Policy::RAW_EVENTS ]         = 365;
				$retention_days[ Journey_Retention_Policy::IDENTIFIED_HISTORY ] = 730;

				return $retention_days;
			}
		);

		self::assertSame(
			array(
				Journey_Retention_Policy::ANONYMOUS_HISTORY => 30,
				Journey_Retention_Policy::RAW_EVENTS => 365,
				Journey_Retention_Policy::IDENTIFIED_HISTORY => 730,
			),
			$this->policy->get_retention_days()
		);
	}

	/**
	 * A filter can configure one scope without selecting the other periods.
	 *
	 * @return void
	 */
	public function test_filter_can_configure_one_scope(): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static fn (): array => array(
				Journey_Retention_Policy::RAW_EVENTS => 180,
			)
		);

		self::assertSame( 180, $this->policy->get_days_for( Journey_Retention_Policy::RAW_EVENTS ) );
		self::assertNull(
			$this->policy->get_days_for( Journey_Retention_Policy::ANONYMOUS_HISTORY )
		);
		self::assertNull(
			$this->policy->get_days_for( Journey_Retention_Policy::IDENTIFIED_HISTORY )
		);
	}

	/**
	 * Invalid values disable cleanup for only their affected scopes.
	 *
	 * @return void
	 */
	public function test_invalid_values_fail_closed_per_scope(): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static fn (): array => array(
				Journey_Retention_Policy::ANONYMOUS_HISTORY => 0,
				Journey_Retention_Policy::RAW_EVENTS => '365',
				Journey_Retention_Policy::IDENTIFIED_HISTORY => 730,
				'unknown_scope'                      => 10,
			)
		);

		self::assertSame(
			array(
				Journey_Retention_Policy::ANONYMOUS_HISTORY => null,
				Journey_Retention_Policy::RAW_EVENTS => null,
				Journey_Retention_Policy::IDENTIFIED_HISTORY => 730,
			),
			$this->policy->get_retention_days()
		);
	}

	/**
	 * A malformed filter response cannot establish a cleanup cutoff.
	 *
	 * @return void
	 */
	public function test_non_array_filter_response_returns_unset_defaults(): void {
		add_filter(
			Journey_Retention_Policy::RETENTION_DAYS_FILTER,
			static fn (): string => '365'
		);

		self::assertSame(
			array(
				Journey_Retention_Policy::ANONYMOUS_HISTORY => null,
				Journey_Retention_Policy::RAW_EVENTS => null,
				Journey_Retention_Policy::IDENTIFIED_HISTORY => null,
			),
			$this->policy->get_retention_days()
		);
	}

	/**
	 * Unsupported scopes do not acquire an accidental retention period.
	 *
	 * @return void
	 */
	public function test_unknown_scope_returns_null(): void {
		self::assertNull( $this->policy->get_days_for( 'unknown_scope' ) );
	}
}
