<?php
/**
 * Checkout admin page controller.
 *
 * Provides admin tools for checkout functions.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Checkout admin page controller.
 */
final class Admin_Page_Controller implements Admin_Page_Interface {

	/**
	 * Overview tab slug.
	 *
	 * @var string
	 */
	private const OVERVIEW_TAB = 'overview';

	/**
	 * Tariff fees tab slug.
	 *
	 * @var string
	 */
	private const TARIFF_FEES_TAB = 'tariff-fees';

	/**
	 * Checkout Tools settings page.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $settings_page;

	/**
	 * Constructor.
	 *
	 * @param Settings_Page $settings_page Checkout Tools settings page.
	 */
	public function __construct(
		Settings_Page $settings_page
	) {
		$this->settings_page = $settings_page;
	}

	/**
	 * Render the Checkout Tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = $this->get_current_tab();

		?>
		<div class="wrap">
			<h1>Checkout Tools</h1>

			<nav class="nav-tab-wrapper">
				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: self::OVERVIEW_TAB ) ); ?>"
					class="nav-tab <?php echo self::OVERVIEW_TAB === $current_tab ? 'nav-tab-active' : ''; ?>"
				>
					Overview
				</a>

				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: self::TARIFF_FEES_TAB ) ); ?>"
					class="nav-tab <?php echo self::TARIFF_FEES_TAB === $current_tab ? 'nav-tab-active' : ''; ?>"
				>
					Tariff Fees
				</a>
			</nav>

			<?php
			if ( self::TARIFF_FEES_TAB === $current_tab ) {
				$this->settings_page->render_tariff_fees_tab();

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
			Checkout and payment tools.
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
			return self::OVERVIEW_TAB;
		}

		$tab = sanitize_key(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
			wp_unslash( $_GET['tab'] )
		);

		if (
			in_array(
				$tab,
				array(
					self::OVERVIEW_TAB,
					self::TARIFF_FEES_TAB,
				),
				true
			)
		) {
			return $tab;
		}

		return self::OVERVIEW_TAB;
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
				'page' => Settings_Page::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}
}
