<?php
/**
 * Customer Journey schema administration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Shows schema failures and provides an authorized migration retry.
 */
final class Journey_Schema_Admin {

	/**
	 * Capability required to retry a schema migration.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * WordPress admin-post action and nonce action.
	 */
	private const RETRY_ACTION = 'shurloc_retry_journey_schema';

	/**
	 * Schema migrator.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator $migrator Schema migrator.
	 */
	public function __construct( Journey_Schema_Migrator $migrator ) {
		$this->migrator = $migrator;
	}

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action(
			'admin_post_' . self::RETRY_ACTION,
			array( $this, 'handle_retry' )
		);
	}

	/**
	 * Show a notice while the current Journey schema is unavailable.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! current_user_can( self::CAPABILITY ) || $this->migrator->is_ready() ) {
			return;
		}

		$newer_schema = Journey_Schema_Migrator::CURRENT_VERSION <
			$this->migrator->get_installed_version();

		if ( $newer_schema ) {
			$message = esc_html__(
				'The installed database schema is newer than this plugin supports. Journey features are unavailable until the plugin is updated.',
				'shurloc-site-tools'
			);
		} elseif ( 'migration_failed' === $this->migrator->get_failure_code() ) {
			$message = esc_html__(
				'The database migration failed. Journey features are unavailable until it succeeds.',
				'shurloc-site-tools'
			);
		} else {
			$message = esc_html__(
				'The database schema needs an upgrade before Journey features can run.',
				'shurloc-site-tools'
			);
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php echo esc_html__( 'Customer Journey:', 'shurloc-site-tools' ); ?></strong> <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by esc_html__ above. ?></p>
			<?php if ( ! $newer_schema ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RETRY_ACTION ); ?>" />
					<?php wp_nonce_field( self::RETRY_ACTION ); ?>
					<?php submit_button( __( 'Retry Journey schema migration', 'shurloc-site-tools' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Process an authorized retry, then return to the Customer admin page.
	 *
	 * @return void
	 */
	public function handle_retry(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__(
					'You are not allowed to retry the Journey schema migration.',
					'shurloc-site-tools'
				)
			);
		}

		if ( false === check_admin_referer( self::RETRY_ACTION ) ) {
			wp_die(
				esc_html__(
					'The Journey schema retry request could not be verified.',
					'shurloc-site-tools'
				)
			);
		}

		if (
			Journey_Schema_Migrator::CURRENT_VERSION <
			$this->migrator->get_installed_version()
		) {
			wp_die(
				esc_html__(
					'A newer Journey schema cannot be retried with this plugin version.',
					'shurloc-site-tools'
				)
			);
		}

		$this->migrator->migrate();

		wp_safe_redirect(
			admin_url( 'admin.php?page=shurloc-site-tools-customers' )
		);

		exit;
	}
}
