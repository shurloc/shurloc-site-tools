<?php
/**
 * Tests for Customer Journey report presentation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;

/**
 * Verify safe, identity-aware Journey report markup.
 *
 * @phpstan-import-type ReportPage from Journey_Report_Page_Builder
 * @phpstan-import-type ReportEvent from Journey_Report_Repository
 * @phpstan-import-type SessionContext from Journey_Report_Session_Repository
 * @phpstan-import-type Totals from Journey_Report_Page_Builder
 */
final class JourneyReportRendererTest extends TestCase {
	/** Reset request and nonce state used by deletion controls. */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_nonce_fields'] = array();
		$_GET                                 = array();
	}

	/** Restore shared request and nonce state. */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_nonce_fields'] = array();
		$_GET                                 = array();

		parent::tearDown();
	}

	/**
	 * Customer reports render totals, attribution, chronology, and v1 actions.
	 *
	 * @return void
	 */
	public function test_renders_customer_journey_page(): void {
		$events = array(
			$this->event( id: 1, event_type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 12:00:00', page_path: '/welcome/<script>alert(1)</script>', active_ms: 500 ),
			$this->event( id: 2, event_type: Journey_Event_Type::PRODUCT_VIEW, occurred_at: '2026-09-18 12:01:00', product_id: 123, active_ms: 62000, user_id: 7 ),
			$this->event( id: 3, event_type: Journey_Event_Type::ADD_TO_CART, occurred_at: '2026-09-18 12:02:00', product_id: 123, variation_id: 456, quantity: '2.0000', user_id: 7 ),
			$this->event( id: 4, event_type: Journey_Event_Type::REMOVE_FROM_CART, occurred_at: '2026-09-18 12:03:00', product_id: 123, quantity: '1.0000', user_id: 7 ),
			$this->event( id: 5, event_type: Journey_Event_Type::CHECKOUT_STARTED, occurred_at: '2026-09-18 12:04:00', page_path: '/checkout', user_id: 7 ),
			$this->event( id: 6, event_type: Journey_Event_Type::ORDER_CREATED, occurred_at: '2026-09-18 12:05:00', order_id: 50123, user_id: 7 ),
		);
		$page   = $this->page(
			events: $events,
			context: $this->context(),
			totals: array(
				'sessions'          => 1,
				'pages_viewed'      => 2,
				'products_viewed'   => 1,
				'active_ms'         => 62500,
				'products_added'    => 1,
				'products_removed'  => 1,
				'checkouts_started' => 1,
				'orders_created'    => 1,
			)
		);

		$output = $this->render( page: $page, customer_report: true );

		self::assertStringContainsString( 'September 18, 2026', $output );
		self::assertStringContainsString( '<strong>Sessions:</strong> 1', $output );
		self::assertStringContainsString( '<strong>Pages viewed:</strong> 2', $output );
		self::assertStringContainsString( '<strong>Active time:</strong> 1m 2s', $output );
		self::assertStringContainsString( 'Session 9 — 5:00 am–5:05 am — 1m 2s active', $output );
		self::assertStringContainsString( '&lt;script&gt;campaign&lt;/script&gt;', $output );
		self::assertStringContainsString( '/welcome/&lt;script&gt;alert(1)&lt;/script&gt;', $output );
		self::assertStringNotContainsString( '<script>', $output );
		self::assertStringContainsString( 'Viewed — &lt;1s active', $output );
		self::assertStringContainsString( 'Product #123 — Variation #456', $output );
		self::assertStringContainsString( 'Added to cart — Qty 2.0000', $output );
		self::assertStringContainsString( 'Removed from cart — Qty 1.0000', $output );
		self::assertStringContainsString( 'Checkout started', $output );
		self::assertStringContainsString( 'Order #50123', $output );
		self::assertStringContainsString( 'Order record created', $output );
		self::assertStringNotContainsString( 'Order paid', $output );
		self::assertStringContainsString( 'Anonymous, later linked', $output );
		self::assertStringContainsString( 'Authenticated', $output );
		self::assertSame( 1, substr_count( $output, 'name="journey_session_id"' ) );
		self::assertLessThan( strpos( $output, 'Order record created' ), strpos( $output, 'Viewed — &lt;1s active' ) );
	}

	/**
	 * Every rendered session has a deliberate deletion form with return filters.
	 *
	 * @return void
	 */
	public function test_renders_nonce_protected_session_deletion_control(): void {
		$_GET               = array(
			'journey_subject'   => 'customer',
			'journey_user_id'   => '7',
			'journey_from'      => '2026-09-01',
			'journey_to'        => '2026-09-21',
			'journey_before_at' => '2026-09-18 12:00:00',
			'unrelated'         => '<script>private</script>',
		);
		$event              = $this->event( id: 1, event_type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 12:00:00', page_path: '/' );
		$totals             = $this->empty_totals();
		$totals['sessions'] = 1;

		$output = $this->render( page: $this->page( events: array( $event ), context: null, totals: $totals ), customer_report: true );

		self::assertStringContainsString( '<details class="shurloc-journey-delete">', $output );
		self::assertStringContainsString( '>Delete this journey</summary>', $output );
		self::assertStringContainsString( 'This action cannot be undone.', $output );
		self::assertStringContainsString( 'method="post" action="https://example.com/wp-admin/admin-post.php"', $output );
		self::assertStringContainsString( 'name="action" value="shurloc_delete_journey_session"', $output );
		self::assertStringContainsString( 'name="journey_session_id" value="9"', $output );
		self::assertStringContainsString( 'name="journey_subject" value="customer"', $output );
		self::assertStringContainsString( 'name="journey_user_id" value="7"', $output );
		self::assertStringContainsString( 'name="journey_from" value="2026-09-01"', $output );
		self::assertStringContainsString( 'name="journey_to" value="2026-09-21"', $output );
		self::assertStringContainsString( 'name="_wpnonce" value="test-nonce-shurloc_delete_journey_session"', $output );
		self::assertStringContainsString( 'class="button-link-delete">Delete journey permanently</button>', $output );
		self::assertStringNotContainsString( 'journey_before_at', $output );
		self::assertStringNotContainsString( 'unrelated', $output );
		self::assertStringNotContainsString( '<script>', $output );
		self::assertSame(
			array(
				array(
					'action' => Journey_Report_Controller::DELETE_ACTION,
					'name'   => '_wpnonce',
				),
			),
			$GLOBALS['shurloc_test_nonce_fields']
		);
	}

	/**
	 * A session spanning local dates receives one deletion control.
	 *
	 * @return void
	 */
	public function test_renders_one_deletion_control_for_a_session_spanning_dates(): void {
		$event              = $this->event( id: 1, event_type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 12:00:00', page_path: '/' );
		$totals             = $this->empty_totals();
		$totals['sessions'] = 1;
		$page               = $this->page( events: array( $event ), context: null, totals: $totals );
		$second_day         = $page['days'][0];
		$second_day['date'] = '2026-09-19';
		$page['days'][]     = $second_day;

		$output = $this->render( page: $page, customer_report: false );

		self::assertSame( 1, substr_count( $output, 'name="journey_session_id" value="9"' ) );
		self::assertCount( 1, $GLOBALS['shurloc_test_nonce_fields'] );
	}

	/**
	 * Never-linked visitor reports use an anonymous label without linkage claims.
	 *
	 * @return void
	 */
	public function test_renders_never_linked_visitor_identity(): void {
		$event                  = $this->event( id: 1, event_type: Journey_Event_Type::PAGE_VIEW, occurred_at: '2026-09-18 12:00:00', page_path: '/' );
		$totals                 = $this->empty_totals();
		$totals['sessions']     = 1;
		$totals['pages_viewed'] = 1;
		$output                 = $this->render( page: $this->page( events: array( $event ), context: null, totals: $totals ), customer_report: false );

		self::assertStringContainsString( '<td>Anonymous</td>', $output );
		self::assertStringNotContainsString( 'later linked', $output );
		self::assertStringNotContainsString( 'shurloc-journey-attribution', $output );
	}

	/**
	 * Empty pages explain that no events matched the bounded date range.
	 *
	 * @return void
	 */
	public function test_renders_empty_page(): void {
		$output = $this->render(
			page: array(
				'totals_scope' => 'loaded_events',
				'days'         => array(),
			),
			customer_report: true
		);

		self::assertStringContainsString( 'Totals below cover only the events loaded on this page.', $output );
		self::assertStringContainsString( 'No Journey events were found in this date range.', $output );
		self::assertStringNotContainsString( 'shurloc-journey-events', $output );
		self::assertStringNotContainsString( 'shurloc-journey-delete', $output );
		self::assertSame( array(), $GLOBALS['shurloc_test_nonce_fields'] );
	}

	/**
	 * Future event types remain readable without changing the report schema.
	 *
	 * @return void
	 */
	public function test_renders_future_event_type(): void {
		$event              = $this->event( id: 1, event_type: 'ORDER_PAID', occurred_at: '2026-09-18 12:00:00', order_id: 50123, user_id: 7 );
		$totals             = $this->empty_totals();
		$totals['sessions'] = 1;

		$output = $this->render( page: $this->page( events: array( $event ), context: null, totals: $totals ), customer_report: true );

		self::assertStringContainsString( 'Order Paid', $output );
		self::assertStringContainsString( 'Order #50123', $output );
	}

	/**
	 * Capture the renderer output.
	 *
	 * @param array $page            Report page.
	 * @param bool  $customer_report Whether the subject is a customer.
	 * @return string Rendered markup.
	 * @phpstan-param ReportPage $page
	 */
	private function render( array $page, bool $customer_report ): string {
		ob_start();
		( new Journey_Report_Renderer() )->render(
			page: $page,
			timezone: new DateTimeZone( 'America/Los_Angeles' ),
			customer_report: $customer_report
		);

		return (string) ob_get_clean();
	}

	/**
	 * Build one report page with one date and session.
	 *
	 * @param array      $events  Chronological session events.
	 * @param array|null $context Session context.
	 * @param array      $totals  Loaded-event totals.
	 * @return array Report page.
	 * @phpstan-param list<ReportEvent> $events
	 * @phpstan-param SessionContext|null $context
	 * @phpstan-param Totals $totals
	 * @phpstan-return ReportPage
	 */
	private function page( array $events, ?array $context, array $totals ): array {
		return array(
			'totals_scope' => 'loaded_events',
			'days'         => array(
				array(
					'date'     => '2026-09-18',
					'totals'   => $totals,
					'sessions' => array(
						array(
							'session_id' => 9,
							'context'    => $context,
							'totals'     => $totals,
							'events'     => $events,
						),
					),
				),
			),
		);
	}

	/**
	 * Build a representative report event.
	 *
	 * @param int         $id           Event ID.
	 * @param string      $event_type   Event type.
	 * @param string      $occurred_at  UTC event timestamp.
	 * @param string|null $page_path    Stored page path.
	 * @param int|null    $product_id   WooCommerce product ID.
	 * @param int|null    $variation_id WooCommerce variation ID.
	 * @param string|null $quantity     Product quantity.
	 * @param int|null    $order_id     WooCommerce order ID.
	 * @param int         $active_ms    Estimated visible duration.
	 * @param int|null    $user_id      User at event capture.
	 * @return array Report event.
	 * @phpstan-return ReportEvent
	 */
	private function event(
		int $id,
		string $event_type,
		string $occurred_at,
		?string $page_path = null,
		?int $product_id = null,
		?int $variation_id = null,
		?string $quantity = null,
		?int $order_id = null,
		int $active_ms = 0,
		?int $user_id = null
	): array {
		return array(
			'id'                => $id,
			'session_id'        => 9,
			'visitor_id'        => 3,
			'user_id_at_event'  => $user_id,
			'event_type'        => $event_type,
			'occurred_at'       => $occurred_at,
			'page_path'         => $page_path,
			'post_id'           => null,
			'product_id'        => $product_id,
			'variation_id'      => $variation_id,
			'quantity'          => $quantity,
			'order_id'          => $order_id,
			'related_object_id' => null,
			'active_ms'         => $active_ms,
			'source'            => null,
		);
	}

	/**
	 * Build representative session attribution.
	 *
	 * @return array Session context.
	 * @phpstan-return SessionContext
	 */
	private function context(): array {
		return array(
			'id'                  => 9,
			'visitor_id'          => 3,
			'identity_period_id'  => 4,
			'user_id_at_start'    => null,
			'began_authenticated' => false,
			'started_at'          => '2026-09-18 12:00:00',
			'last_activity_at'    => '2026-09-18 12:05:00',
			'ended_at'            => null,
			'landing_path'        => '/welcome',
			'referrer_host'       => 'example.org',
			'utm_source'          => '<script>campaign</script>',
			'utm_medium'          => 'email',
			'utm_campaign'        => 'autumn',
			'utm_term'            => null,
			'utm_content'         => null,
		);
	}

	/**
	 * Return zero-valued page totals.
	 *
	 * @return array Empty totals.
	 * @phpstan-return Totals
	 */
	private function empty_totals(): array {
		return array(
			'sessions'          => 0,
			'pages_viewed'      => 0,
			'products_viewed'   => 0,
			'active_ms'         => 0,
			'products_added'    => 0,
			'products_removed'  => 0,
			'checkouts_started' => 0,
			'orders_created'    => 0,
		);
	}
}
