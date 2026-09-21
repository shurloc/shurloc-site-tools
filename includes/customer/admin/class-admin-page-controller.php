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

use Shurloc\SiteTools\Customer\Journey\Admin\Journey_Report_Controller;
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
	 * Carts controller.
	 *
	 * @var Carts_Controller
	 */
	private Carts_Controller $carts_controller;

	/**
	 * Journey report controller when reporting has been wired.
	 *
	 * @var Journey_Report_Controller|null
	 */
	private ?Journey_Report_Controller $journey_report_controller;

	/**
	 * Constructor.
	 *
	 * @param Customer_Migrations_Controller $migrations_controller     Migrations controller.
	 * @param Carts_Controller               $carts_controller          Carts controller.
	 * @param Journey_Report_Controller|null $journey_report_controller Journey report controller.
	 */
	public function __construct(
		Customer_Migrations_Controller $migrations_controller,
		Carts_Controller $carts_controller,
		?Journey_Report_Controller $journey_report_controller = null
	) {

		$this->migrations_controller     = $migrations_controller;
		$this->carts_controller          = $carts_controller;
		$this->journey_report_controller = $journey_report_controller;
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

				<?php if ( null !== $this->journey_report_controller ) : ?>
					<a
						href="<?php echo esc_url( $this->get_tab_url( tab: Journey_Report_Controller::TAB_SLUG ) ); ?>"
						class="nav-tab <?php echo Journey_Report_Controller::TAB_SLUG === $current_tab ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html__( 'Journeys', 'shurloc-site-tools' ); ?>
					</a>
				<?php endif; ?>

				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: 'carts' ) ); ?>"
					class="nav-tab <?php echo 'carts' === $current_tab ? 'nav-tab-active' : ''; ?>"
				>
					<?php
					echo esc_html__(
						'Carts',
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
			if ( 'carts' === $current_tab ) {
				$this->carts_controller->render();

				return;
			}

			if ( 'migrations' === $current_tab ) {
				$this->migrations_controller->render();

				return;
			}

			if ( Journey_Report_Controller::TAB_SLUG === $current_tab && null !== $this->journey_report_controller ) {
				$this->journey_report_controller->render();

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
				'Customer tools, listed by operational importance.',
				'shurloc-site-tools'
			);
			?>
		</p>

		<ul class="ul-disc">
			<li><?php echo esc_html__( 'Customer activity, purchase, and cart tracking.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Customer list columns for activity, purchases, carts, and phone numbers.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Customer list filters for activity and purchase information.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Customer data migrations for purchase tracking and cart data.', 'shurloc-site-tools' ); ?></li>
		</ul>
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

		$tabs = array(
			'overview',
			'carts',
			'migrations',
		);
		if ( null !== $this->journey_report_controller ) {
			$tabs[] = Journey_Report_Controller::TAB_SLUG;
		}

		if ( in_array( $tab, $tabs, true ) ) {
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
