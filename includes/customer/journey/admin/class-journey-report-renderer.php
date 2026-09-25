<?php
/**
 * Customer Journey report presentation.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Journey_Event_Type;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;

/**
 * Render one bounded Customer Journey page using WordPress admin markup.
 *
 * Stored paths and attribution values are treated as untrusted display text.
 * IDs identify existing WordPress and WooCommerce records without copying
 * customer, product, or order details into Journey storage.
 *
 * @phpstan-import-type ReportPage from Journey_Report_Page_Builder
 * @phpstan-import-type Totals from Journey_Report_Page_Builder
 * @phpstan-import-type SessionGroup from Journey_Report_Page_Builder
 * @phpstan-import-type ReportEvent from \Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository
 * @phpstan-import-type SessionContext from \Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository
 */
final class Journey_Report_Renderer {
	/** Report filters retained after deleting a session. */
	private const DELETE_RETURN_FIELDS = array(
		'journey_subject',
		'journey_user_id',
		'journey_visitor_id',
		'journey_from',
		'journey_to',
	);

	/**
	 * Render a validated report page.
	 *
	 * Customer reports distinguish authenticated events from earlier anonymous
	 * activity that was subsequently linked. Never-linked visitor reports use
	 * the plain Anonymous label.
	 *
	 * @param array        $page            Grouped report page.
	 * @param DateTimeZone $timezone        Site timezone.
	 * @param bool         $customer_report Whether the subject is a customer.
	 * @return void
	 * @phpstan-param ReportPage $page
	 */
	public function render( array $page, DateTimeZone $timezone, bool $customer_report ): void {
		$rendered_session_ids = array();
		?>
		<div class="shurloc-journey-report">
			<p class="description">
				<?php echo esc_html__( 'Totals below cover only the events loaded on this page.', 'shurloc-site-tools' ); ?>
			</p>

			<?php if ( array() === $page['days'] ) : ?>
				<p><?php echo esc_html__( 'No Journey events were found in this date range.', 'shurloc-site-tools' ); ?></p>
			<?php else : ?>
				<?php foreach ( $page['days'] as $day ) : ?>
					<section class="shurloc-journey-day">
						<h3><?php echo esc_html( $this->format_date( date: $day['date'] ) ); ?></h3>
						<?php $this->render_totals( totals: $day['totals'] ); ?>

						<?php foreach ( $day['sessions'] as $session ) : ?>
							<?php
							$render_delete                                  = ! isset( $rendered_session_ids[ $session['session_id'] ] );
							$rendered_session_ids[ $session['session_id'] ] = true;
							$this->render_session(
								session: $session,
								timezone: $timezone,
								customer_report: $customer_report,
								render_delete: $render_delete
							);
							?>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render loaded-event totals for one date.
	 *
	 * @param array $totals Date totals.
	 * @return void
	 * @phpstan-param Totals $totals
	 */
	private function render_totals( array $totals ): void {
		$items = array(
			__( 'Sessions', 'shurloc-site-tools' )         => (string) $totals['sessions'],
			__( 'Pages viewed', 'shurloc-site-tools' )     => (string) $totals['pages_viewed'],
			__( 'Products viewed', 'shurloc-site-tools' )  => (string) $totals['products_viewed'],
			__( 'Active time', 'shurloc-site-tools' )      => $this->format_duration( milliseconds: $totals['active_ms'] ),
			__( 'Products added', 'shurloc-site-tools' )   => (string) $totals['products_added'],
			__( 'Products removed', 'shurloc-site-tools' ) => (string) $totals['products_removed'],
			__( 'Checkouts started', 'shurloc-site-tools' ) => (string) $totals['checkouts_started'],
			__( 'Orders created', 'shurloc-site-tools' )   => (string) $totals['orders_created'],
		);
		?>
		<ul class="subsubsub shurloc-journey-totals">
			<?php foreach ( $items as $label => $value ) : ?>
				<li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
			<?php endforeach; ?>
		</ul>
		<div class="clear"></div>
		<?php
	}

	/**
	 * Render one session group and its chronological events.
	 *
	 * @param array        $session         Session group.
	 * @param DateTimeZone $timezone        Site timezone.
	 * @param bool         $customer_report Whether the subject is a customer.
	 * @param bool         $render_delete   Whether to render this session's deletion control.
	 * @return void
	 * @phpstan-param SessionGroup $session
	 */
	private function render_session( array $session, DateTimeZone $timezone, bool $customer_report, bool $render_delete ): void {
		$first_event = $session['events'][0];
		$last_event  = $session['events'][ count( $session['events'] ) - 1 ];
		$first_time  = $this->format_time( utc: $first_event['occurred_at'], timezone: $timezone );
		$last_time   = $this->format_time( utc: $last_event['occurred_at'], timezone: $timezone );
		$time_range  = $first_time === $last_time ? $first_time : $first_time . '–' . $last_time;

		$heading = sprintf(
			/* translators: 1: internal session ID, 2: loaded event time range, 3: loaded active duration. */
			__( 'Session %1$d — %2$s — %3$s active', 'shurloc-site-tools' ),
			$session['session_id'],
			$time_range,
			$this->format_duration( milliseconds: $session['totals']['active_ms'] )
		);
		?>
		<article class="shurloc-journey-session">
			<h4><?php echo esc_html( $heading ); ?></h4>
			<?php if ( null !== $session['context'] ) : ?>
				<?php $this->render_attribution( context: $session['context'] ); ?>
			<?php endif; ?>

			<table class="widefat striped shurloc-journey-events">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Time', 'shurloc-site-tools' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Item', 'shurloc-site-tools' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Activity', 'shurloc-site-tools' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Identity', 'shurloc-site-tools' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $session['events'] as $event ) : ?>
						<tr>
							<td><?php echo esc_html( $this->format_time( utc: $event['occurred_at'], timezone: $timezone ) ); ?></td>
							<td><?php echo esc_html( $this->event_item( event: $event ) ); ?></td>
							<td><?php echo esc_html( $this->event_activity( event: $event ) ); ?></td>
							<td><?php echo esc_html( $this->identity_label( authenticated: null !== $event['user_id_at_event'], customer_report: $customer_report ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $render_delete ) : ?>
				<?php $this->render_delete_control( session_id: $session['session_id'] ); ?>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * Render a deliberate, nonce-protected deletion control for one session.
	 *
	 * The details disclosure requires an administrator to expose the permanent
	 * action before submitting it, without depending on JavaScript.
	 *
	 * @param int $session_id Journey session ID.
	 * @return void
	 */
	private function render_delete_control( int $session_id ): void {
		?>
		<details class="shurloc-journey-delete">
			<summary><?php echo esc_html__( 'Delete this journey', 'shurloc-site-tools' ); ?></summary>
			<p><?php echo esc_html__( 'This permanently deletes this session and all of its recorded events. This action cannot be undone.', 'shurloc-site-tools' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Journey_Report_Controller::DELETE_ACTION ); ?>">
				<input type="hidden" name="journey_session_id" value="<?php echo esc_attr( (string) $session_id ); ?>">
				<?php $this->render_delete_return_fields(); ?>
				<?php wp_nonce_field( Journey_Report_Controller::DELETE_ACTION ); ?>
				<button type="submit" class="button-link-delete"><?php echo esc_html__( 'Delete journey permanently', 'shurloc-site-tools' ); ?></button>
			</form>
		</details>
		<?php
	}

	/**
	 * Retain only report-selection fields after a deletion redirect.
	 *
	 * Event pagination cursors are intentionally omitted because the deleted
	 * session can invalidate the current event page.
	 *
	 * @return void
	 */
	private function render_delete_return_fields(): void {
		foreach ( self::DELETE_RETURN_FIELDS as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filters are revalidated by the deletion controller.
			$value = $_GET[ $field ] ?? '';
			if ( ! is_string( $value ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $value ) );
			if ( '' === $value ) {
				continue;
			}
			?>
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}
	}

	/**
	 * Render session landing and campaign context without arbitrary metadata.
	 *
	 * @param array $context Validated session context.
	 * @return void
	 * @phpstan-param SessionContext $context
	 */
	private function render_attribution( array $context ): void {
		$fields = array(
			__( 'Landing page', 'shurloc-site-tools' ) => $context['landing_path'],
			__( 'Referrer', 'shurloc-site-tools' )     => $context['referrer_host'],
			__( 'UTM source', 'shurloc-site-tools' )   => $context['utm_source'],
			__( 'UTM medium', 'shurloc-site-tools' )   => $context['utm_medium'],
			__( 'UTM campaign', 'shurloc-site-tools' ) => $context['utm_campaign'],
			__( 'UTM term', 'shurloc-site-tools' )     => $context['utm_term'],
			__( 'UTM content', 'shurloc-site-tools' )  => $context['utm_content'],
		);

		$fields = array_filter( $fields, static fn ( ?string $value ): bool => null !== $value && '' !== $value );
		if ( array() === $fields ) {
			return;
		}
		?>
		<dl class="shurloc-journey-attribution">
			<?php foreach ( $fields as $label => $value ) : ?>
				<dt><?php echo esc_html( $label ); ?></dt>
				<dd><?php echo esc_html( $value ); ?></dd>
			<?php endforeach; ?>
		</dl>
		<?php
	}

	/**
	 * Describe the record affected by one event using stored IDs and paths.
	 *
	 * @param array $event Validated report event.
	 * @return string Display item.
	 * @phpstan-param ReportEvent $event
	 */
	private function event_item( array $event ): string {
		if ( null !== $event['order_id'] ) {
			/* translators: %d: WooCommerce order ID. */
			return sprintf( __( 'Order #%d', 'shurloc-site-tools' ), $event['order_id'] );
		}

		if ( null !== $event['product_id'] ) {
			/* translators: %d: WooCommerce product ID. */
			$item = sprintf( __( 'Product #%d', 'shurloc-site-tools' ), $event['product_id'] );
			if ( null !== $event['variation_id'] ) {
				/* translators: %d: WooCommerce variation ID. */
				$item .= ' — ' . sprintf( __( 'Variation #%d', 'shurloc-site-tools' ), $event['variation_id'] );
			}

			return $item;
		}

		if ( null !== $event['page_path'] && '' !== $event['page_path'] ) {
			return $event['page_path'];
		}

		if ( null !== $event['post_id'] ) {
			/* translators: %d: WordPress post ID. */
			return sprintf( __( 'Page #%d', 'shurloc-site-tools' ), $event['post_id'] );
		}

		return '—';
	}

	/**
	 * Describe one event while preserving ORDER_CREATED semantics.
	 *
	 * @param array $event Validated report event.
	 * @return string Display action.
	 * @phpstan-param ReportEvent $event
	 */
	private function event_activity( array $event ): string {
		if ( Journey_Event_Type::PAGE_VIEW === $event['event_type'] || Journey_Event_Type::PRODUCT_VIEW === $event['event_type'] ) {
			/* translators: %s: estimated visible duration. */
			return sprintf( __( 'Viewed — %s active', 'shurloc-site-tools' ), $this->format_duration( milliseconds: $event['active_ms'] ) );
		}

		if ( Journey_Event_Type::ADD_TO_CART === $event['event_type'] ) {
			/* translators: %s: product quantity. */
			return sprintf( __( 'Added to cart — Qty %s', 'shurloc-site-tools' ), $event['quantity'] );
		}

		if ( Journey_Event_Type::REMOVE_FROM_CART === $event['event_type'] ) {
			/* translators: %s: product quantity. */
			return sprintf( __( 'Removed from cart — Qty %s', 'shurloc-site-tools' ), $event['quantity'] );
		}

		if ( Journey_Event_Type::CHECKOUT_STARTED === $event['event_type'] ) {
			return __( 'Checkout started', 'shurloc-site-tools' );
		}

		if ( Journey_Event_Type::ORDER_CREATED === $event['event_type'] ) {
			return __( 'Order record created', 'shurloc-site-tools' );
		}

		return ucwords( strtolower( str_replace( '_', ' ', $event['event_type'] ) ) );
	}

	/**
	 * Label identity state without exposing a WordPress user ID.
	 *
	 * @param bool $authenticated   Whether the event had a user at capture.
	 * @param bool $customer_report Whether the subject is a customer.
	 * @return string Identity label.
	 */
	private function identity_label( bool $authenticated, bool $customer_report ): string {
		if ( $authenticated ) {
			return __( 'Authenticated', 'shurloc-site-tools' );
		}

		return $customer_report
			? __( 'Anonymous, later linked', 'shurloc-site-tools' )
			: __( 'Anonymous', 'shurloc-site-tools' );
	}

	/**
	 * Format a local calendar date supplied by the page builder.
	 *
	 * @param string $date Local Y-m-d date.
	 * @return string Display date.
	 */
	private function format_date( string $date ): string {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return false === $parsed ? $date : $parsed->format( 'F j, Y' );
	}

	/**
	 * Convert a validated UTC database timestamp to the site timezone.
	 *
	 * @param string       $utc      UTC MySQL datetime.
	 * @param DateTimeZone $timezone Site timezone.
	 * @return string Local display time.
	 */
	private function format_time( string $utc, DateTimeZone $timezone ): string {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );
		return false === $parsed ? $utc : $parsed->setTimezone( $timezone )->format( 'g:i a' );
	}

	/**
	 * Format estimated active milliseconds without overstating partial seconds.
	 *
	 * @param int $milliseconds Estimated visible time.
	 * @return string Compact duration.
	 */
	private function format_duration( int $milliseconds ): string {
		if ( 0 === $milliseconds ) {
			return '0s';
		}

		if ( 1000 > $milliseconds ) {
			return '<1s';
		}

		$seconds = intdiv( $milliseconds, 1000 );
		$hours   = intdiv( $seconds, 3600 );
		$minutes = intdiv( $seconds % 3600, 60 );
		$seconds = $seconds % 60;

		if ( 0 < $hours ) {
			return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
		}

		if ( 0 < $minutes ) {
			return $minutes . 'm ' . $seconds . 's';
		}

		return $seconds . 's';
	}
}
