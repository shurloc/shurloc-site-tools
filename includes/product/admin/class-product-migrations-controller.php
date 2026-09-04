<?php
/**
 * Product migrations admin controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Product\Migrations\Yoast_Product_Meta_Cleanup_Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and processes Product Tools data migrations.
 */
final class Product_Migrations_Controller {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'shurloc-site-tools-products';

	/**
	 * Admin tab slug.
	 */
	private const TAB_SLUG = 'migrations';

	/**
	 * Required capability.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Yoast product meta cleanup migration action.
	 */
	private const CLEANUP_ACTION =
		'shurloc_run_yoast_product_meta_cleanup';

	/**
	 * Migration result nonce action.
	 */
	private const RESULT_NONCE_ACTION =
		'shurloc_yoast_product_meta_cleanup_result';

	/**
	 * Yoast product meta cleanup migration.
	 *
	 * @var Yoast_Product_Meta_Cleanup_Migration
	 */
	private Yoast_Product_Meta_Cleanup_Migration $cleanup_migration;

	/**
	 * Constructor.
	 *
	 * @param Yoast_Product_Meta_Cleanup_Migration $cleanup_migration Cleanup migration.
	 */
	public function __construct(
		Yoast_Product_Meta_Cleanup_Migration $cleanup_migration
	) {

		$this->cleanup_migration = $cleanup_migration;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_post_' . self::CLEANUP_ACTION,
			array(
				$this,
				'handle_cleanup_migration',
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
			'shurloc-product-migrations',
			SHURLOC_SITE_TOOLS_URL .
				'assets/product/css/shurloc-product-migrations.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			'shurloc-product-migrations',
			SHURLOC_SITE_TOOLS_URL .
				'assets/product/js/shurloc-product-migrations.js',
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

		$last_run = $this->cleanup_migration->get_last_run();

		$last_run_display = $this->get_last_run_display(
			last_run: $last_run,
		);

		$last_run_version =
			$this->cleanup_migration->get_last_run_version();

		?>
		<h2>Product Data Migrations</h2>

		<p>
			Controlled tools for cleaning and rebuilding product data.
		</p>

		<div class="card">
			<h2>Yoast Product Metadata Cleanup</h2>

			<p>
				Clears existing Yoast primary-category assignments and
				content-score metadata from WooCommerce products.
			</p>

			<p>
				<strong>
					This will clear all currently assigned primary product
					categories.
				</strong>
			</p>

			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row">Current migration version</th>
						<td>
							<?php
							echo esc_html(
								(string) Yoast_Product_Meta_Cleanup_Migration::VERSION
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
				data-confirm-message="Run the Yoast Product Metadata Cleanup migration? This will clear all existing primary product category assignments and Yoast content-score metadata."
			>
				<input
					type="hidden"
					name="action"
					value="<?php echo esc_attr( self::CLEANUP_ACTION ); ?>"
				/>

				<?php
				wp_nonce_field(
					self::CLEANUP_ACTION
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
						Run Yoast Metadata Cleanup
					</button>
				</p>
			</form>
		</div>

		<?php
		$this->render_migration_overlay();
	}

	/**
	 * Run the cleanup migration and build its result URL.
	 *
	 * @return string Redirect URL.
	 */
	public function run_cleanup_migration(): string {

		if ( ! $this->cleanup_migration->acquire_lock() ) {
			return $this->get_locked_redirect_url();
		}

		try {

			$result = $this->cleanup_migration->run();

			return $this->get_result_redirect_url(
				result: $result,
			);

		} finally {

			$this->cleanup_migration->release_lock();
		}
	}

	/**
	 * Process the cleanup migration request.
	 *
	 * @return void
	 */
	public function handle_cleanup_migration(): void {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to run this migration.',
					'shurloc-site-tools'
				)
			);
		}

		check_admin_referer(
			self::CLEANUP_ACTION
		);

		$redirect_url = $this->run_cleanup_migration();

		wp_safe_redirect(
			$redirect_url
		);

		exit;
	}

	/**
	 * Build the redirect URL for a completed migration.
	 *
	 * @param array{ examined: int, updated: int, skipped: int, errors: int } $result Migration result.
	 * @return string
	 */
	private function get_result_redirect_url(
		array $result
	): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'yoast-product-meta-cleanup',
				'examined'  => $result['examined'],
				'updated'   => $result['updated'],
				'skipped'   => $result['skipped'],
				'errors'    => $result['errors'],
				'_wpnonce'  => wp_create_nonce(
					self::RESULT_NONCE_ACTION
				),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the redirect URL when the migration is already running.
	 *
	 * @return string
	 */
	private function get_locked_redirect_url(): string {

		return add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tab'       => self::TAB_SLUG,
				'migration' => 'yoast-product-meta-cleanup-locked',
				'_wpnonce'  => wp_create_nonce(
					self::RESULT_NONCE_ACTION
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

		if (
			! wp_verify_nonce(
				$nonce,
				self::RESULT_NONCE_ACTION
			)
		) {
			return;
		}

		$migration = sanitize_key(
			wp_unslash( $_GET['migration'] )
		);

		if (
			'yoast-product-meta-cleanup-locked' ===
			$migration
		) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					echo esc_html__(
						'Yoast product metadata cleanup is already running. No second migration was started.',
						'shurloc-site-tools'
					);
					?>
				</p>
			</div>
			<?php

			return;
		}

		if (
			'yoast-product-meta-cleanup' !==
			$migration
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

		$notice_class = 0 === $errors
			? 'notice notice-success is-dismissible'
			: 'notice notice-warning is-dismissible';

		?>
		<div class="<?php echo esc_attr( $notice_class ); ?>">
			<p>
				<?php
				echo esc_html(
					sprintf(
						'Yoast product metadata cleanup complete. Examined: %d; Updated: %d; Skipped: %d; Errors: %d.',
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

		$formatted_last_run = wp_date(
			'F j, Y g:i a',
			$last_run
		);

		return false !== $formatted_last_run
			? $formatted_last_run
			: 'Never';
	}

	/**
	 * Determine whether the current request is the migrations page.
	 *
	 * @return bool
	 */
	private function is_migrations_page(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] )
			? sanitize_key(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_unslash( $_GET['page'] )
			)
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] )
			? sanitize_key(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_unslash( $_GET['tab'] )
			)
			: '';

		return (
			self::PAGE_SLUG === $page &&
			self::TAB_SLUG === $tab
		);
	}
}
