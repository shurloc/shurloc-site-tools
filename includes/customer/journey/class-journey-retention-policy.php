<?php
/**
 * Customer Journey retention policy.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies centrally configured retention periods for Journey cleanup.
 *
 * A null period means that no retention cutoff has been approved for that
 * scope. Cleanup code must skip a scope whose configured period is null.
 *
 * @phpstan-type RetentionDays array{
 *     anonymous_history: int|null,
 *     raw_events: int|null,
 *     identified_history: int|null
 * }
 */
final class Journey_Retention_Policy {
	/**
	 * Anonymous visitors and their dependent Journey records.
	 */
	public const ANONYMOUS_HISTORY = 'anonymous_history';

	/**
	 * Individual event records retained within identified history.
	 */
	public const RAW_EVENTS = 'raw_events';

	/**
	 * Identified visitor, identity, session, and attribution history.
	 */
	public const IDENTIFIED_HISTORY = 'identified_history';

	/**
	 * Filter for the Journey retention periods, expressed in days.
	 */
	public const RETENTION_DAYS_FILTER =
		'shurloc_site_tools_journey_retention_days';

	/**
	 * Retention periods pending approval.
	 *
	 * @var RetentionDays
	 */
	private const DEFAULT_RETENTION_DAYS = array(
		self::ANONYMOUS_HISTORY  => null,
		self::RAW_EVENTS         => null,
		self::IDENTIFIED_HISTORY => null,
	);

	/**
	 * Return the configured retention periods.
	 *
	 * Filters may replace one or more values with positive integer day counts.
	 * Missing or invalid values remain null so cleanup fails closed per scope.
	 * Unknown keys are ignored.
	 *
	 * @return RetentionDays
	 */
	public function get_retention_days(): array {
		$filtered = apply_filters(
			self::RETENTION_DAYS_FILTER,
			self::DEFAULT_RETENTION_DAYS
		);

		if ( ! is_array( $filtered ) ) {
			return self::DEFAULT_RETENTION_DAYS;
		}

		$retention_days = self::DEFAULT_RETENTION_DAYS;

		foreach ( array_keys( self::DEFAULT_RETENTION_DAYS ) as $scope ) {
			$value = $filtered[ $scope ] ?? null;

			if ( is_int( $value ) && 0 < $value ) {
				$retention_days[ $scope ] = $value;
			}
		}

		return $retention_days;
	}

	/**
	 * Return the configured number of days for one retention scope.
	 *
	 * @param string $scope Retention scope.
	 * @return int|null Positive day count, or null when cleanup must skip it.
	 */
	public function get_days_for( string $scope ): ?int {
		$retention_days = $this->get_retention_days();

		return $retention_days[ $scope ] ?? null;
	}
}
