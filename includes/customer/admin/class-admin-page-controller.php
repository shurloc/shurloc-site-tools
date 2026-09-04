<?php
/**
 * Customer admin page controller.
 *
 * Provides admin tools for customer functions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Customer admin page controller.
 */
final class Admin_Page_Controller implements Admin_Page_Interface {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'shurloc-site-tools-customers';

	/**
	 * Migrations controller.
	 *
	 * @var Customer_Migrations_Controller
	 */
	private Customer_Migrations_Controller $migrations_controller;

	/**
	 * Constructor.
	 *
	 * @param Customer_Migrations_Controller $migrations_controller Migrations controller.
	 */
	public function __construct(
		Customer_Migrations_Controller $migrations_controller
	) {

		$this->migrations_controller = $migrations_controller;
	}

	/**
	 * Render the Customer Tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {

		$current_tab = $this->get_current_tab();

		?>
		<div class="wrap">

			<h1>
				<?php
				echo esc_html__(
					'Customer Tools',
					'shurloc-site-tools'
				);
				?>
			</h1>

			<nav class="nav-tab-wrapper">
				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: 'overview' ) ); ?>"
					class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>"
				>
					<?php
					echo esc_html__(
						'Overview',
						'shurloc-site-tools'
					);
					?>
				</a>

				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: 'migrations' ) ); ?>"
					class="nav-tab <?php echo 'migrations' === $current_tab ? 'nav-tab-active' : ''; ?>"
				>
					<?php
					echo esc_html__(
						'Migrations',
						'shurloc-site-tools'
					);
					?>
				</a>
			</nav>

			<?php
			if ( 'migrations' === $current_tab ) {
				$this->migrations_controller->render();

				return;
			}

			$this->render_overview();
			?>

		</div>
		<?php
	}

	/**
	 * Render the overview tab.
	 *
	 * @return void
	 */
	private function render_overview(): void {
		?>
		<p>
			<?php
			echo esc_html__(
				'Utilities for customer administration.',
				'shurloc-site-tools'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Get the active tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		if ( ! isset( $_GET['tab'] ) ) {
			return 'overview';
		}

		$tab = sanitize_key(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
			wp_unslash( $_GET['tab'] )
		);

		if (
			in_array(
				$tab,
				array(
					'overview',
					'migrations',
				),
				true
			)
		) {
			return $tab;
		}

		return 'overview';
	}

	/**
	 * Get an admin tab URL.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function get_tab_url(
		string $tab
	): string {

		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}
}
