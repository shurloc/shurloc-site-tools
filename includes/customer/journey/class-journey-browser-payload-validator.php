<?php
/**
 * Customer Journey browser payload validation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

/**
 * Accept only bounded view context and duration from a browser request.
 *
 * The ingestion controller must establish event type, post and product IDs,
 * visitor identity, and session from trusted server state. A view token is
 * used only to make retries idempotent; it grants no access to an event.
 */
final class Journey_Browser_Payload_Validator {
	/** Maximum referrer bytes accepted in one browser view request. */
	private const MAX_REFERRER_BYTES = 8192;

	/** Maximum cumulative duration in milliseconds, about 24.8 days. */
	private const MAX_TOTAL_ACTIVE_MS = 2147483647;

	/**
	 * Shared path and referrer sanitizer.
	 *
	 * @var Journey_Attribution_Sanitizer
	 */
	private Journey_Attribution_Sanitizer $attribution;

	/**
	 * Constructor.
	 *
	 * @param Journey_Attribution_Sanitizer|null $attribution Existing sanitizer.
	 */
	public function __construct( ?Journey_Attribution_Sanitizer $attribution = null ) {
		$this->attribution = $attribution ?? new Journey_Attribution_Sanitizer();
	}

	/**
	 * Validate a browser view without accepting identity or object claims.
	 *
	 * The controller must verify the viewed page against server state before
	 * recording PAGE_VIEW or PRODUCT_VIEW. Only a relative URI is accepted. The
	 * original URI is retained long enough for the event and session services
	 * to extract its path and whitelisted campaign parameters.
	 *
	 * @param mixed $payload Decoded request body.
	 * @return array{page_uri:string,referrer_url:string|null,idempotency_key:string}|null Valid view context.
	 */
	public function validate_view( mixed $payload ): ?array {
		if ( ! is_array( $payload ) || 2 > count( $payload ) || 3 < count( $payload ) ) {
			return null;
		}

		foreach ( array_keys( $payload ) as $field ) {
			if ( ! in_array( $field, array( 'page_uri', 'referrer_url', 'view_token' ), true ) ) {
				return null;
			}
		}

		$page_uri     = $payload['page_uri'] ?? null;
		$referrer_url = $payload['referrer_url'] ?? null;
		$view_token   = $payload['view_token'] ?? null;

		if (
			! is_string( $page_uri ) ||
			! is_string( $view_token ) ||
			1 !== preg_match( '/\A[0-9a-f]{32}\z/', $view_token ) ||
			( null !== $referrer_url && ( ! is_string( $referrer_url ) || strlen( $referrer_url ) > self::MAX_REFERRER_BYTES ) )
		) {
			return null;
		}

		$attribution = $this->attribution->sanitize(
			request_uri: $page_uri,
			referrer_url: $referrer_url
		);
		if ( null === $attribution['landing_path'] ) {
			return null;
		}

		return array(
			'page_uri'        => $page_uri,
			'referrer_url'    => null === $attribution['referrer_host'] ? null : $referrer_url,
			'idempotency_key' => hash( 'sha256', 'shurloc_journey:browser_view:' . $view_token ),
		);
	}

	/**
	 * Validate one cumulative visible-duration update.
	 *
	 * The maximum is an input bound, not an inactivity timer; visible time is
	 * accumulated by the browser while the document is visible. The controller
	 * must additionally check that a submitted total is plausible for the view.
	 *
	 * @param mixed $payload Decoded request body.
	 * @return array{event_id:int,total_active_ms:int}|null Valid duration input.
	 */
	public function validate_duration( mixed $payload ): ?array {
		if ( ! is_array( $payload ) || 2 !== count( $payload ) ||
			! array_key_exists( 'event_id', $payload ) ||
			! array_key_exists( 'total_active_ms', $payload ) ||
			! is_int( $payload['event_id'] ) ||
			! is_int( $payload['total_active_ms'] ) ||
			0 >= $payload['event_id'] ||
			0 > $payload['total_active_ms'] ||
			self::MAX_TOTAL_ACTIVE_MS < $payload['total_active_ms'] ) {
			return null;
		}

		return array(
			'event_id'        => $payload['event_id'],
			'total_active_ms' => $payload['total_active_ms'],
		);
	}
}
