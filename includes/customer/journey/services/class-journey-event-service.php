<?php
/**
 * Customer Journey event orchestration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Attribution_Sanitizer;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Field_Validator;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Event_Repository;

/**
 * Record a trusted v1 event using the current request's visitor and session.
 *
 * Callers must establish product, post, and order IDs from server state. A
 * later browser-ingestion controller must validate claims before calling this
 * service. Visitor, session, user, and occurrence time are always server-owned.
 */
final class Journey_Event_Service {
	/**
	 * Session and identity resolution, including collection policy.
	 *
	 * @var Journey_Session_Service
	 */
	private Journey_Session_Service $sessions;

	/**
	 * Event storage.
	 *
	 * @var Journey_Event_Repository
	 */
	private Journey_Event_Repository $events;

	/**
	 * Sanitize the viewed page separately from the ingestion request URI.
	 *
	 * @var Journey_Attribution_Sanitizer
	 */
	private Journey_Attribution_Sanitizer $attribution;

	/**
	 * Type-specific event field rules.
	 *
	 * @var Journey_Event_Field_Validator
	 */
	private Journey_Event_Field_Validator $field_validator;

	/**
	 * Current visitor resolution for duration updates.
	 *
	 * @var Journey_Visitor_Service
	 */
	private Journey_Visitor_Service $visitors;

	/**
	 * Constructor.
	 *
	 * @param Journey_Session_Service|null       $sessions        Current session resolver.
	 * @param Journey_Event_Repository|null      $events          Event storage.
	 * @param Journey_Attribution_Sanitizer|null $attribution     Page-path sanitizer.
	 * @param Journey_Event_Field_Validator|null $field_validator Event field rules.
	 * @param Journey_Visitor_Service|null       $visitors        Current visitor resolver.
	 */
	public function __construct(
		?Journey_Session_Service $sessions = null,
		?Journey_Event_Repository $events = null,
		?Journey_Attribution_Sanitizer $attribution = null,
		?Journey_Event_Field_Validator $field_validator = null,
		?Journey_Visitor_Service $visitors = null
	) {
		$this->sessions        = $sessions ?? new Journey_Session_Service();
		$this->events          = $events ?? new Journey_Event_Repository();
		$this->attribution     = $attribution ?? new Journey_Attribution_Sanitizer();
		$this->field_validator = $field_validator ?? new Journey_Event_Field_Validator();
		$this->visitors        = $visitors ?? new Journey_Visitor_Service();
	}

	/**
	 * Record one accepted occurrence and return its stored event ID.
	 *
	 * The caller supplies only trusted event details. A product page uses one
	 * PRODUCT_VIEW rather than a separate PAGE_VIEW. The page URI is a relative
	 * request target; query parameters are stripped before storage. A checkout
	 * start requires a stable key for one entry, then scopes that key to the
	 * resolved session. Order keys are independent of checkout transport.
	 *
	 * @param string      $event_type      One of the v1 event types.
	 * @param string|null $page_uri        Viewed page URI, not the ingestion URI.
	 * @param string|null $referrer_url    Referrer URL for session attribution.
	 * @param int|null    $post_id         Server-verified WordPress post ID.
	 * @param int|null    $product_id      Server-verified product ID.
	 * @param int|null    $variation_id    Server-verified variation ID.
	 * @param string|null $quantity        Positive decimal cart quantity.
	 * @param int|null    $order_id        Server-verified WooCommerce order ID.
	 * @param int         $active_ms       Initial estimated visible duration.
	 * @param string|null $source          Server-selected event source.
	 * @param string|null $idempotency_key Stable lowercase SHA-256 event key; for checkout, one entry key.
	 * @return array{id:int,created:bool,visitor_id:int,session_id:int}|null Stored event or null when rejected.
	 * @phpstan-impure
	 */
	public function record(
		string $event_type,
		?string $page_uri = null,
		?string $referrer_url = null,
		?int $post_id = null,
		?int $product_id = null,
		?int $variation_id = null,
		?string $quantity = null,
		?int $order_id = null,
		int $active_ms = 0,
		?string $source = null,
		?string $idempotency_key = null
	): ?array {
		$path = $this->attribution->sanitize(
			request_uri: $page_uri ?? '',
			referrer_url: null,
		)['landing_path'];

		if ( null !== $page_uri && null === $path ) {
			return null;
		}

		if ( Journey_Event_Type::CHECKOUT_STARTED === $event_type && null === $idempotency_key ) {
			return null;
		}

		if ( Journey_Event_Type::ORDER_CREATED === $event_type && null !== $order_id && 0 < $order_id ) {
			$order_key = hash( 'sha256', 'shurloc_journey:ORDER_CREATED:' . $order_id );
			if ( null !== $idempotency_key && $order_key !== $idempotency_key ) {
				return null;
			}

			$idempotency_key = $order_key;
		}

		$fields = array(
			'event_type'      => $event_type,
			'page_path'       => $path,
			'post_id'         => $post_id,
			'product_id'      => $product_id,
			'variation_id'    => $variation_id,
			'quantity'        => $quantity,
			'order_id'        => $order_id,
			'active_ms'       => $active_ms,
			'source'          => $source,
			'idempotency_key' => $idempotency_key,
		);

		if ( ! $this->field_validator->is_valid( event: $fields ) ) {
			return null;
		}

		$session = $this->sessions->resolve_for_activity(
			page_uri: $page_uri,
			referrer_url: $referrer_url,
		);
		if ( null === $session ) {
			return null;
		}

		if ( Journey_Event_Type::CHECKOUT_STARTED === $event_type ) {
			$fields['idempotency_key'] = hash( 'sha256', 'shurloc_journey:CHECKOUT_STARTED:' . $session['session_id'] . ':' . $idempotency_key );
		}

		$result = $this->events->record(
			event: array_merge(
				array(
					'session_id'       => $session['session_id'],
					'visitor_id'       => $session['visitor_id'],
					'user_id_at_event' => $session['user_id_at_event'],
					'occurred_at'      => gmdate( 'Y-m-d H:i:s' ),
				),
				$fields
			),
		);

		if ( null === $result ) {
			return null;
		}

		return array(
			'id'         => $result['id'],
			'created'    => $result['created'],
			'visitor_id' => $session['visitor_id'],
			'session_id' => $session['session_id'],
		);
	}

	/**
	 * Add a view's cumulative visible time using the current visitor identity.
	 *
	 * The repository verifies event ownership and applies only an increase to
	 * the original session. This does not resolve or extend a session. A later
	 * browser-ingestion controller must validate the submitted duration.
	 *
	 * @param int $event_id        Existing page or product view event ID.
	 * @param int $total_active_ms Cumulative visible time for that view.
	 * @return bool Whether the duration was accepted or already recorded.
	 * @phpstan-impure
	 */
	public function record_view_duration( int $event_id, int $total_active_ms ): bool {
		if ( 0 >= $event_id || 0 > $total_active_ms ) {
			return false;
		}

		$identity = $this->visitors->resolve();
		if ( null === $identity ) {
			return false;
		}

		return $this->events->record_view_duration(
			event_id: $event_id,
			visitor_id: $identity['visitor_id'],
			total_active_ms: $total_active_ms
		);
	}
}
