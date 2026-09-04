<?php
/**
 * Customer migrations admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

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
	 * Constructor.
	 *
	 * @param User_Purchase_Migration $purchase_migration Purchase migration.
	 * @param User_Cart_Migration     $cart_migration     Cart migration.
	 */
	public function __construct(
		User_Purchase_Migration $purchase_migration,
		User_Cart_Migration $cart_migration
	) {

		$this->purchase_migration = $purchase_migration;
		$this->cart_migration     = $cart_migration;
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
