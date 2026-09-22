<?php
/**
 * Customer Journey report administration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Journey_Report_Page_Builder;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Session_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Report_Visitor_Repository;
use WP_User;

/**
 * Coordinate bounded customer and never-linked visitor journey reports.
 *
 * @phpstan-import-type RecentSubject from Journey_Report_Visitor_Repository
 */
final class Journey_Report_Controller {
	/** Customer Tools page slug. */
	public const PAGE_SLUG = 'shurloc-site-tools-customers';

	/** Journey report tab slug. */
	public const TAB_SLUG = 'journeys';

	/** Capability required to view behavioral reports. */
	public const CAPABILITY = 'manage_options';

	/** Events loaded in one report request. */
	private const EVENT_PAGE_SIZE = 50;

	/** Recent report subjects shown on the landing page. */
	private const RECENT_SUBJECT_LIMIT = 50;

	/** Anonymous visitors offered in one selector page. */
	private const VISITOR_PAGE_SIZE = 50;

	/**
	 * Event report reads.
	 *
	 * @var Journey_Report_Repository
	 */
	private Journey_Report_Repository $report_repository;

	/**
	 * Session context reads.
	 *
	 * @var Journey_Report_Session_Repository
	 */
	private Journey_Report_Session_Repository $session_repository;

	/**
	 * Anonymous visitor selector reads.
	 *
	 * @var Journey_Report_Visitor_Repository
	 */
	private Journey_Report_Visitor_Repository $visitor_repository;

	/**
	 * Report page grouping.
	 *
	 * @var Journey_Report_Page_Builder
	 */
	private Journey_Report_Page_Builder $page_builder;

	/**
	 * Report presentation.
	 *
	 * @var Journey_Report_Renderer
	 */
	private Journey_Report_Renderer $renderer;

	/**
	 * Site timezone used for range boundaries and presentation.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $timezone;

	/**
	 * Current time used for default date fields.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/**
	 * Constructor.
	 *
	 * @param Journey_Report_Repository         $report_repository  Event report reads.
	 * @param Journey_Report_Session_Repository $session_repository Session context reads.
	 * @param Journey_Report_Visitor_Repository $visitor_repository Anonymous visitor reads.
	 * @param Journey_Report_Page_Builder       $page_builder       Report grouping.
	 * @param Journey_Report_Renderer           $renderer           Report presentation.
	 * @param DateTimeZone                      $timezone           Site timezone.
	 * @param DateTimeImmutable|null            $now                Current time override.
	 */
	public function __construct(
		Journey_Report_Repository $report_repository,
		Journey_Report_Session_Repository $session_repository,
		Journey_Report_Visitor_Repository $visitor_repository,
		Journey_Report_Page_Builder $page_builder,
		Journey_Report_Renderer $renderer,
		DateTimeZone $timezone,
		?DateTimeImmutable $now = null
	) {
		$this->report_repository  = $report_repository;
		$this->session_repository = $session_repository;
		$this->visitor_repository = $visitor_repository;
		$this->page_builder       = $page_builder;
		$this->renderer           = $renderer;
		$this->timezone           = $timezone;
		$this->now                = $now ?? new DateTimeImmutable( 'now', $timezone );
	}

	/**
	 * Register assets for WooCommerce's existing customer search control.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load WooCommerce's enhanced customer selector only on this report tab.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if (
			! current_user_can( self::CAPABILITY ) ||
			self::PAGE_SLUG !== $this->request_value( key: 'page' ) ||
			self::TAB_SLUG !== $this->request_value( key: 'tab' )
		) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
	}

	/**
	 * Render selector controls and, when requested, one report event page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->verify_permissions();

		$subject   = sanitize_key( $this->request_value( key: 'journey_subject' ) );
		$from_date = $this->request_value( key: 'journey_from' );
		$to_date   = $this->request_value( key: 'journey_to' );
		if ( '' === $from_date ) {
			$from_date = $this->now->setTimezone( $this->timezone )->modify( '-6 days' )->format( 'Y-m-d' );
		}
		if ( '' === $to_date ) {
			$to_date = $this->now->setTimezone( $this->timezone )->format( 'Y-m-d' );
		}

		$user_id    = $this->positive_integer( value: $this->request_value( key: 'journey_user_id' ) );
		$visitor_id = $this->positive_integer( value: $this->request_value( key: 'journey_visitor_id' ) );
		$user       = 'customer' === $subject && null !== $user_id ? get_userdata( $user_id ) : false;

		if ( '' === $subject ) {
			$this->render_landing( from_date: $from_date, to_date: $to_date );
			return;
		}

		$this->render_controls(
			from_date: $from_date,
			to_date: $to_date,
			selected_user: $user instanceof WP_User ? $user : null,
			selected_visitor_id: 'visitor' === $subject ? $visitor_id : null,
			visitors: array()
		);

		if ( 'customer' !== $subject && 'visitor' !== $subject ) {
			$this->render_error( message: __( 'Select a valid Journey report type.', 'shurloc-site-tools' ) );
			return;
		}

		if ( 'customer' === $subject && ( null === $user_id || ! $user instanceof WP_User ) ) {
			$this->render_error( message: __( 'Select a valid WordPress customer.', 'shurloc-site-tools' ) );
			return;
		}

		if ( 'visitor' === $subject && null === $visitor_id ) {
			$this->render_error( message: __( 'Select a valid anonymous visitor.', 'shurloc-site-tools' ) );
			return;
		}

		$range = $this->utc_range( from_date: $from_date, to_date: $to_date );
		if ( null === $range ) {
			$this->render_error( message: __( 'Choose a valid date range of no more than 31 days.', 'shurloc-site-tools' ) );
			return;
		}

		$cursor = $this->cursor( at_key: 'journey_before_at', id_key: 'journey_before_id' );
		if ( false === $cursor ) {
			$this->render_error( message: __( 'The Journey event page cursor is invalid.', 'shurloc-site-tools' ) );
			return;
		}

		$events = 'customer' === $subject
			? $this->report_repository->customer_events(
				user_id: $user_id,
				from_utc: $range['from_utc'],
				until_utc: $range['until_utc'],
				limit: self::EVENT_PAGE_SIZE,
				before_at: $cursor['at'],
				before_id: $cursor['id']
			)
			: $this->report_repository->anonymous_visitor_events(
				visitor_id: $visitor_id,
				from_utc: $range['from_utc'],
				until_utc: $range['until_utc'],
				limit: self::EVENT_PAGE_SIZE,
				before_at: $cursor['at'],
				before_id: $cursor['id']
			);

		if ( null === $events ) {
			$this->render_unavailable();
			return;
		}

		$session_ids = array_values( array_unique( array_column( $events, 'session_id' ) ) );
		$contexts    = $this->session_repository->get_by_ids( session_ids: $session_ids );
		if ( null === $contexts ) {
			$this->render_unavailable();
			return;
		}

		$page = $this->page_builder->build( events: $events, contexts: $contexts, timezone: $this->timezone );
		if ( null === $page ) {
			$this->render_unavailable();
			return;
		}

		$this->render_subject_heading(
			subject: $subject,
			user: $user instanceof WP_User ? $user : null,
			visitor_id: $visitor_id
		);
		$this->renderer->render( page: $page, timezone: $this->timezone, customer_report: 'customer' === $subject );
		$this->render_event_pagination(
			events: $events,
			subject: $subject,
			user_id: $user_id,
			visitor_id: $visitor_id,
			from_date: $from_date,
			to_date: $to_date
		);
	}

	/**
	 * Render the initial selectors and recent customer journeys.
	 *
	 * @param string $from_date Inclusive local date.
	 * @param string $to_date   Inclusive local date.
	 * @return void
	 */
	private function render_landing( string $from_date, string $to_date ): void {
		$cursor = $this->cursor( at_key: 'journey_visitor_before_at', id_key: 'journey_visitor_before_id' );
		if ( false === $cursor ) {
			$this->render_controls( from_date: $from_date, to_date: $to_date, selected_user: null, selected_visitor_id: null, visitors: array() );
			$this->render_error( message: __( 'The anonymous visitor page cursor is invalid.', 'shurloc-site-tools' ) );
			return;
		}

		$visitors = $this->visitor_repository->anonymous_visitors(
			limit: self::VISITOR_PAGE_SIZE,
			before_at: $cursor['at'],
			before_id: $cursor['id']
		);
		$subjects = $this->visitor_repository->recent_subjects( limit: self::RECENT_SUBJECT_LIMIT );
		$this->render_controls(
			from_date: $from_date,
			to_date: $to_date,
			selected_user: null,
			selected_visitor_id: null,
			visitors: $visitors ?? array()
		);

		if ( null === $visitors || null === $subjects ) {
			$this->render_unavailable();
			return;
		}

		$this->render_recent_subjects( subjects: $subjects );
		$this->render_visitor_pagination( visitors: $visitors, from_date: $from_date, to_date: $to_date );
	}

	/**
	 * Render customer search and anonymous visitor selector forms.
	 *
	 * @param string                                  $from_date          Inclusive local date.
	 * @param string                                  $to_date            Inclusive local date.
	 * @param WP_User|null                            $selected_user       Selected customer.
	 * @param int|null                                $selected_visitor_id Selected visitor ID.
	 * @param list<array{id:int,last_seen_at:string}> $visitors Visitor options.
	 * @return void
	 */
	private function render_controls(
		string $from_date,
		string $to_date,
		?WP_User $selected_user,
		?int $selected_visitor_id,
		array $visitors
	): void {
		?>
		<h2><?php echo esc_html__( 'Customer Journeys', 'shurloc-site-tools' ); ?></h2>
		<p><?php echo esc_html__( 'Inspect one customer or never-linked anonymous visitor over a bounded date range.', 'shurloc-site-tools' ); ?></p>

		<div class="shurloc-journey-selectors">
			<form method="get">
				<?php $this->render_common_fields( subject: 'customer', from_date: $from_date, to_date: $to_date ); ?>
				<label for="shurloc-journey-customer"><?php echo esc_html__( 'Customer', 'shurloc-site-tools' ); ?></label>
				<select
					id="shurloc-journey-customer"
					name="journey_user_id"
					class="wc-customer-search"
					data-action="woocommerce_json_search_customers"
					data-allow_clear="true"
					data-placeholder="<?php echo esc_attr__( 'Search for a customer', 'shurloc-site-tools' ); ?>"
					style="width: 320px"
				>
					<?php if ( null !== $selected_user ) : ?>
						<option value="<?php echo esc_attr( (string) $selected_user->ID ); ?>" selected>
							<?php echo esc_html( $this->customer_label( user: $selected_user ) ); ?>
						</option>
					<?php endif; ?>
				</select>
				<?php submit_button( __( 'View Customer Journey', 'shurloc-site-tools' ), 'secondary', 'submit', false ); ?>
			</form>

			<form method="get">
				<?php $this->render_common_fields( subject: 'visitor', from_date: $from_date, to_date: $to_date ); ?>
				<label for="shurloc-journey-visitor"><?php echo esc_html__( 'Anonymous visitor', 'shurloc-site-tools' ); ?></label>
				<select id="shurloc-journey-visitor" name="journey_visitor_id">
					<?php if ( null !== $selected_visitor_id ) : ?>
						<option value="<?php echo esc_attr( (string) $selected_visitor_id ); ?>" selected>
							<?php echo esc_html( $this->visitor_label( visitor_id: $selected_visitor_id ) ); ?>
						</option>
					<?php elseif ( array() === $visitors ) : ?>
						<option value=""><?php echo esc_html__( 'No anonymous visitors found', 'shurloc-site-tools' ); ?></option>
					<?php else : ?>
						<?php foreach ( $visitors as $visitor ) : ?>
							<option value="<?php echo esc_attr( (string) $visitor['id'] ); ?>">
								<?php echo esc_html( $this->visitor_label( visitor_id: $visitor['id'] ) . ' — ' . __( 'last seen', 'shurloc-site-tools' ) . ' ' . $this->local_datetime( utc: $visitor['last_seen_at'] ) ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<?php submit_button( __( 'View Anonymous Journey', 'shurloc-site-tools' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render authenticated customers and anonymous visitors by latest activity.
	 *
	 * @param array $subjects Validated recent report subjects.
	 * @return void
	 * @phpstan-param list<RecentSubject> $subjects
	 */
	private function render_recent_subjects( array $subjects ): void {
		?>
		<h3><?php echo esc_html__( 'Recent Journeys', 'shurloc-site-tools' ); ?></h3>
		<?php if ( array() === $subjects ) : ?>
			<p><?php echo esc_html__( 'No customer journeys have been recorded.', 'shurloc-site-tools' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<table class="widefat striped shurloc-journey-subjects">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Customer or visitor', 'shurloc-site-tools' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Type', 'shurloc-site-tools' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Latest activity', 'shurloc-site-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $subjects as $subject ) : ?>
					<?php $this->render_recent_subject_row( subject: $subject ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one recent report subject with a link when it remains viewable.
	 *
	 * @param array $subject Validated recent report subject.
	 * @return void
	 * @phpstan-param RecentSubject $subject
	 */
	private function render_recent_subject_row( array $subject ): void {
		$user        = 'customer' === $subject['subject_type'] ? get_userdata( $subject['subject_id'] ) : false;
		$is_customer = $user instanceof WP_User;
		$label       = $is_customer
			? $this->customer_label( user: $user )
			: $this->visitor_label( visitor_id: $subject['subject_id'] );
		$type_label  = 'customer' === $subject['subject_type']
			? __( 'Authenticated customer', 'shurloc-site-tools' )
			: __( 'Anonymous visitor', 'shurloc-site-tools' );
		?>
		<tr>
			<td>
				<?php if ( 'visitor' === $subject['subject_type'] || $is_customer ) : ?>
					<a href="<?php echo esc_url( $this->recent_subject_url( subject: $subject ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: WordPress user ID for a deleted account. */
							__( 'Unavailable customer (#%d)', 'shurloc-site-tools' ),
							$subject['subject_id']
						)
					);
					?>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $type_label ); ?></td>
			<td><?php echo esc_html( $this->local_datetime( utc: $subject['last_activity_at'] ) ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Build a seven-day report URL ending on the subject's latest activity day.
	 *
	 * @param array $subject Validated recent report subject.
	 * @return string Report URL.
	 * @phpstan-param RecentSubject $subject
	 */
	private function recent_subject_url( array $subject ): string {
		$latest                  = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $subject['last_activity_at'], new DateTimeZone( 'UTC' ) );
		$latest                  = false === $latest ? $this->now : $latest->setTimezone( $this->timezone );
		$args                    = $this->base_url_args(
			from_date: $latest->modify( '-6 days' )->format( 'Y-m-d' ),
			to_date: $latest->format( 'Y-m-d' )
		);
		$args['journey_subject'] = $subject['subject_type'];
		if ( 'customer' === $subject['subject_type'] ) {
			$args['journey_user_id'] = $subject['subject_id'];
		} else {
			$args['journey_visitor_id'] = $subject['subject_id'];
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render routing and local-date fields shared by both selectors.
	 *
	 * @param string $subject   Report subject type.
	 * @param string $from_date Inclusive local date.
	 * @param string $to_date   Inclusive local date.
	 * @return void
	 */
	private function render_common_fields( string $subject, string $from_date, string $to_date ): void {
		?>
		<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
		<input type="hidden" name="tab" value="<?php echo esc_attr( self::TAB_SLUG ); ?>">
		<input type="hidden" name="journey_subject" value="<?php echo esc_attr( $subject ); ?>">
		<label>
			<?php echo esc_html__( 'From', 'shurloc-site-tools' ); ?>
			<input type="date" name="journey_from" value="<?php echo esc_attr( $from_date ); ?>" required>
		</label>
		<label>
			<?php echo esc_html__( 'Through', 'shurloc-site-tools' ); ?>
			<input type="date" name="journey_to" value="<?php echo esc_attr( $to_date ); ?>" required>
		</label>
		<?php
	}

	/**
	 * Render the selected report subject without exposing raw visitor UUIDs.
	 *
	 * @param string       $subject    Subject type.
	 * @param WP_User|null $user       Selected customer.
	 * @param int|null     $visitor_id Selected visitor ID.
	 * @return void
	 */
	private function render_subject_heading( string $subject, ?WP_User $user, ?int $visitor_id ): void {
		$label = 'customer' === $subject && null !== $user
			? $this->customer_label( user: $user )
			: $this->visitor_label( visitor_id: (int) $visitor_id );
		?>
		<h3 class="shurloc-journey-subject"><?php echo esc_html( $label ); ?></h3>
		<?php
	}

	/**
	 * Render an older-events link only when the current page is full.
	 *
	 * @param array    $events     Newest-first event rows.
	 * @param string   $subject    Subject type.
	 * @param int|null $user_id    Customer ID.
	 * @param int|null $visitor_id Visitor ID.
	 * @param string   $from_date  Inclusive local date.
	 * @param string   $to_date    Inclusive local date.
	 * @return void
	 * @phpstan-param list<array{id:int,occurred_at:string}> $events
	 */
	private function render_event_pagination(
		array $events,
		string $subject,
		?int $user_id,
		?int $visitor_id,
		string $from_date,
		string $to_date
	): void {
		if ( self::EVENT_PAGE_SIZE !== count( $events ) ) {
			return;
		}

		$last                      = $events[ count( $events ) - 1 ];
		$args                      = $this->base_url_args( from_date: $from_date, to_date: $to_date );
		$args['journey_subject']   = $subject;
		$args['journey_before_at'] = $last['occurred_at'];
		$args['journey_before_id'] = $last['id'];
		if ( null !== $user_id ) {
			$args['journey_user_id'] = $user_id;
		}
		if ( null !== $visitor_id ) {
			$args['journey_visitor_id'] = $visitor_id;
		}
		?>
		<p><a class="button" href="<?php echo esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Older events', 'shurloc-site-tools' ); ?></a></p>
		<?php
	}

	/**
	 * Render an older anonymous visitor page link when the selector page is full.
	 *
	 * @param array  $visitors Visitors ordered by last seen time.
	 * @param string $from_date Inclusive local report date.
	 * @param string $to_date   Inclusive local report date.
	 * @return void
	 * @phpstan-param list<array{id:int,last_seen_at:string}> $visitors
	 */
	private function render_visitor_pagination( array $visitors, string $from_date, string $to_date ): void {
		if ( self::VISITOR_PAGE_SIZE !== count( $visitors ) ) {
			return;
		}

		$last                              = $visitors[ count( $visitors ) - 1 ];
		$args                              = $this->base_url_args( from_date: $from_date, to_date: $to_date );
		$args['journey_visitor_before_at'] = $last['last_seen_at'];
		$args['journey_visitor_before_id'] = $last['id'];
		?>
		<p><a class="button" href="<?php echo esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Older anonymous visitors', 'shurloc-site-tools' ); ?></a></p>
		<?php
	}

	/**
	 * Get common report URL arguments.
	 *
	 * @param string $from_date Inclusive local date.
	 * @param string $to_date   Inclusive local date.
	 * @return array<string,int|string> URL arguments.
	 */
	private function base_url_args( string $from_date, string $to_date ): array {
		return array(
			'page'         => self::PAGE_SLUG,
			'tab'          => self::TAB_SLUG,
			'journey_from' => $from_date,
			'journey_to'   => $to_date,
		);
	}

	/**
	 * Convert inclusive local calendar dates into a half-open UTC range.
	 *
	 * @param string $from_date Inclusive local date.
	 * @param string $to_date   Inclusive local date.
	 * @return array{from_utc:string,until_utc:string}|null Valid UTC range.
	 */
	private function utc_range( string $from_date, string $to_date ): ?array {
		$from  = DateTimeImmutable::createFromFormat( '!Y-m-d', $from_date, $this->timezone );
		$until = DateTimeImmutable::createFromFormat( '!Y-m-d', $to_date, $this->timezone );
		if (
			false === $from || $from->format( 'Y-m-d' ) !== $from_date ||
			false === $until || $until->format( 'Y-m-d' ) !== $to_date
		) {
			return null;
		}

		$until = $until->modify( '+1 day' );
		$span  = $until->getTimestamp() - $from->getTimestamp();
		if ( 0 >= $span || Journey_Report_Repository::MAX_RANGE_DAYS * 86400 < $span ) {
			return null;
		}

		$utc = new DateTimeZone( 'UTC' );
		return array(
			'from_utc'  => $from->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'until_utc' => $until->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Read and validate one paired timestamp and ID keyset cursor.
	 *
	 * @param string $at_key Timestamp request key.
	 * @param string $id_key ID request key.
	 * @return array{at:string|null,id:int|null}|false Cursor or false when invalid.
	 */
	private function cursor( string $at_key, string $id_key ): array|false {
		$at       = $this->request_value( key: $at_key );
		$id_value = $this->request_value( key: $id_key );
		$id       = $this->positive_integer( value: $id_value );
		if ( '' === $at && '' === $id_value ) {
			return array(
				'at' => null,
				'id' => null,
			);
		}

		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $at, new DateTimeZone( 'UTC' ) );
		if ( '' === $at || '' === $id_value || false === $parsed || $parsed->format( 'Y-m-d H:i:s' ) !== $at || null === $id ) {
			return false;
		}

		return array(
			'at' => $at,
			'id' => $id,
		);
	}

	/**
	 * Read one scalar GET value for this read-only admin report.
	 *
	 * @param string $key Request key.
	 * @return string Sanitized value or an empty string.
	 */
	private function request_value( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report routing and filters.
		$value = $_GET[ $key ] ?? '';
		return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}

	/**
	 * Parse a canonical positive request integer.
	 *
	 * @param string $value Request value.
	 * @return int|null Positive integer or null.
	 */
	private function positive_integer( string $value ): ?int {
		if ( '' === $value ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Format a customer label for the authorized admin report.
	 *
	 * @param WP_User $user WordPress user.
	 * @return string Display label.
	 */
	private function customer_label( WP_User $user ): string {
		if ( '' !== $user->display_name ) {
			return sprintf(
				/* translators: 1: customer display name, 2: WordPress user ID. */
				__( '%1$s (#%2$d)', 'shurloc-site-tools' ),
				$user->display_name,
				$user->ID
			);
		}

		return sprintf(
				/* translators: %d: WordPress user ID. */
			__( 'Customer #%d', 'shurloc-site-tools' ),
			$user->ID
		);
	}

	/**
	 * Format a non-sensitive internal anonymous visitor label.
	 *
	 * @param int $visitor_id Internal visitor row ID.
	 * @return string Display label.
	 */
	private function visitor_label( int $visitor_id ): string {
		/* translators: %d: internal Journey visitor row ID. */
		return sprintf( __( 'Anonymous Visitor #%d', 'shurloc-site-tools' ), $visitor_id );
	}

	/**
	 * Format a UTC timestamp in the site timezone.
	 *
	 * @param string $utc Validated UTC MySQL datetime.
	 * @return string Local display timestamp.
	 */
	private function local_datetime( string $utc ): string {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );
		return false === $parsed ? $utc : $parsed->setTimezone( $this->timezone )->format( 'Y-m-d g:i a' );
	}

	/**
	 * Render a report error notice.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function render_error( string $message ): void {
		?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Render the shared unavailable-data notice.
	 *
	 * @return void
	 */
	private function render_unavailable(): void {
		$this->render_error( message: __( 'Journey reporting is unavailable. Verify the Journey schema and retry.', 'shurloc-site-tools' ) );
	}

	/**
	 * Enforce the same capability as the Customer Tools admin page.
	 *
	 * @return void
	 */
	private function verify_permissions(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view Customer Journey reports.', 'shurloc-site-tools' ) );
		}
	}
}
