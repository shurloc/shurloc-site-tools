<?php
/**
 * Customer migrations admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Data_Truncation_Migration;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Single_Event_Cleanup_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Cart_Migration;
use Shurloc\SiteTools\Customer\Migrations\User_Purchase_Migration;

/**
 * Renders and processes customer data migrations.
 */
final class Customer_Migrations_Controller {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'shurloc-site-tools-customers';

	/**
	 * Admin tab slug.
	 *
	 * @var string
	 */
	private const TAB_SLUG = 'migrations';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Purchase migration action.
	 *
	 * @var string
	 */
	private const PURCHASE_ACTION =
		'shurloc_run_purchase_migration';

	/**
	 * Purchase migration result nonce action.
	 *
	 * @var string
	 */
	private const PURCHASE_RESULT_NONCE_ACTION =
		'shurloc_purchase_migration_result';

	/**
	 * Cart migration action.
	 *
	 * @var string
	 */
	private const CART_ACTION =
		'shurloc_run_cart_migration';

	/**
	 * Cart migration result nonce action.
	 *
	 * @var string
	 */
	private const CART_RESULT_NONCE_ACTION =
		'shurloc_cart_migration_result';

	/**
	 * Journey data truncation action.
	 *
	 * @var string
	 */
	private const JOURNEY_TRUNCATION_ACTION =
		'shurloc_run_journey_data_truncation_migration';

	/**
	 * Journey data truncation result nonce action.
	 *
	 * @var string
	 */
	private const JOURNEY_TRUNCATION_RESULT_NONCE_ACTION =
		'shurloc_journey_data_truncation_migration_result';

	/**
	 * Single-event Journey cleanup action.
	 *
	 * @var string
	 */
	private const JOURNEY_SINGLE_EVENT_ACTION =
		'shurloc_run_journey_single_event_cleanup_migration';

	/**
	 * Single-event Journey cleanup result nonce action.
	 *
	 * @var string
	 */
	private const JOURNEY_SINGLE_EVENT_RESULT_NONCE_ACTION =
		'shurloc_journey_single_event_cleanup_migration_result';

	/**
	 * Purchase migration.
	 *
	 * @var User_Purchase_Migration
	 */
	private User_Purchase_Migration $purchase_migration;

	/**
	 * Cart migration.
	 *
	 * @var User_Cart_Migration
	 */
	private User_Cart_Migration $cart_migration;

	/**
	 * Journey data truncation migration.
	 *
	 * @var Journey_Data_Truncation_Migration
	 */
	private Journey_Data_Truncation_Migration $journey_truncation_migration;

	/**
	 * Single-event Journey cleanup migration.
	 *
	 * @var Journey_Single_Event_Cleanup_Migration
	 */
	private Journey_Single_Event_Cleanup_Migration $journey_single_event_migration;

	/**
	 * Constructor.
	 *
	 * @param User_Purchase_Migration                     $purchase_migration             Purchase migration.
	 * @param User_Cart_Migration                         $cart_migration                 Cart migration.
	 * @param Journey_Data_Truncation_Migration|null      $journey_truncation_migration   Journey data truncation.
	 * @param Journey_Single_Event_Cleanup_Migration|null $journey_single_event_migration Single-event Journey cleanup.
	 */
	public function __construct(
		User_Purchase_Migration $purchase_migration,
		User_Cart_Migration $cart_migration,
		?Journey_Data_Truncation_Migration $journey_truncation_migration = null,
		?Journey_Single_Event_Cleanup_Migration $journey_single_event_migration = null
	) {

		$this->purchase_migration             = $purchase_migration;
		$this->cart_migration                 = $cart_migration;
		$this->journey_truncation_migration   =
			$journey_truncation_migration ??
			new Journey_Data_Truncation_Migration();
		$this->journey_single_event_migration =
			$journey_single_event_migration ??
			new Journey_Single_Event_Cleanup_Migration();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_post_' . self::PURCHASE_ACTION,
			array(
				$this,
				'handle_purchase_migration',
			)
		);

		add_action(
			'admin_post_' . self::CART_ACTION,
			array(
				$this,
				'handle_cart_migration',
			)
		);

		add_action(
			'admin_post_' . self::JOURNEY_TRUNCATION_ACTION,
			array(
				$this,
				'handle_journey_data_truncation_migration',
			)
		);

		add_action(
			'admin_post_' . self::JOURNEY_SINGLE_EVENT_ACTION,
			array(
				$this,
				'handle_journey_single_event_cleanup_migration',
			)
		);

		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_assets',
			)
		);
	}

	/**
	 * Enqueue migration admin assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if ( ! $this->is_migrations_page() ) {
			return;
		}

		wp_enqueue_style(
			'shurloc-customer-migrations',
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/css/shurloc-customer-migrations.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			'shurloc-customer-migrations',
			SHURLOC_SITE_TOOLS_URL .
				'assets/customer/js/shurloc-customer-migrations.js',
			array(),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}

	/**
	 * Render the migrations tab.
	 *
	 * @return void
	 */
	public function render(): void {

		$this->render_result_notice();

		?>
		<h2>Customer Data Migrations</h2>

		<p>
			Controlled tools for seeding and rebuilding customer
			tracking data.
		</p>
		<?php

		$this->render_purchase_migration_card();
		$this->render_cart_migration_card();
		$this->render_journey_single_event_migration_card();
		$this->render_journey_truncation_migration_card();
		$this->render_migration_overlay();
	}

	/**
	 * Render the purchase migration card.
	 *
	 * @return void
	 */
	private function render_purchase_migration_card(): void {

		$last_run = $this->purchase_migration->get_last_run();

		$last_run_display = $this->get_last_run_display(
			last_run: $last_run,
		);

		$last_run_version =
			$this->purchase_migration->get_last_run_version();

		?>
		<div class="card">
			<h2>Purchase Tracking Seeding</h2>

			<p>
				Seeds each registered user's last-purchase data from
				their most recent qualifying WooCommerce order.
			</p>

			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row">Current migration version</th>
						<td>
							<?php
							echo esc_html(
								(string) User_Purchase_Migration::VERSION
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">Last-run migration version</th>
						<td>
							<?php
							echo esc_html(
								0 < $last_run_version
									? (string) $last_run_version
									: 'Not recorded'
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">Last run</th>
						<td>
							<?php echo esc_html( $last_run_display ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				class="shurloc-migration-form"
				data-confirm-message="Run the Purchase Tracking migration? This will rebuild purchase tracking data for existing users."
			>
				<input
					type="hidden"
					name="action"
					value="<?php echo esc_attr( self::PURCHASE_ACTION ); ?>"
				/>

				<?php
				wp_nonce_field(
					self::PURCHASE_ACTION
				);
				?>

				<p>
					<label>
						<input
							type="checkbox"
							class="shurloc-migration-enable"
						/>

						Enable this migration
					</label>
				</p>

				<p>
					<button
						type="submit"
						class="button button-primary shurloc-migration-submit"
						disabled
					>
						Run Purchase Migration
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the cart migration card.
	 *
	 * @return void
	 */
	private function render_cart_migration_card(): void {

		$last_run = $this->cart_migration->get_last_run();

		$last_run_display = $this->get_last_run_display(
			last_run: $last_run,
		);

		$last_run_version =
			$this->cart_migration->get_last_run_version();

		?>
		<div class="card">
			<h2>Cart Tracking Seeding</h2>

			<p>
				Seeds stored cart snapshots for registered users from
				their existing WooCommerce sessions.
			</p>

			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row">Current migration version</th>
						<td>
							<?php
							echo esc_html(
								(string) User_Cart_Migration::VERSION
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">Last-run migration version</th>
						<td>
							<?php
							echo esc_html(
								0 < $last_run_version
									? (string) $last_run_version
									: 'Not recorded'
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">Last run</th>
						<td>
							<?php echo esc_html( $last_run_display ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				class="shurloc-migration-form"
				data-confirm-message="Run the Cart Tracking migration? This will rebuild cart tracking data for existing users from stored WooCommerce sessions."
			>
				<input
					type="hidden"
					name="action"
					value="<?php echo esc_attr( self::CART_ACTION ); ?>"
				/>

				<?php
				wp_nonce_field(
					self::CART_ACTION
				);
				?>

				<p>
					<label>
						<input
							type="checkbox"
							class="shurloc-migration-enable"
						/>

						Enable this migration
					</label>
				</p>

				<p>
					<button
						type="submit"
						class="button button-primary shurloc-migration-submit"
						disabled
					>
						Run Cart Migration
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the single-event Journey cleanup card.
	 *
	 * @return void
	 */
	private function render_journey_single_event_migration_card(): void {
		$this->render_journey_migration_card(
			title: 'Single-Event Journey Cleanup',
			description: 'Deletes Journey sessions with exactly one event and 0 seconds of active time, together with their events and cart links.',
			version: Journey_Single_Event_Cleanup_Migration::VERSION,
			last_run: $this->journey_single_event_migration->get_last_run(),
			last_run_version: $this->journey_single_event_migration->get_last_run_version(),
			action: self::JOURNEY_SINGLE_EVENT_ACTION,
			confirm_message: 'Run the Single-Event Journey Cleanup migration? Matching journeys and their dependent events and cart links will be permanently deleted.',
			button_label: 'Run Single-Event Journey Cleanup',
		);
	}

	/**
	 * Render the complete Journey data truncation card.
	 *
	 * @return void
	 */
	private function render_journey_truncation_migration_card(): void {
		$this->render_journey_migration_card(
			title: 'Journey Data Truncation',
			description: 'Permanently removes all Customer Journey visitors, identity periods, sessions, events, and cart links.',
			version: Journey_Data_Truncation_Migration::VERSION,
			last_run: $this->journey_truncation_migration->get_last_run(),
			last_run_version: $this->journey_truncation_migration->get_last_run_version(),
			action: self::JOURNEY_TRUNCATION_ACTION,
			confirm_message: 'Run the Journey Data Truncation migration? All Customer Journey data will be permanently removed. This cannot be undone.',
			button_label: 'Run Journey Data Truncation',
		);
	}

	/**
	 * Render one Journey maintenance migration card.
	 *
	 * @param string $title            Card heading.
	 * @param string $description      Migration description.
	 * @param int    $version          Current migration version.
	 * @param int    $last_run         Last successful run timestamp.
	 * @param int    $last_run_version Version used for the last successful run.
	 * @param string $action           Admin-post action.
	 * @param string $confirm_message  Browser confirmation message.
	 * @param string $button_label     Submit button label.
	 * @return void
	 */
	private function render_journey_migration_card(
		string $title,
		string $description,
		int $version,
		int $last_run,
		int $last_run_version,
		string $action,
		string $confirm_message,
		string $button_label
	): void {
		$last_run_display = $this->get_last_run_display(
			last_run: $last_run,
		);
		?>
		<div class="card">
			<h2><?php echo esc_html( $title ); ?></h2>

			<p><?php echo esc_html( $description ); ?></p>

			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row">Current migration version</th>
						<td><?php echo esc_html( (string) $version ); ?></td>
					</tr>

					<tr>
						<th scope="row">Last-run migration version</th>
						<td>
							<?php
							echo esc_html(
								0 < $last_run_version
									? (string) $last_run_version
									: 'Not recorded'
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">Last run</th>
						<td><?php echo esc_html( $last_run_display ); ?></td>
					</tr>
				</tbody>
			</table>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				class="shurloc-migration-form"
				data-confirm-message="<?php echo esc_attr( $confirm_message ); ?>"
			>
				<input
					type="hidden"
					name="action"
					value="<?php echo esc_attr( $action ); ?>"
				/>

				<?php wp_nonce_field( $action ); ?>

				<p>
					<label>
						<input
							type="checkbox"
							class="shurloc-migration-enable"
						/>

						Enable this migration
					</label>
				</p>

				<p>
					<button
						type="submit"
						class="button button-primary shurloc-migration-submit"
						disabled
					>
						<?php echo esc_html( $button_label ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the migration running overlay.
	 *
	 * @return void
	 */
	private function render_migration_overlay(): void {
		?>
		<div
			class="shurloc-migration-overlay"
			hidden
		>
			<div
				class="shurloc-migration-dialog"
				role="status"
				aria-live="polite"
			>
				<span
					class="spinner is-active"
					aria-hidden="true"
				></span>

				<strong>
					Migration is running…
				</strong>

				<p>
					Please keep this page open until the migration completes.
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the display value for a migration last-run timestamp.
	 *
	 * @param int $last_run Last-run timestamp.
	 * @return string
	 */
	private function get_last_run_display(
		int $last_run
	): string {

		if ( 0 >= $last_run ) {
			return 'Never';
		}

		$formatted_date = wp_date(
			'F j, Y g:i a',
			$last_run
		);

		return false === $formatted_date
		? 'Unknown'
		: $formatted_date;
	}

	/**
	 * Run the purchase migration and build its result URL.
	 *
	 * @return string Redirect URL.
	 */
	public function run_purchase_migration(): string {

		if ( ! $this->purchase_migration->acquire_lock() ) {
			return $this->get_purchase_migration_locked_redirect_url();
		}

		try {

			$result = $this->purchase_migration->run();

			return $this->get_purchase_migration_redirect_url(
				result: $result,
			);

		} finally {

			$this->purchase_migration->release_lock();
		}
	}

	/**
	 * Run the cart migration and build its result URL.
	 *
	 * @return string Redirect URL.
	 */
	public function run_cart_migration(): string {

		if ( ! $this->cart_migration->acquire_lock() ) {
			return $this->get_cart_migration_locked_redirect_url();
		}

		try {

			$result = $this->cart_migration->run();

			return $this->get_cart_migration_redirect_url(
				result: $result,
			);

		} finally {

			$this->cart_migration->release_lock();
		}
	}

	/**
	 * Run the Journey data truncation migration and build its result URL.
	 *
	 * @return string Redirect URL.
	 */
	public function run_journey_data_truncation_migration(): string {
		if ( ! $this->journey_truncation_migration->acquire_lock() ) {
			return $this->get_journey_truncation_locked_redirect_url();
		}

		try {
			$result = $this->journey_truncation_migration->run();

			return $this->get_journey_truncation_redirect_url(
				result: $result,
			);
		} finally {
			$this->journey_truncation_migration->release_lock();
		}
	}

	/**
	 * Run the single-event Journey cleanup and build its result URL.
	 *
	 * @return string Redirect URL.
	 */
	public function run_journey_single_event_cleanup_migration(): string {
		if ( ! $this->journey_single_event_migration->acquire_lock() ) {
			return $this->get_journey_single_event_locked_redirect_url();
		}

		try {
			$result = $this->journey_single_event_migration->run();

			return $this->get_journey_single_event_redirect_url(
				result: $result,
			);
		} finally {
			$this->journey_single_event_migration->release_lock();
		}
	}

	/**
	 * Process the purchase migration request.
	 *
	 * @return void
	 */
	public function handle_purchase_migration(): void {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to run this migration.',
					'shurloc-site-tools'
				)
			);
		}

		check_admin_referer(
			self::PURCHASE_ACTION
		);

		$redirect_url = $this->run_purchase_migration();

		wp_safe_redirect(
			$redirect_url
		);

		exit;
	}

	/**
	 * Process the cart migration request.
	 *
	 * @return void
	 */
	public function handle_cart_migration(): void {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to run this migration.',
					'shurloc-site-tools'
				)
			);
		}

		check_admin_referer(
			self::CART_ACTION
		);

		$redirect_url = $this->run_cart_migration();

		wp_safe_redirect(
			$redirect_url
		);

		exit;
	}

	/**
	 * Process the Journey data truncation request.
	 *
	 * @return void
	 */
	public function handle_journey_data_truncation_migration(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to run this migration.',
					'shurloc-site-tools'
				)
			);
		}

		check_admin_referer( self::JOURNEY_TRUNCATION_ACTION );

		wp_safe_redirect(
			$this->run_journey_data_truncation_migration()
		);

		exit;
	}

	/**
	 * Process the single-event Journey cleanup request.
	 *
	 * @return void
	 */
	public function handle_journey_single_event_cleanup_migration(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to run this migration.',
					'shurloc-site-tools'
				)
			);
		}

		check_admin_referer( self::JOURNEY_SINGLE_EVENT_ACTION );

		wp_safe_redirect(
			$this->run_journey_single_event_cleanup_migration()
		);

		exit;
	}

	/**
	 * Build the redirect URL for a completed purchase migration.
	 *
	 * @param array{ examined:int, updated:int,skipped:int, errors:int } $result Migration result.
	 * @return string
	 */
	private function get_purchase_migration_redirect_url(
		array $result
	): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'purchase',
				'examined'  => $result['examined'],
				'updated'   => $result['updated'],
				'skipped'   => $result['skipped'],
				'errors'    => $result['errors'],
				'_wpnonce'  => wp_create_nonce(
					self::PURCHASE_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL when the purchase migration is already running.
	 *
	 * @return string
	 */
	private function get_purchase_migration_locked_redirect_url(): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'purchase-locked',
				'_wpnonce'  => wp_create_nonce(
					self::PURCHASE_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL for a completed cart migration.
	 *
	 * @param array{ examined:int, updated:int, skipped:int, errors:int } $result Migration result.
	 * @return string
	 */
	private function get_cart_migration_redirect_url(
		array $result
	): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'cart',
				'examined'  => $result['examined'],
				'updated'   => $result['updated'],
				'skipped'   => $result['skipped'],
				'errors'    => $result['errors'],
				'_wpnonce'  => wp_create_nonce(
					self::CART_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL when the cart migration is already running.
	 *
	 * @return string
	 */
	private function get_cart_migration_locked_redirect_url(): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'cart-locked',
				'_wpnonce'  => wp_create_nonce(
					self::CART_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL for completed Journey data truncation.
	 *
	 * @param array{truncated:int,errors:int} $result Migration result.
	 * @return string
	 */
	private function get_journey_truncation_redirect_url(
		array $result
	): string {
		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'journey-data-truncation',
				'truncated' => $result['truncated'],
				'errors'    => $result['errors'],
				'_wpnonce'  => wp_create_nonce(
					self::JOURNEY_TRUNCATION_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL when Journey data truncation is already running.
	 *
	 * @return string
	 */
	private function get_journey_truncation_locked_redirect_url(): string {
		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'journey-data-truncation-locked',
				'_wpnonce'  => wp_create_nonce(
					self::JOURNEY_TRUNCATION_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL for completed single-event Journey cleanup.
	 *
	 * @param array{deleted:int,errors:int} $result Migration result.
	 * @return string
	 */
	private function get_journey_single_event_redirect_url(
		array $result
	): string {
		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'journey-single-event-cleanup',
				'deleted'   => $result['deleted'],
				'errors'    => $result['errors'],
				'_wpnonce'  => wp_create_nonce(
					self::JOURNEY_SINGLE_EVENT_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL when single-event Journey cleanup is locked.
	 *
	 * @return string
	 */
	private function get_journey_single_event_locked_redirect_url(): string {
		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'journey-single-event-cleanup-locked',
				'_wpnonce'  => wp_create_nonce(
					self::JOURNEY_SINGLE_EVENT_RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the result from the previous migration run.
	 *
	 * @return void
	 */
	private function render_result_notice(): void {

		if (
			! isset(
				$_GET['_wpnonce'],
				$_GET['migration']
			)
		) {
			return;
		}

		$nonce = sanitize_text_field(
			wp_unslash( $_GET['_wpnonce'] )
		);

		$migration = sanitize_key(
			wp_unslash( $_GET['migration'] )
		);

		$nonce_action = $this->get_result_nonce_action(
			migration: $migration,
		);

		if (
			null === $nonce_action ||
			! wp_verify_nonce(
				$nonce,
				$nonce_action
			)
		) {
			return;
		}

		if ( 'purchase-locked' === $migration ) {
			$this->render_locked_notice(
				message: 'Purchase migration is already running. No second migration was started.',
			);

			return;
		}

		if ( 'cart-locked' === $migration ) {
			$this->render_locked_notice(
				message: 'Cart migration is already running. No second migration was started.',
			);

			return;
		}

		if ( 'journey-data-truncation-locked' === $migration ) {
			$this->render_locked_notice(
				message: 'Journey data truncation is already running. No second migration was started.',
			);

			return;
		}

		if ( 'journey-single-event-cleanup-locked' === $migration ) {
			$this->render_locked_notice(
				message: 'Single-event Journey cleanup is already running. No second migration was started.',
			);

			return;
		}

		if ( 'journey-data-truncation' === $migration ) {
			$this->render_journey_completion_notice(
				label: 'Journey data truncation',
				count_label: 'Tables truncated',
				count: isset( $_GET['truncated'] )
					? absint( $_GET['truncated'] )
					: 0,
				errors: isset( $_GET['errors'] )
					? absint( $_GET['errors'] )
					: 0,
			);

			return;
		}

		if ( 'journey-single-event-cleanup' === $migration ) {
			$this->render_journey_completion_notice(
				label: 'Single-event Journey cleanup',
				count_label: 'Journeys deleted',
				count: isset( $_GET['deleted'] )
					? absint( $_GET['deleted'] )
					: 0,
				errors: isset( $_GET['errors'] )
					? absint( $_GET['errors'] )
					: 0,
			);

			return;
		}

		if (
			'purchase' !== $migration &&
			'cart' !== $migration
		) {
			return;
		}

		$examined = isset( $_GET['examined'] )
			? absint( $_GET['examined'] )
			: 0;

		$updated = isset( $_GET['updated'] )
			? absint( $_GET['updated'] )
			: 0;

		$skipped = isset( $_GET['skipped'] )
			? absint( $_GET['skipped'] )
			: 0;

		$errors = isset( $_GET['errors'] )
			? absint( $_GET['errors'] )
			: 0;

		$label = 'purchase' === $migration
			? 'Purchase'
			: 'Cart';

		$this->render_completion_notice(
			label: $label,
			examined: $examined,
			updated: $updated,
			skipped: $skipped,
			errors: $errors,
		);
	}

	/**
	 * Get the expected result nonce action for a migration result.
	 *
	 * @param string $migration Migration result identifier.
	 * @return string|null
	 */
	private function get_result_nonce_action(
		string $migration
	): ?string {

		if (
			'purchase' === $migration ||
			'purchase-locked' === $migration
		) {
			return self::PURCHASE_RESULT_NONCE_ACTION;
		}

		if (
			'cart' === $migration ||
			'cart-locked' === $migration
		) {
			return self::CART_RESULT_NONCE_ACTION;
		}

		if (
			'journey-data-truncation' === $migration ||
			'journey-data-truncation-locked' === $migration
		) {
			return self::JOURNEY_TRUNCATION_RESULT_NONCE_ACTION;
		}

		if (
			'journey-single-event-cleanup' === $migration ||
			'journey-single-event-cleanup-locked' === $migration
		) {
			return self::JOURNEY_SINGLE_EVENT_RESULT_NONCE_ACTION;
		}

		return null;
	}

	/**
	 * Render a migration locked notice.
	 *
	 * @param string $message Notice message.
	 * @return void
	 */
	private function render_locked_notice(
		string $message
	): void {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo esc_html( $message ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a completed migration notice.
	 *
	 * @param string $label    Migration label.
	 * @param int    $examined Number examined.
	 * @param int    $updated  Number updated.
	 * @param int    $skipped  Number skipped.
	 * @param int    $errors   Number of errors.
	 * @return void
	 */
	private function render_completion_notice(
		string $label,
		int $examined,
		int $updated,
		int $skipped,
		int $errors
	): void {

		$notice_class = 0 === $errors
			? 'notice notice-success is-dismissible'
			: 'notice notice-warning is-dismissible';

		?>
		<div class="<?php echo esc_attr( $notice_class ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						'%s migration complete. Examined: %d; Updated: %d; Skipped: %d; Errors: %d.',
						$label,
						$examined,
						$updated,
						$skipped,
						$errors
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a completed Journey maintenance migration notice.
	 *
	 * @param string $label       Migration label.
	 * @param string $count_label Affected-record label.
	 * @param int    $count       Number affected.
	 * @param int    $errors      Number of errors.
	 * @return void
	 */
	private function render_journey_completion_notice(
		string $label,
		string $count_label,
		int $count,
		int $errors
	): void {
		$notice_class = 0 === $errors
			? 'notice notice-success is-dismissible'
			: 'notice notice-warning is-dismissible';
		?>
		<div class="<?php echo esc_attr( $notice_class ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						'%s complete. %s: %d; Errors: %d.',
						$label,
						$count_label,
						$count,
						$errors
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Determine whether the current request is the migrations page.
	 *
	 * @return bool
	 */
	private function is_migrations_page(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$page = isset( $_GET['page'] )
			? sanitize_key(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
				wp_unslash( $_GET['page'] )
			)
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$tab = isset( $_GET['tab'] )
			? sanitize_key(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
				wp_unslash( $_GET['tab'] )
			)
			: '';

		return (
			self::PAGE_SLUG === $page &&
			self::TAB_SLUG === $tab
		);
	}
}
