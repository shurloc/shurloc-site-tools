<?php
/**
 * Customer Journey WordPress personal-data eraser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Privacy_Erasure_Repository;
use WP_User;

/**
 * Registers and serves the Journey personal-data eraser callback.
 *
 * @phpstan-type EraserResult array{
 *     items_removed:bool,
 *     items_retained:bool,
 *     messages:list<string>,
 *     done:bool
 * }
 */
final class Journey_Privacy_Eraser {
	/** Key used in WordPress's personal-data eraser registry. */
	public const ERASER_KEY = 'shurloc-site-tools-customer-journey';

	/** Filter controlling the maximum records removed from one dependent table. */
	public const BATCH_SIZE_FILTER = 'shurloc_site_tools_journey_privacy_erasure_batch_size';

	/** Default maximum records removed in one WordPress eraser request. */
	public const DEFAULT_BATCH_SIZE = 100;

	/**
	 * Bounded erasure storage.
	 *
	 * @var Journey_Privacy_Erasure_Repository
	 */
	private Journey_Privacy_Erasure_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Journey_Privacy_Erasure_Repository|null $repository Erasure storage.
	 */
	public function __construct( ?Journey_Privacy_Erasure_Repository $repository = null ) {
		$this->repository = $repository ?? new Journey_Privacy_Erasure_Repository();
	}

	/**
	 * Register the Journey eraser with WordPress privacy tools.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'wp_privacy_personal_data_erasers',
			array( $this, 'register_eraser' )
		);
	}

	/**
	 * Add the Journey eraser without replacing another plugin's callbacks.
	 *
	 * @param array $erasers Registered personal-data erasers.
	 * @return array Registered personal-data erasers.
	 * @phpstan-param array<string,array{eraser_friendly_name:string,callback:callable}> $erasers
	 * @phpstan-return array<string,array{eraser_friendly_name:string,callback:callable}>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[ self::ERASER_KEY ] = array(
			'eraser_friendly_name' => __( 'Customer Journey data', 'shurloc-site-tools' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Erase one bounded page of Journey data for the account matching an email.
	 *
	 * Journey stores no email address, so an account must still exist for the
	 * server to resolve the email to the stored WordPress user ID.
	 *
	 * @param string $email_address Confirmed privacy-request email address.
	 * @param int    $page          WordPress eraser page, starting at one.
	 * @return array WordPress personal-data eraser result.
	 * @phpstan-return EraserResult
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		if ( 1 > $page ) {
			return $this->failure_result();
		}

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User || 1 > $user->ID ) {
			return $this->result( done: true );
		}

		$batch = $this->repository->erase_user_batch(
			user_id: $user->ID,
			batch_size: $this->batch_size(),
		);

		if ( null === $batch ) {
			return $this->failure_result();
		}

		$removed = 0 < $batch['events_removed'] +
			$batch['sessions_removed'] +
			$batch['periods_removed'];

		return $this->result(
			items_removed: $removed,
			done: ! $batch['has_more'],
		);
	}

	/**
	 * Return a safe privacy batch size.
	 *
	 * @return int Positive bounded batch size.
	 */
	private function batch_size(): int {
		$batch_size = apply_filters( self::BATCH_SIZE_FILTER, self::DEFAULT_BATCH_SIZE );

		return is_int( $batch_size ) &&
			1 <= $batch_size &&
			Journey_Privacy_Erasure_Repository::MAX_BATCH_SIZE >= $batch_size
			? $batch_size
			: self::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Build a WordPress personal-data eraser result.
	 *
	 * @param bool  $items_removed  Whether this page removed records.
	 * @param bool  $items_retained Whether this page retained records.
	 * @param array $messages       Administrator-facing messages.
	 * @param bool  $done           Whether erasure is complete.
	 * @phpstan-param list<string> $messages
	 * @return array WordPress eraser result.
	 * @phpstan-return EraserResult
	 */
	private function result(
		bool $items_removed = false,
		bool $items_retained = false,
		array $messages = array(),
		bool $done = false
	): array {
		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Report that Journey data may remain after an erasure failure.
	 *
	 * @return array WordPress eraser result.
	 * @phpstan-return EraserResult
	 */
	private function failure_result(): array {
		return $this->result(
			items_retained: true,
			messages: array(
				__(
					'Customer Journey data could not be erased. The remaining data was retained.',
					'shurloc-site-tools'
				),
			),
			done: true,
		);
	}
}
