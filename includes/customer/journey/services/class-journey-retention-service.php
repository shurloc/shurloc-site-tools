<?php
/**
 * Customer Journey retention cleanup orchestration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

defined( 'ABSPATH' ) || exit;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Journey_Retention_Policy;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Retention_Repository;
use Throwable;

/**
 * Runs one bounded cleanup batch for each configured retention scope.
 *
 * @phpstan-import-type RetentionDays from Journey_Retention_Policy
 * @phpstan-type RetentionBatchResult array{
 *     success:bool,
 *     has_more:bool,
 *     events_deleted:int,
 *     identified_sessions_deleted:int,
 *     identified_attribution_cleared:int,
 *     identified_histories_deleted:int,
 *     identity_periods_deleted:int,
 *     anonymous_histories_deleted:int
 * }
 */
final class Journey_Retention_Service {
	/** Default roots processed by each repository operation. */
	public const DEFAULT_BATCH_SIZE = 100;

	/** Filter for the roots processed by each repository operation. */
	public const BATCH_SIZE_FILTER = 'shurloc_site_tools_journey_retention_batch_size';

	/**
	 * Central retention configuration.
	 *
	 * @var Journey_Retention_Policy
	 */
	private Journey_Retention_Policy $policy;

	/**
	 * Bounded Journey deletion operations.
	 *
	 * @var Journey_Retention_Repository
	 */
	private Journey_Retention_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Journey_Retention_Policy|null     $policy     Retention configuration.
	 * @param Journey_Retention_Repository|null $repository Retention storage.
	 */
	public function __construct(
		?Journey_Retention_Policy $policy = null,
		?Journey_Retention_Repository $repository = null
	) {
		$this->policy     = $policy ?? new Journey_Retention_Policy();
		$this->repository = $repository ?? new Journey_Retention_Repository();
	}

	/**
	 * Run one cleanup batch, stopping at the first failed operation.
	 *
	 * Unset policy scopes are skipped. Events are pruned before dependent
	 * history. Identified visitor roots are removed before expired periods can
	 * remove their identity classification. Anonymous visitor roots run last.
	 * The result lets a later scheduler distinguish failure from more work.
	 *
	 * @param DateTimeImmutable|null $now Current time; defaults to server UTC.
	 * @return RetentionBatchResult Cleanup outcome and affected root counts.
	 */
	public function run_batch( ?DateTimeImmutable $now = null ): array {
		$result = $this->empty_result();
		$days   = $this->policy->get_retention_days();
		$limit  = $this->batch_size();
		$now    = ( $now ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->setTimezone( new DateTimeZone( 'UTC' ) );

		if ( null !== $days[ Journey_Retention_Policy::RAW_EVENTS ] ) {
			$cutoff = $this->cutoff( now: $now, days: $days[ Journey_Retention_Policy::RAW_EVENTS ] );
			if ( null === $cutoff ) {
				return $this->failed( result: $result );
			}

			$count = $this->repository->delete_events_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}

			$result['events_deleted'] = $count;
			$result['has_more']       = $result['has_more'] || $limit === $count;
		}

		if ( null !== $days[ Journey_Retention_Policy::IDENTIFIED_HISTORY ] ) {
			$cutoff = $this->cutoff( now: $now, days: $days[ Journey_Retention_Policy::IDENTIFIED_HISTORY ] );
			if ( null === $cutoff ) {
				return $this->failed( result: $result );
			}

			$count = $this->repository->delete_identified_sessions_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}
			$result['identified_sessions_deleted'] = $count;
			$result['has_more']                    = $result['has_more'] || $limit === $count;

			$count = $this->repository->clear_identified_attribution_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}
			$result['identified_attribution_cleared'] = $count;
			$result['has_more']                       = $result['has_more'] || $limit === $count;

			$count = $this->repository->delete_identified_histories_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}
			$result['identified_histories_deleted'] = $count;
			$result['has_more']                     = $result['has_more'] || $limit === $count;

			$count = $this->repository->delete_unreferenced_identity_periods_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}
			$result['identity_periods_deleted'] = $count;
			$result['has_more']                 = $result['has_more'] || $limit === $count;
		}

		if ( null !== $days[ Journey_Retention_Policy::ANONYMOUS_HISTORY ] ) {
			$cutoff = $this->cutoff( now: $now, days: $days[ Journey_Retention_Policy::ANONYMOUS_HISTORY ] );
			if ( null === $cutoff ) {
				return $this->failed( result: $result );
			}

			$count = $this->repository->delete_anonymous_histories_before(
				cutoff_utc: $cutoff,
				batch_size: $limit,
			);
			if ( null === $count ) {
				return $this->failed( result: $result );
			}

			$result['anonymous_histories_deleted'] = $count;
			$result['has_more']                    = $result['has_more'] || $limit === $count;
		}

		return $result;
	}

	/**
	 * Return an empty successful batch result.
	 *
	 * @return RetentionBatchResult Empty result.
	 */
	private function empty_result(): array {
		return array(
			'success'                        => true,
			'has_more'                       => false,
			'events_deleted'                 => 0,
			'identified_sessions_deleted'    => 0,
			'identified_attribution_cleared' => 0,
			'identified_histories_deleted'   => 0,
			'identity_periods_deleted'       => 0,
			'anonymous_histories_deleted'    => 0,
		);
	}

	/**
	 * Mark a partial batch as failed without discarding its completed counts.
	 *
	 * @param array $result Partial result.
	 * @return RetentionBatchResult Failed result.
	 * @phpstan-param RetentionBatchResult $result
	 */
	private function failed( array $result ): array {
		$result['success'] = false;

		return $result;
	}

	/**
	 * Resolve the bounded number of roots handled by each operation.
	 *
	 * @return int Batch size.
	 */
	private function batch_size(): int {
		$batch_size = apply_filters( self::BATCH_SIZE_FILTER, self::DEFAULT_BATCH_SIZE );

		return is_int( $batch_size ) &&
			1 <= $batch_size &&
			Journey_Retention_Repository::MAX_BATCH_SIZE >= $batch_size
			? $batch_size
			: self::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Calculate an exclusive UTC cutoff from a positive number of days.
	 *
	 * @param DateTimeImmutable $now  Current UTC time.
	 * @param int               $days Retention period in days.
	 * @return string|null UTC MySQL datetime, or null when it cannot be represented.
	 */
	private function cutoff( DateTimeImmutable $now, int $days ): ?string {
		try {
			return $now->sub( new DateInterval( 'P' . $days . 'D' ) )->format( 'Y-m-d H:i:s' );
		} catch ( Throwable $error ) {
			unset( $error );
			return null;
		}
	}
}
