<?php
/**
 * Time formatter.
 *
 * Formats timestamps for display in the WordPress
 * administration area.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Formatters;

defined( 'ABSPATH' ) || exit;

/**
 * Formats user activity timestamps for display.
 */
final class Relative_Time_Formatter {

	/**
	 * Number of seconds in one minute.
	 *
	 * @var int
	 */
	private const MINUTE_IN_SECONDS = 60;

	/**
	 * Number of seconds in one hour.
	 *
	 * @var int
	 */
	private const HOUR_IN_SECONDS = 3600;

	/**
	 * Number of seconds in one day.
	 *
	 * @var int
	 */
	private const DAY_IN_SECONDS = 86400;

	/**
	 * Format an activity timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public function format(
		int $timestamp
	): string {

		if ( 0 >= $timestamp ) {
			return '';
		}

		$current_timestamp = time();

		if ( $timestamp >= $current_timestamp ) {
			return __( 'Just now', 'shurloc-site-tools' );
		}

		$elapsed = $current_timestamp - $timestamp;

		if ( self::MINUTE_IN_SECONDS > $elapsed ) {
			return __( 'Just now', 'shurloc-site-tools' );
		}

		if ( self::HOUR_IN_SECONDS > $elapsed ) {

			$minutes = (int) floor(
				$elapsed / self::MINUTE_IN_SECONDS
			);

			return sprintf(
				/* translators: %d: Number of minutes ago. */
				_n(
					'%d minute ago',
					'%d minutes ago',
					$minutes,
					'shurloc-site-tools'
				),
				$minutes
			);
		}

		if ( self::DAY_IN_SECONDS > $elapsed ) {

			$hours = (int) floor(
				$elapsed / self::HOUR_IN_SECONDS
			);

			return sprintf(
				/* translators: %d: Number of hours ago. */
				_n(
					'%d hour ago',
					'%d hours ago',
					$hours,
					'shurloc-site-tools'
				),
				$hours
			);
		}

		if ( ( 2 * self::DAY_IN_SECONDS ) > $elapsed ) {
			return __( 'Yesterday', 'shurloc-site-tools' );
		}

		if ( ( 7 * self::DAY_IN_SECONDS ) > $elapsed ) {

			$days = (int) floor(
				$elapsed / self::DAY_IN_SECONDS
			);

			return sprintf(
				/* translators: %d: Number of days ago. */
				_n(
					'%d day ago',
					'%d days ago',
					$days,
					'shurloc-site-tools'
				),
				$days
			);
		}

		$time_string = wp_date(
			get_option( 'date_format' ),
			$timestamp
		);

		return ( false === $time_string ) ? '' : $time_string;
	}
}
