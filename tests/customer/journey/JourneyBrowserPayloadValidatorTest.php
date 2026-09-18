<?php
/**
 * Tests for Customer Journey browser payload validation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests the narrow untrusted input accepted before server event resolution.
 */
final class JourneyBrowserPayloadValidatorTest extends TestCase {
	/**
	 * A valid view retains context and derives a stable scoped retry key.
	 *
	 * @return void
	 */
	public function test_valid_view_returns_context_and_stable_idempotency_key(): void {
		$validator = new Journey_Browser_Payload_Validator();
		$payload   = array(
			'page_uri'     => '/article?utm_source=newsletter&email=private%40example.org',
			'referrer_url' => 'https://example.org/previous?token=private',
			'view_token'   => str_repeat( 'a', 32 ),
		);

		$expected = array(
			'page_uri'           => $payload['page_uri'],
			'referrer_url'       => $payload['referrer_url'],
			'idempotency_key'    => hash( 'sha256', 'shurloc_journey:browser_view:' . $payload['view_token'] ),
			'checkout_entry_key' => null,
		);

		self::assertSame( $expected, $validator->validate_view( payload: $payload ) );
		self::assertSame( $expected, $validator->validate_view( payload: $payload ) );
		$other = $validator->validate_view( payload: array_replace( $payload, array( 'view_token' => str_repeat( 'b', 32 ) ) ) );
		self::assertNotNull( $other );
		self::assertNotSame( $expected['idempotency_key'], $other['idempotency_key'] );
	}

	/**
	 * Checkout entry retries keep one key while each document gets a view key.
	 *
	 * @return void
	 */
	public function test_checkout_entry_token_is_independent_of_view_token(): void {
		$validator = new Journey_Browser_Payload_Validator();
		$payload   = array(
			'page_uri'             => '/checkout',
			'referrer_url'         => 'https://example.org/cart',
			'view_token'           => str_repeat( 'a', 32 ),
			'checkout_entry_token' => str_repeat( 'b', 32 ),
		);
		$first     = $validator->validate_view( payload: $payload );
		self::assertNotNull( $first );
		self::assertSame( hash( 'sha256', 'shurloc_journey:browser_checkout_entry:' . $payload['checkout_entry_token'] ), $first['checkout_entry_key'] );
		self::assertNotSame( $first['idempotency_key'], $first['checkout_entry_key'] );

		$reload = $validator->validate_view( payload: array_replace( $payload, array( 'view_token' => str_repeat( 'c', 32 ) ) ) );
		self::assertNotNull( $reload );
		self::assertNotSame( $first['idempotency_key'], $reload['idempotency_key'] );
		self::assertSame( $first['checkout_entry_key'], $reload['checkout_entry_key'] );

		$new_entry = $validator->validate_view( payload: array_replace( $payload, array( 'checkout_entry_token' => str_repeat( 'd', 32 ) ) ) );
		self::assertNotNull( $new_entry );
		self::assertNotSame( $first['checkout_entry_key'], $new_entry['checkout_entry_key'] );
	}

	/**
	 * Bad referrers do not prevent a valid view and are never passed onward.
	 *
	 * @return void
	 */
	public function test_invalid_referrer_is_discarded(): void {
		$validator = new Journey_Browser_Payload_Validator();
		$payload   = array(
			'page_uri'   => '/article',
			'view_token' => str_repeat( 'a', 32 ),
		);

		$without_referrer = $validator->validate_view( payload: $payload );
		self::assertNotNull( $without_referrer );
		self::assertNull( $without_referrer['referrer_url'] );

		$invalid_referrer = $validator->validate_view(
			payload: array_replace( $payload, array( 'referrer_url' => 'https://user:pass@example.org/private' ) )
		);
		self::assertNotNull( $invalid_referrer );
		self::assertNull( $invalid_referrer['referrer_url'] );
	}

	/**
	 * The browser cannot assert event type or server-owned identifiers.
	 *
	 * @return void
	 */
	public function test_view_rejects_identity_object_and_event_claims(): void {
		$validator = new Journey_Browser_Payload_Validator();
		$payload   = array(
			'page_uri'   => '/product/widget',
			'view_token' => str_repeat( 'a', 32 ),
		);

		foreach ( array( 'event_type', 'post_id', 'product_id', 'variation_id', 'order_id', 'visitor_id', 'session_id', 'user_id', 'source', 'active_ms', 'idempotency_key' ) as $field ) {
			self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( $field => 1 ) ) ), $field );
		}
	}

	/**
	 * Only a bounded relative view URI and a canonical random token are valid.
	 *
	 * @return void
	 */
	public function test_view_rejects_bad_uri_token_and_payload_types(): void {
		$validator = new Journey_Browser_Payload_Validator();
		$payload   = array(
			'page_uri'   => '/article',
			'view_token' => str_repeat( 'a', 32 ),
		);

		foreach ( array( 'https://example.org/article', '//example.org/article', '/bad path', "/bad\npath", '/' . str_repeat( 'x', 8192 ) ) as $uri ) {
			self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'page_uri' => $uri ) ) ) );
		}

		foreach ( array( '', str_repeat( 'A', 32 ), str_repeat( 'a', 31 ), str_repeat( 'g', 32 ) ) as $token ) {
			self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'view_token' => $token ) ) ) );
			self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'checkout_entry_token' => $token ) ) ) );
		}
		self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'checkout_entry_token' => null ) ) ) );
		self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'checkout_entry_token' => 42 ) ) ) );

		self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'referrer_url' => array() ) ) ) );
		self::assertNull( $validator->validate_view( payload: array_replace( $payload, array( 'referrer_url' => str_repeat( 'x', 8193 ) ) ) ) );
		self::assertNull( $validator->validate_view( payload: array( 'page_uri' => '/article' ) ) );
		self::assertNull( $validator->validate_view( payload: 'not an object' ) );
	}

	/**
	 * Duration inputs remain strictly typed and finite.
	 *
	 * @return void
	 */
	public function test_duration_accepts_only_positive_event_ids_and_bounded_totals(): void {
		$validator = new Journey_Browser_Payload_Validator();
		self::assertSame(
			array(
				'event_id'        => 15,
				'total_active_ms' => 0,
			),
			$validator->validate_duration(
				payload: array(
					'event_id'        => 15,
					'total_active_ms' => 0,
				)
			)
		);
		self::assertNotNull(
			$validator->validate_duration(
				payload: array(
					'event_id'        => 15,
					'total_active_ms' => 2147483647,
				)
			)
		);

		foreach (
			array(
				array(
					'event_id'        => 0,
					'total_active_ms' => 100,
				),
				array(
					'event_id'        => '15',
					'total_active_ms' => 100,
				),
				array(
					'event_id'        => 15,
					'total_active_ms' => '100',
				),
				array(
					'event_id'        => 15,
					'total_active_ms' => -1,
				),
				array(
					'event_id'        => 15,
					'total_active_ms' => 2147483648,
				),
				array(
					'event_id'        => 15,
					'total_active_ms' => 100,
					'visitor_id'      => 2,
				),
				array( 'event_id' => 15 ),
			) as $invalid
		) {
			self::assertNull( $validator->validate_duration( payload: $invalid ) );
		}
		self::assertNull( $validator->validate_duration( payload: null ) );
	}
}
