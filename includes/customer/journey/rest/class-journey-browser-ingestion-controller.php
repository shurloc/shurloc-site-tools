<?php
/**
 * Customer Journey browser REST ingestion.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Rest;

defined( 'ABSPATH' ) || exit;

use JsonException;
use Shurloc\SiteTools\Customer\Journey\Journey_Browser_Payload_Validator;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Page_Context_Resolver;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Services\Journey_Event_Service;
use WP_Error;
use WP_REST_Request;

/**
 * Accept narrow browser view and duration requests over the WordPress REST API.
 *
 * WordPress handles cookie authentication and REST nonces. The browser sends
 * only page context or a previously returned event ID and cumulative visible
 * time. The service resolves the current visitor, session, and user.
 */
final class Journey_Browser_Ingestion_Controller {
	/** REST API namespace. */
	public const ROUTE_NAMESPACE = 'shurloc-site-tools/v1';

	/** Maximum raw JSON body size. */
	private const MAX_BODY_BYTES = 20480;

	/**
	 * Central collection eligibility policy.
	 *
	 * @var Journey_Collection_Policy
	 */
	private Journey_Collection_Policy $collection_policy;

	/**
	 * Journey schema readiness gate.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Browser payload shape and bound checks.
	 *
	 * @var Journey_Browser_Payload_Validator
	 */
	private Journey_Browser_Payload_Validator $payload_validator;

	/**
	 * Server-derived page and product context.
	 *
	 * @var Journey_Page_Context_Resolver
	 */
	private Journey_Page_Context_Resolver $pages;

	/**
	 * Server-owned event orchestration.
	 *
	 * @var Journey_Event_Service
	 */
	private Journey_Event_Service $events;

	/**
	 * Existing first-party visitor cookie for duration requests.
	 *
	 * @var Journey_Visitor_Cookie
	 */
	private Journey_Visitor_Cookie $cookie;

	/**
	 * Constructor.
	 *
	 * @param Journey_Collection_Policy|null         $collection_policy Request policy.
	 * @param Journey_Schema_Migrator|null           $schema_migrator    Schema gate.
	 * @param Journey_Browser_Payload_Validator|null $payload_validator Browser payload validator.
	 * @param Journey_Page_Context_Resolver|null     $pages              Page context resolver.
	 * @param Journey_Event_Service|null             $events             Event service.
	 * @param Journey_Visitor_Cookie|null            $cookie             Visitor cookie.
	 */
	public function __construct(
		?Journey_Collection_Policy $collection_policy = null,
		?Journey_Schema_Migrator $schema_migrator = null,
		?Journey_Browser_Payload_Validator $payload_validator = null,
		?Journey_Page_Context_Resolver $pages = null,
		?Journey_Event_Service $events = null,
		?Journey_Visitor_Cookie $cookie = null
	) {
		$this->collection_policy = $collection_policy ?? new Journey_Collection_Policy();
		$this->schema_migrator   = $schema_migrator ?? new Journey_Schema_Migrator();
		$this->payload_validator = $payload_validator ?? new Journey_Browser_Payload_Validator();
		$this->pages             = $pages ?? new Journey_Page_Context_Resolver();
		$this->events            = $events ?? new Journey_Event_Service();
		$this->cookie            = $cookie ?? new Journey_Visitor_Cookie();
	}

	/**
	 * Register route setup at the WordPress REST lifecycle hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the two browser collection routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/journey/view',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record_view' ),
				'permission_callback' => array( $this, 'can_collect' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/journey/duration',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record_duration' ),
				'permission_callback' => array( $this, 'can_collect' ),
			)
		);
	}

	/**
	 * Require collection eligibility and reject conflicting browser origins.
	 *
	 * Missing Origin and Referer headers are tolerated for privacy browsers;
	 * JSON content type and SameSite visitor cookies still limit browser-based
	 * cross-site delivery. This does not authenticate a scripted HTTP client.
	 *
	 * @param WP_REST_Request $request Browser request.
	 * @return bool Whether this request may attempt Journey collection.
	 */
	public function can_collect( WP_REST_Request $request ): bool {
		// Without a REST nonce, WordPress may clear a logged-in user's identity.
		if ( defined( 'LOGGED_IN_COOKIE' ) && isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) && ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $this->collection_policy->allows_collection() || ! $this->schema_migrator->is_ready() ) {
			return false;
		}

		$fetch_site = $request->get_header( 'sec-fetch-site' );
		if ( null !== $fetch_site && ! in_array( $fetch_site, array( 'same-origin', 'none' ), true ) ) {
			return false;
		}

		$origin = $request->get_header( 'origin' );
		if ( null !== $origin ) {
			return $this->matches_site_origin( url: $origin );
		}

		$referer = $request->get_header( 'referer' );
		return null === $referer || $this->matches_site_origin( url: $referer );
	}

	/**
	 * Record one page or product view and return its server event ID.
	 *
	 * @param WP_REST_Request $request Browser request.
	 * @return array{event_id:int,created:bool}|WP_Error View result or error.
	 */
	public function record_view( WP_REST_Request $request ): array|WP_Error {
		$payload = $this->parse_payload( request: $request );
		if ( $payload instanceof WP_Error ) {
			return $payload;
		}

		$view = $this->payload_validator->validate_view( payload: $payload );
		if ( null === $view ) {
			return $this->error( code: 'shurloc_journey_invalid_view', status: 400 );
		}

		$context = $this->pages->resolve( page_uri: $view['page_uri'] );
		if ( null === $context ) {
			return $this->error( code: 'shurloc_journey_invalid_page', status: 422 );
		}

		$result = $this->events->record(
			event_type: $context['event_type'],
			page_uri: $view['page_uri'],
			referrer_url: $view['referrer_url'],
			post_id: $context['post_id'],
			product_id: $context['product_id'],
			source: 'browser',
			idempotency_key: $view['idempotency_key']
		);
		if ( null === $result ) {
			return $this->error( code: 'shurloc_journey_unavailable', status: 503 );
		}

		return array(
			'event_id' => $result['id'],
			'created'  => $result['created'],
		);
	}

	/**
	 * Update an existing view's cumulative visible duration.
	 *
	 * A browser without its visitor cookie cannot own a prior view, so the
	 * request is rejected before visitor resolution can create a new identity.
	 *
	 * @param WP_REST_Request $request Browser request.
	 * @return array{accepted:bool}|WP_Error Duration result or error.
	 */
	public function record_duration( WP_REST_Request $request ): array|WP_Error {
		$payload = $this->parse_payload( request: $request );
		if ( $payload instanceof WP_Error ) {
			return $payload;
		}

		$duration = $this->payload_validator->validate_duration( payload: $payload );
		if ( null === $duration ) {
			return $this->error( code: 'shurloc_journey_invalid_duration', status: 400 );
		}

		if ( null === $this->cookie->read() ) {
			return $this->error( code: 'shurloc_journey_missing_visitor', status: 403 );
		}

		if ( ! $this->events->record_view_duration(
			event_id: $duration['event_id'],
			total_active_ms: $duration['total_active_ms']
		) ) {
			return $this->error( code: 'shurloc_journey_duration_rejected', status: 422 );
		}

		return array( 'accepted' => true );
	}

	/**
	 * Parse a small JSON object without trusting form or query parameters.
	 *
	 * @param WP_REST_Request $request Browser request.
	 * @return array<string,mixed>|WP_Error Decoded object or request error.
	 */
	private function parse_payload( WP_REST_Request $request ): array|WP_Error {
		$content_type = $request->get_header( 'content-type' );
		if ( null === $content_type || 1 !== preg_match( '/\Aapplication\/json(?:\s*;|\z)/i', trim( $content_type ) ) ) {
			return $this->error( code: 'shurloc_journey_json_required', status: 415 );
		}

		$body = $request->get_body();
		if ( '' === $body || self::MAX_BODY_BYTES < strlen( $body ) ) {
			return $this->error( code: 'shurloc_journey_invalid_body_size', status: 413 );
		}

		try {
			$payload = json_decode( $body, true, 4, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING );
		} catch ( JsonException $error ) {
			unset( $error );
			return $this->error( code: 'shurloc_journey_invalid_json', status: 400 );
		}

		if ( ! is_array( $payload ) || array_is_list( $payload ) ) {
			return $this->error( code: 'shurloc_journey_invalid_json', status: 400 );
		}

		return $payload;
	}

	/**
	 * Compare only the scheme, host, and port of a supplied browser URL.
	 *
	 * @param string $url Origin or Referer header.
	 * @return bool Whether it matches this site's home origin.
	 */
	private function matches_site_origin( string $url ): bool {
		$origin = wp_parse_url( $url );
		$home   = wp_parse_url( home_url() );
		return is_array( $origin ) && is_array( $home ) &&
			isset( $origin['scheme'], $origin['host'], $home['scheme'], $home['host'] ) &&
			! isset( $origin['user'], $origin['pass'] ) &&
			strtolower( $origin['scheme'] ) === strtolower( $home['scheme'] ) &&
			strtolower( $origin['host'] ) === strtolower( $home['host'] ) &&
			( $origin['port'] ?? null ) === ( $home['port'] ?? null );
	}

	/**
	 * Construct an intentionally generic public REST error.
	 *
	 * @param string $code   Stable error code.
	 * @param int    $status HTTP status.
	 * @return WP_Error REST error.
	 */
	private function error( string $code, int $status ): WP_Error {
		return new WP_Error( $code, __( 'Journey request could not be recorded.', 'shurloc-site-tools' ), array( 'status' => $status ) );
	}
}
