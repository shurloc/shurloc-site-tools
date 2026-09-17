<?php
/**
 * Customer Journey session resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Attribution_Sanitizer;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Visitor_Repository;

/**
 * Resolve one accepted Journey activity to a visitor and session.
 *
 * @phpstan-type SessionIdentity array{
 *     visitor_id:int,
 *     visitor_uuid:string,
 *     identity_period_id:int,
 *     user_id_at_event:int|null,
 *     session_id:int
 * }
 */
final class Journey_Session_Service {
	/**
	 * Default gap between accepted activities before a new session starts.
	 */
	public const DEFAULT_TIMEOUT_SECONDS = 30 * 60;

	/**
	 * Filter the positive integer session gap in seconds.
	 */
	public const TIMEOUT_FILTER = 'shurloc_site_tools_journey_session_timeout_seconds';

	/**
	 * Visitor identity and central collection policy.
	 *
	 * @var Journey_Visitor_Service
	 */
	private Journey_Visitor_Service $visitors;

	/**
	 * Session storage.
	 *
	 * @var Journey_Session_Repository
	 */
	private Journey_Session_Repository $sessions;

	/**
	 * Persistent visitor first-touch attribution.
	 *
	 * @var Journey_Visitor_Repository
	 */
	private Journey_Visitor_Repository $visitor_repository;

	/**
	 * Landing and referrer data sanitizer.
	 *
	 * @var Journey_Attribution_Sanitizer
	 */
	private Journey_Attribution_Sanitizer $attribution;

	/**
	 * Constructor.
	 *
	 * @param Journey_Visitor_Service|null       $visitors    Visitor identity resolver.
	 * @param Journey_Session_Repository|null    $sessions    Session storage.
	 * @param Journey_Attribution_Sanitizer|null $attribution Attribution sanitizer.
	 * @param Journey_Visitor_Repository|null    $visitor_repository Visitor attribution storage.
	 */
	public function __construct(
		?Journey_Visitor_Service $visitors = null,
		?Journey_Session_Repository $sessions = null,
		?Journey_Attribution_Sanitizer $attribution = null,
		?Journey_Visitor_Repository $visitor_repository = null
	) {
		$this->visitors           = $visitors ?? new Journey_Visitor_Service();
		$this->sessions           = $sessions ?? new Journey_Session_Repository();
		$this->attribution        = $attribution ?? new Journey_Attribution_Sanitizer();
		$this->visitor_repository = $visitor_repository ?? new Journey_Visitor_Repository();
	}

	/**
	 * Create or continue a session for an accepted activity.
	 *
	 * The caller supplies the viewed page's relative URI. An ingestion endpoint's
	 * request URI is not the viewed page. Only page context is accepted from the
	 * caller; visitor and user identity come from the current server request.
	 * The timeout measures the gap between accepted activities, not time spent
	 * reading a visible page. A known page path establishes visitor first touch
	 * after session storage succeeds; that attribution write is best effort.
	 *
	 * @param string|null $page_uri     Relative URI of the viewed page, if known.
	 * @param string|null $referrer_url Referrer URL, if known.
	 * @return SessionIdentity|null Resolved session, or null when collection is unavailable.
	 * @phpstan-impure
	 */
	public function resolve_for_activity( ?string $page_uri = null, ?string $referrer_url = null ): ?array {
		$visitor = $this->visitors->resolve();
		if ( null === $visitor ) {
			return null;
		}

		$timeout = apply_filters( self::TIMEOUT_FILTER, self::DEFAULT_TIMEOUT_SECONDS );
		if ( ! is_int( $timeout ) || 0 >= $timeout ) {
			$timeout = self::DEFAULT_TIMEOUT_SECONDS;
		}

		$attribution = $this->attribution->sanitize(
			request_uri: $page_uri ?? '',
			referrer_url: $referrer_url,
		);

		$observed_at = gmdate( 'Y-m-d H:i:s' );
		$session_id  = $this->sessions->resolve_current(
			visitor_id: $visitor['visitor_id'],
			identity_period_id: $visitor['identity_period_id'],
			user_id_at_start: $visitor['user_id_at_event'],
			observed_at: $observed_at,
			timeout_seconds: $timeout,
			landing_path: $attribution['landing_path'],
			referrer_host: $attribution['referrer_host'],
			utm_source: $attribution['utm_source'],
			utm_medium: $attribution['utm_medium'],
			utm_campaign: $attribution['utm_campaign'],
			utm_term: $attribution['utm_term'],
			utm_content: $attribution['utm_content'],
		);

		if ( null === $session_id ) {
			return null;
		}

		if ( null !== $attribution['landing_path'] ) {
			// A first-touch write failure must not discard a successfully resolved session.
			$this->visitor_repository->record_first_touch(
				visitor_id: $visitor['visitor_id'],
				observed_at: $observed_at,
				attribution: $attribution,
			);
		}

		return array(
			'visitor_id'         => $visitor['visitor_id'],
			'visitor_uuid'       => $visitor['visitor_uuid'],
			'identity_period_id' => $visitor['identity_period_id'],
			'user_id_at_event'   => $visitor['user_id_at_event'],
			'session_id'         => $session_id,
		);
	}
}
