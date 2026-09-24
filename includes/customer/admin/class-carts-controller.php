<?php
/**
 * Customer carts admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use Shurloc\SiteTools\Customer\Formatters\Relative_Time_Formatter;
use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Controller;
use Shurloc\SiteTools\Customer\Repositories\Cart_Session_Repository;
use Shurloc\SiteTools\Customer\Services\Cart_Listing_Service;
use WP_User;

/**
 * Renders the read-only Carts administration table.
 */
final class Carts_Controller {

	/**
	 * Customer Tools page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'shurloc-site-tools-customers';

	/**
	 * Carts tab slug.
	 *
	 * @var string
	 */
	private const TAB_SLUG = 'carts';

	/**
	 * Shared cart asset handle.
	 *
	 * @var string
	 */
	private const ASSET_HANDLE = 'shurloc-user-cart-column';

	/** Calendar days included in a cart customer Journey link. */
	private const JOURNEY_LOOKBACK_DAYS = 30;

	/**
	 * Cart listing service.
	 *
	 * @var Cart_Listing_Service
	 */
	private Cart_Listing_Service $listing_service;

	/**
	 * Cart session repository.
	 *
	 * @var Cart_Session_Repository
	 */
	private Cart_Session_Repository $session_repository;

	/**
	 * Shared cart details renderer.
	 *
	 * @var Cart_Details_Renderer
	 */
	private Cart_Details_Renderer $cart_details_renderer;

	/**
	 * Relative time formatter.
	 *
	 * @var Relative_Time_Formatter
	 */
	private Relative_Time_Formatter $time_formatter;

	/**
	 * Constructor.
	 *
	 * @param Cart_Listing_Service    $listing_service       Cart listing service.
	 * @param Cart_Session_Repository $session_repository    Cart session repository.
	 * @param Cart_Details_Renderer   $cart_details_renderer Shared cart details renderer.
	 * @param Relative_Time_Formatter $time_formatter        Relative time formatter.
	 */
	public function __construct(
		Cart_Listing_Service $listing_service,
		Cart_Session_Repository $session_repository,
		Cart_Details_Renderer $cart_details_renderer,
		Relative_Time_Formatter $time_formatter
	) {

		$this->listing_service       = $listing_service;
		$this->session_repository    = $session_repository;
		$this->cart_details_renderer = $cart_details_renderer;
		$this->time_formatter        = $time_formatter;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' ),
			10,
			0
		);
	}

	/**
	 * Enqueue shared cart-modal assets only on the Carts tab.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if (
			self::PAGE_SLUG !== $this->get_request_value( key: 'page' ) ||
			self::TAB_SLUG !== $this->get_request_value( key: 'tab' )
		) {
			return;
		}

		wp_enqueue_style(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/css/shurloc-user-cart-column.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			self::ASSET_HANDLE,
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/js/shurloc-user-cart-column.js',
			array(),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}

	/**
	 * Render the Carts tab contents.
	 *
	 * @return void
	 */
	public function render(): void {

		?>
		<h2><?php echo esc_html__( 'Carts', 'shurloc-site-tools' ); ?></h2>

		<p>
			<?php echo esc_html__( 'Current non-empty WooCommerce carts, including registered customers and guests.', 'shurloc-site-tools' ); ?>
		</p>
		<?php

		if ( ! $this->session_repository->supports_current_handler() ) {
			$this->render_unsupported_notice();

			return;
		}

		$listing = $this->listing_service->get_listing(
			filter: $this->get_filter(),
			page: $this->get_page_number(),
		);

		$this->render_filters(
			counts: $listing['counts'],
			current_filter: $listing['filter'],
		);

		$this->render_table( items: $listing['items'] );
		$this->render_pagination(
			current_page: $listing['page'],
			total_pages: $listing['total_pages'],
			current_filter: $listing['filter'],
		);
	}

	/**
	 * Render an unsupported-storage notice.
	 *
	 * @return void
	 */
	private function render_unsupported_notice(): void {
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php echo esc_html__( 'Cart listing is unavailable because this site uses a custom WooCommerce session handler.', 'shurloc-site-tools' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render visitor filters.
	 *
	 * @param array{all:int,authenticated:int,unauthenticated:int} $counts         Cart counts.
	 * @param string                                               $current_filter Current filter.
	 * @return void
	 */
	private function render_filters(
		array $counts,
		string $current_filter
	): void {

		$filters = array(
			Cart_Listing_Service::FILTER_ALL             => __( 'All', 'shurloc-site-tools' ),
			Cart_Listing_Service::FILTER_AUTHENTICATED   => __( 'Authenticated', 'shurloc-site-tools' ),
			Cart_Listing_Service::FILTER_UNAUTHENTICATED => __( 'Unauthenticated', 'shurloc-site-tools' ),
		);
		?>
		<ul class="subsubsub">
			<?php foreach ( $filters as $filter => $label ) : ?>
				<li>
					<a
						href="<?php echo esc_url( $this->get_url( filter: $filter ) ); ?>"
						class="<?php echo $filter === $current_filter ? 'current' : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo esc_html( (string) $counts[ $filter ] ); ?>)</span>
					</a>
					<?php echo Cart_Listing_Service::FILTER_UNAUTHENTICATED !== $filter ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render the cart table.
	 *
	 * @param array<int,array<string,mixed>> $items Prepared cart rows.
	 * @return void
	 */
	private function render_table(
		array $items
	): void {
		?>
		<table class="widefat fixed striped shurloc-carts-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Visitor', 'shurloc-site-tools' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Cart', 'shurloc-site-tools' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Last Activity', 'shurloc-site-tools' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'shurloc-site-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( array() === $items ) : ?>
					<tr>
						<td colspan="4"><?php echo esc_html__( 'No non-empty carts were found.', 'shurloc-site-tools' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<?php $this->render_row( item: $item ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one cart row.
	 *
	 * @param array<string,mixed> $item Prepared cart row.
	 * @return void
	 */
	private function render_row(
		array $item
	): void {

		$user_id            = isset( $item['user_id'] ) ? (int) $item['user_id'] : 0;
		$session_reference  = isset( $item['session_reference'] ) && is_string( $item['session_reference'] )
			? $item['session_reference']
			: '';
		$item_count         = isset( $item['item_count'] ) ? (int) $item['item_count'] : 0;
		$contents_total     = isset( $item['contents_total'] ) ? (float) $item['contents_total'] : 0.0;
		$cart_contents      = isset( $item['cart_contents'] ) && is_array( $item['cart_contents'] )
			? $item['cart_contents']
			: array();
		$last_activity_at   = isset( $item['last_activity_at'] ) ? (int) $item['last_activity_at'] : 0;
		$journey_visitor_id = isset( $item['journey_visitor_id'] ) ? (int) $item['journey_visitor_id'] : 0;

		$cart_html = $this->cart_details_renderer->render(
			reference: $session_reference,
			item_count: $item_count,
			total: $contents_total,
			contents: $cart_contents,
		);
		?>
		<tr>
			<td><?php $this->render_visitor( user_id: $user_id, item: $item ); ?></td>
			<td>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its complete HTML contract.
				echo $cart_html;
				?>
			</td>
			<td>
				<?php $this->render_last_activity( user_id: $user_id, journey_visitor_id: $journey_visitor_id, last_activity_at: $last_activity_at ); ?>
			</td>
			<td><?php echo esc_html( isset( $item['status'] ) && is_string( $item['status'] ) ? $item['status'] : '' ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Render cart activity with a Journey link when identity is known.
	 *
	 * @param int $user_id           Registered user ID, or zero for a guest.
	 * @param int $journey_visitor_id Correlated Journey visitor ID, or zero.
	 * @param int $last_activity_at  Estimated cart activity timestamp.
	 * @return void
	 */
	private function render_last_activity( int $user_id, int $journey_visitor_id, int $last_activity_at ): void {
		$label = $this->time_formatter->format( $last_activity_at );
		if ( 0 >= $last_activity_at || ( 0 >= $user_id && 0 >= $journey_visitor_id ) ) {
			echo esc_html( $label );
			return;
		}

		?>
		<a href="<?php echo esc_url( $this->get_journey_url( user_id: $user_id, visitor_id: $journey_visitor_id, last_activity_at: $last_activity_at ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php
	}

	/**
	 * Build a Journey URL for the 30 days ending on cart activity.
	 *
	 * @param int $user_id          Registered user ID, or zero for a guest.
	 * @param int $visitor_id       Correlated Journey visitor ID, or zero.
	 * @param int $last_activity_at Estimated cart activity timestamp.
	 * @return string Journey report URL.
	 */
	private function get_journey_url( int $user_id, int $visitor_id, int $last_activity_at ): string {
		$activity = ( new DateTimeImmutable( '@' . $last_activity_at ) )->setTimezone( wp_timezone() );
		$args     = array(
			'page'            => Journey_Report_Controller::PAGE_SLUG,
			'tab'             => Journey_Report_Controller::TAB_SLUG,
			'journey_subject' => 0 < $user_id ? 'customer' : 'visitor',
			'journey_from'    => $activity->modify( '-' . ( self::JOURNEY_LOOKBACK_DAYS - 1 ) . ' days' )->format( 'Y-m-d' ),
			'journey_to'      => $activity->format( 'Y-m-d' ),
		);
		if ( 0 < $user_id ) {
			$args['journey_user_id'] = $user_id;
		} else {
			$args['journey_visitor_id'] = $visitor_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render visitor identity without exposing a full guest session key.
	 *
	 * @param int                 $user_id Registered user ID, or zero for a guest.
	 * @param array<string,mixed> $item    Prepared cart row.
	 * @return void
	 */
	private function render_visitor(
		int $user_id,
		array $item
	): void {

		if ( 0 >= $user_id ) {
			$reference = isset( $item['session_reference'] ) && is_string( $item['session_reference'] )
				? $item['session_reference']
				: '';
			?>
			<strong><?php echo esc_html__( 'Guest', 'shurloc-site-tools' ); ?></strong>
			<?php if ( '' !== $reference ) : ?>
				<br><code><?php echo esc_html( $reference ); ?></code>
			<?php endif; ?>
			<?php
			return;
		}

		$user         = get_userdata( $user_id );
		$display_name = sprintf(
			/* translators: %d: WordPress user ID. */
			__( 'User #%d', 'shurloc-site-tools' ),
			$user_id
		);
		$email = '';

		if ( $user instanceof WP_User ) {
			if ( isset( $user->display_name ) && '' !== $user->display_name ) {
				$display_name = $user->display_name;
			}

			if ( isset( $user->user_email ) ) {
				$email = $user->user_email;
			}
		}

		$edit_url = add_query_arg(
			array( 'user_id' => $user_id ),
			admin_url( 'user-edit.php' )
		);
		?>
		<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $display_name ); ?></a></strong>
		<?php if ( '' !== $email ) : ?>
			<br><span><?php echo esc_html( $email ); ?></span>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render simple WordPress-style pagination.
	 *
	 * @param int    $current_page   Current page.
	 * @param int    $total_pages    Total pages.
	 * @param string $current_filter Current visitor filter.
	 * @return void
	 */
	private function render_pagination(
		int $current_page,
		int $total_pages,
		string $current_filter
	): void {

		if ( 2 > $total_pages ) {
			return;
		}
		?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php if ( 1 < $current_page ) : ?>
					<a class="button" href="<?php echo esc_url( $this->get_url( filter: $current_filter, page: $current_page - 1 ) ); ?>"><?php echo esc_html__( 'Previous', 'shurloc-site-tools' ); ?></a>
				<?php endif; ?>
				<span class="paging-input">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Current page. 2: Total pages. */
							__( 'Page %1$d of %2$d', 'shurloc-site-tools' ),
							$current_page,
							$total_pages
						)
					);
					?>
				</span>
				<?php if ( $current_page < $total_pages ) : ?>
					<a class="button" href="<?php echo esc_url( $this->get_url( filter: $current_filter, page: $current_page + 1 ) ); ?>"><?php echo esc_html__( 'Next', 'shurloc-site-tools' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the validated visitor filter.
	 *
	 * @return string
	 */
	private function get_filter(): string {

		$filter = $this->get_request_value( key: 'cart_filter' );

		return in_array(
			$filter,
			array(
				Cart_Listing_Service::FILTER_ALL,
				Cart_Listing_Service::FILTER_AUTHENTICATED,
				Cart_Listing_Service::FILTER_UNAUTHENTICATED,
			),
			true
		) ? $filter : Cart_Listing_Service::FILTER_ALL;
	}

	/**
	 * Get the requested page number.
	 *
	 * @return int
	 */
	private function get_page_number(): int {

		$value = $this->get_request_value( key: 'paged' );

		return ctype_digit( $value ) ? max( 1, (int) $value ) : 1;
	}

	/**
	 * Get a sanitized request value.
	 *
	 * @param string $key Query parameter key.
	 * @return string
	 */
	private function get_request_value(
		string $key
	): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin table routing.
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_key(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin table routing.
			wp_unslash( $_GET[ $key ] )
		);
	}

	/**
	 * Build a Customer Tools Carts URL.
	 *
	 * @param string $filter Visitor filter.
	 * @param int    $page   Page number.
	 * @return string
	 */
	private function get_url(
		string $filter,
		int $page = 1
	): string {

		$args = array(
			'page'        => self::PAGE_SLUG,
			'tab'         => self::TAB_SLUG,
			'cart_filter' => $filter,
		);

		if ( 1 < $page ) {
			$args['paged'] = $page;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
