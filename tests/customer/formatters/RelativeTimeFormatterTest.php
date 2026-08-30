<?php
/**
 * Tests for the relative time formatter.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Formatters;

use PHPUnit\Framework\TestCase;

/**
 * Tests the relative time formatter.
 */
final class RelativeTimeFormatterTest extends TestCase {

	/**
	 * Formatter under test.
	 *
	 * @var Relative_Time_Formatter
	 */
	private Relative_Time_Formatter $formatter;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_time'] = 1_000_000;

		$this->formatter = new Relative_Time_Formatter();
	}

	/**
	 * Verify invalid timestamps return an empty string.
	 *
	 * @return void
	 */
	public function test_invalid_timestamp_returns_empty_string(): void {

		self::assertSame(
			'',
			$this->formatter->format( 0 )
		);
	}

	/**
	 * Verify future timestamps are treated as just now.
	 *
	 * @return void
	 */
	public function test_future_timestamp_returns_just_now(): void {

		self::assertSame(
			'Just now',
			$this->formatter->format( 1_000_001 )
		);
	}

	/**
	 * Verify timestamps less than one minute old return just now.
	 *
	 * @return void
	 */
	public function test_timestamp_less_than_one_minute_old_returns_just_now(): void {

		self::assertSame(
			'Just now',
			$this->formatter->format( 999_941 )
		);
	}

	/**
	 * Verify exactly one minute is formatted singularly.
	 *
	 * @return void
	 */
	public function test_exactly_one_minute_old_returns_one_minute_ago(): void {

		self::assertSame(
			'1 minute ago',
			$this->formatter->format( 999_940 )
		);
	}

	/**
	 * Verify multiple minutes are formatted plurally.
	 *
	 * @return void
	 */
	public function test_multiple_minutes_are_formatted(): void {

		self::assertSame(
			'17 minutes ago',
			$this->formatter->format(
				1_000_000 - ( 17 * 60 )
			)
		);
	}

	/**
	 * Verify exactly one hour is formatted singularly.
	 *
	 * @return void
	 */
	public function test_exactly_one_hour_old_returns_one_hour_ago(): void {

		self::assertSame(
			'1 hour ago',
			$this->formatter->format(
				1_000_000 - 3600
			)
		);
	}

	/**
	 * Verify multiple hours are formatted plurally.
	 *
	 * @return void
	 */
	public function test_multiple_hours_are_formatted(): void {

		self::assertSame(
			'6 hours ago',
			$this->formatter->format(
				1_000_000 - ( 6 * 3600 )
			)
		);
	}

	/**
	 * Verify exactly one day old returns Yesterday.
	 *
	 * @return void
	 */
	public function test_exactly_one_day_old_returns_yesterday(): void {

		self::assertSame(
			'Yesterday',
			$this->formatter->format(
				1_000_000 - 86400
			)
		);
	}

	/**
	 * Verify timestamps less than two days old return Yesterday.
	 *
	 * @return void
	 */
	public function test_timestamp_less_than_two_days_old_returns_yesterday(): void {

		self::assertSame(
			'Yesterday',
			$this->formatter->format(
				1_000_000 - ( ( 2 * 86400 ) - 1 )
			)
		);
	}

	/**
	 * Verify exactly two days old returns two days ago.
	 *
	 * @return void
	 */
	public function test_exactly_two_days_old_returns_two_days_ago(): void {

		self::assertSame(
			'2 days ago',
			$this->formatter->format(
				1_000_000 - ( 2 * 86400 )
			)
		);
	}

	/**
	 * Verify multiple days are formatted plurally.
	 *
	 * @return void
	 */
	public function test_multiple_days_are_formatted(): void {

		self::assertSame(
			'5 days ago',
			$this->formatter->format(
				1_000_000 - ( 5 * 86400 )
			)
		);
	}

	/**
	 * Verify timestamps less than seven days old use relative days.
	 *
	 * @return void
	 */
	public function test_timestamp_less_than_seven_days_old_uses_relative_days(): void {

		self::assertSame(
			'6 days ago',
			$this->formatter->format(
				1_000_000 - ( ( 7 * 86400 ) - 1 )
			)
		);
	}

	/**
	 * Verify exactly seven days old uses the WordPress date format.
	 *
	 * @return void
	 */
	public function test_exactly_seven_days_old_uses_wordpress_date_format(): void {

		$timestamp = 1_000_000 - ( 7 * 86400 );

		self::assertSame(
			wp_date(
				get_option( 'date_format' ),
				$timestamp
			),
			$this->formatter->format( $timestamp )
		);
	}

	/**
	 * Verify older timestamps use the WordPress date format.
	 *
	 * @return void
	 */
	public function test_older_timestamp_uses_wordpress_date_format(): void {

		$timestamp = 2_600_000 - ( 30 * 86400 );

		self::assertSame(
			wp_date(
				get_option( 'date_format' ),
				$timestamp
			),
			$this->formatter->format( $timestamp )
		);
	}
}
