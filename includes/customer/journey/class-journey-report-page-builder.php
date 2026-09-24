<?php
/**
 * Customer Journey report page grouping.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;

/**
 * Organize one bounded event page by local date and session.
 *
 * All totals cover only the supplied events. A page may stop partway through
 * a date or session, so callers must label these as loaded-event totals. Full
 * date totals require a separate query over every matching event in the date.
 *
 * @phpstan-import-type ReportEvent from Journey_Report_Repository
 * @phpstan-import-type SessionContext from Journey_Report_Session_Repository
 * @phpstan-import-type SummaryDelta from Journey_Event_Summary_Delta
 * @phpstan-type Totals array{
 *     sessions:int,
 *     pages_viewed:int,
 *     products_viewed:int,
 *     active_ms:int,
 *     products_added:int,
 *     products_removed:int,
 *     checkouts_started:int,
 *     orders_created:int
 * }
 * @phpstan-type SessionGroup array{
 *     session_id:int,
 *     context:SessionContext|null,
 *     totals:Totals,
 *     events:list<ReportEvent>
 * }
 * @phpstan-type DayGroup array{
 *     date:string,
 *     totals:Totals,
 *     sessions:array<int,SessionGroup>
 * }
 * @phpstan-type ReportPage array{
 *     totals_scope:'loaded_events',
 *     days:list<DayGroup>
 * }
 */
final class Journey_Report_Page_Builder {
	/**
	 * Build latest-first days and sessions with oldest-first events in each.
	 *
	 * The event repository provides newest-first rows with ID as the tie breaker.
	 * Missing session context is allowed, but a context for a different visitor
	 * invalidates the page. Event user IDs stay attached to their original rows
	 * so a later presenter can distinguish anonymous from authenticated activity.
	 *
	 * @param array        $events   One bounded, identity-scoped event page.
	 * @param array        $contexts Session context keyed by session ID.
	 * @param DateTimeZone $timezone Site timezone for calendar-day grouping.
	 * @return ReportPage|null Grouped page or null for inconsistent input.
	 * @phpstan-param list<ReportEvent> $events
	 * @phpstan-param array<int,SessionContext> $contexts
	 */
	public function build( array $events, array $contexts, DateTimeZone $timezone ): ?array {
		if ( count( $events ) > Journey_Report_Repository::MAX_PAGE_SIZE ) {
			return null;
		}

		$days        = array();
		$previous_at = null;
		$previous_id = null;

		foreach ( $events as $event ) {
			$occurred = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $event['occurred_at'], new DateTimeZone( 'UTC' ) );
			if ( false === $occurred || $occurred->format( 'Y-m-d H:i:s' ) !== $event['occurred_at'] ||
				( null !== $previous_at && ( $event['occurred_at'] > $previous_at || ( $event['occurred_at'] === $previous_at && $event['id'] >= $previous_id ) ) ) ) {
				return null;
			}

			$context = $contexts[ $event['session_id'] ] ?? null;
			if ( null !== $context && ( $context['id'] !== $event['session_id'] || $context['visitor_id'] !== $event['visitor_id'] ) ) {
				return null;
			}

			$delta = array();
			if ( Journey_Event_Type::is_supported( value: $event['event_type'] ) ) {
				$delta = Journey_Event_Summary_Delta::for_event(
					event_type: $event['event_type'],
					quantity: $event['quantity'],
					active_ms: $event['active_ms']
				);
				if ( null === $delta ) {
					return null;
				}
			}

			$date       = $occurred->setTimezone( $timezone )->format( 'Y-m-d' );
			$session_id = $event['session_id'];
			$day        = $days[ $date ] ?? $this->new_day( date: $date );
			if ( ! isset( $day['sessions'][ $session_id ] ) ) {
				$day['totals']['sessions'] += 1;
			}

			$session        = $day['sessions'][ $session_id ] ?? $this->new_session( session_id: $session_id, context: $context );
			$day_totals     = $this->add_delta( totals: $day['totals'], delta: $delta );
			$session_totals = $this->add_delta( totals: $session['totals'], delta: $delta );
			if ( null === $day_totals || null === $session_totals ) {
				return null;
			}

			$session['totals']              = $session_totals;
			$session['events'][]            = $event;
			$day['totals']                  = $day_totals;
			$day['sessions'][ $session_id ] = $session;
			$days[ $date ]                  = $day;
			$previous_at                    = $event['occurred_at'];
			$previous_id                    = $event['id'];
		}

		foreach ( $days as $date => $day ) {
			foreach ( $day['sessions'] as $session_id => $session ) {
				$session['events']              = array_reverse( $session['events'] );
				$day['sessions'][ $session_id ] = $session;
			}

			$day['sessions'] = array_values( $day['sessions'] );
			$days[ $date ]   = $day;
		}

		return array(
			'totals_scope' => 'loaded_events',
			'days'         => array_values( $days ),
		);
	}

	/**
	 * Start one local-date group for the loaded events.
	 *
	 * @param string $date Calendar date in the site timezone.
	 * @return array New date group.
	 * @phpstan-return DayGroup
	 */
	private function new_day( string $date ): array {
		return array(
			'date'     => $date,
			'totals'   => $this->empty_totals(),
			'sessions' => array(),
		);
	}

	/**
	 * Start one session group within a local date.
	 *
	 * @param int        $session_id Internal session ID.
	 * @param array|null $context    Session context, when present.
	 * @return array New session group.
	 * @phpstan-param SessionContext|null $context
	 * @phpstan-return SessionGroup
	 */
	private function new_session( int $session_id, ?array $context ): array {
		$totals             = $this->empty_totals();
		$totals['sessions'] = 1;

		return array(
			'session_id' => $session_id,
			'context'    => $context,
			'totals'     => $totals,
			'events'     => array(),
		);
	}

	/**
	 * Start one page subtotal without borrowing whole-session counters.
	 *
	 * @return Totals Zero-valued subtotal.
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

	/**
	 * Add the v1 event counters without overflowing total visible time.
	 *
	 * @param array $totals Existing subtotal.
	 * @param array $delta  Valid event summary delta.
	 * @return array|null Updated subtotal or null on overflow.
	 * @phpstan-param Totals $totals
	 * @phpstan-param SummaryDelta $delta
	 * @phpstan-return Totals|null
	 */
	private function add_delta( array $totals, array $delta ): ?array {
		$active_ms = $delta['active_ms'] ?? 0;
		if ( $totals['active_ms'] > PHP_INT_MAX - $active_ms ) {
			return null;
		}

		$totals['pages_viewed']      += $delta['page_view_count'] ?? 0;
		$totals['products_viewed']   += $delta['product_view_count'] ?? 0;
		$totals['products_added']    += $delta['cart_add_count'] ?? 0;
		$totals['products_removed']  += $delta['cart_remove_count'] ?? 0;
		$totals['checkouts_started'] += $delta['checkout_started_count'] ?? 0;
		$totals['orders_created']    += $delta['order_created_count'] ?? 0;
		$totals['active_ms']         += $active_ms;

		return $totals;
	}
}
