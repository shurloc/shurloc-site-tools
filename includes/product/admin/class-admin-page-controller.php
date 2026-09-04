<?php
/**
 * Product Tools admin page controller.
 *
 * Renders the Product Tools admin page shell and routes requests to the
 * appropriate tab controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and routes the Product Tools admin page.
 */
final class Admin_Page_Controller implements Admin_Page_Interface {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG =
		'shurloc-site-tools-products';

	/**
	 * Catalog report tab slug.
	 */
	private const CATALOG_REPORT_TAB =
		'catalog-report';

	/**
	 * Invalid mesh products tab slug.
	 */
	private const INVALID_MESH_TAB =
		'invalid-mesh-products';

	/**
	 * Unrecognized mesh products tab slug.
	 */
	private const UNRECOGNIZED_MESH_TAB =
		'unrecognized-mesh-products';

	/**
	 * Migrations tab slug.
	 */
	private const MIGRATIONS_TAB =
		'migrations';

	/**
	 * Catalog report controller.
	 *
	 * @var Catalog_Report_Controller
	 */
	private Catalog_Report_Controller $catalog_report_controller;

	/**
	 * Product migrations controller.
	 *
	 * @var Product_Migrations_Controller
	 */
	private Product_Migrations_Controller $migrations_controller;

	/**
	 * Constructor.
	 *
	 * @param Catalog_Report_Controller     $catalog_report_controller Catalog report controller.
	 * @param Product_Migrations_Controller $migrations_controller     Product migrations controller.
	 */
	public function __construct(
		Catalog_Report_Controller $catalog_report_controller,
		Product_Migrations_Controller $migrations_controller
	) {

		$this->catalog_report_controller =
			$catalog_report_controller;

		$this->migrations_controller =
			$migrations_controller;
	}

	/**
	 * Render the Product Tools admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {

		$active_tab = $this->get_active_tab();

		?>
		<div class="wrap">
			<h1>
				<?php
				echo esc_html__(
					'Shur-loc Product Tools',
					'shurloc-site-tools'
				);
				?>
			</h1>

			<p>
				<?php
				echo esc_html__(
					'Product administration, reporting, migrations, and catalog tools.',
					'shurloc-site-tools'
				);
				?>
			</p>

			<?php
			$this->render_tabs(
				active_tab: $active_tab,
			);

			$this->render_active_tab(
				active_tab: $active_tab,
			);
			?>
		</div>
		<?php
	}

	/**
	 * Render the Product Tools tab navigation.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_tabs(
		string $active_tab
	): void {

		$tabs = array(
			self::CATALOG_REPORT_TAB    => __(
				'Catalog Report',
				'shurloc-site-tools'
			),
			self::INVALID_MESH_TAB      => __(
				'Invalid Mesh Products',
				'shurloc-site-tools'
			),
			self::UNRECOGNIZED_MESH_TAB => __(
				'Unrecognized Mesh Products',
				'shurloc-site-tools'
			),
			self::MIGRATIONS_TAB        => __(
				'Migrations',
				'shurloc-site-tools'
			),
		);

		?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_slug => $label ) : ?>
				<a
					href="<?php echo esc_url( $this->get_tab_url( tab: $tab_slug ) ); ?>"
					class="<?php echo esc_attr( $this->get_tab_class( tab: $tab_slug, active_tab: $active_tab ) ); ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render the active Product Tools tab.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_active_tab(
		string $active_tab
	): void {

		switch ( $active_tab ) {

			case self::INVALID_MESH_TAB:
				$this->catalog_report_controller
					->render_invalid_mesh_products();
				break;

			case self::UNRECOGNIZED_MESH_TAB:
				$this->catalog_report_controller
					->render_unrecognized_mesh_products();
				break;

			case self::MIGRATIONS_TAB:
				$this->migrations_controller->render();
				break;

			case self::CATALOG_REPORT_TAB:
			default:
				$this->catalog_report_controller
					->render_catalog_report();
				break;
		}
	}

	/**
	 * Get the currently selected tab.
	 *
	 * Invalid or missing tab values fall back to the catalog report.
	 *
	 * @return string
	 */
	private function get_active_tab(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation value.
		$tab = isset( $_GET['tab'] )
			? sanitize_key(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation value.
				wp_unslash( $_GET['tab'] )
			)
			: self::CATALOG_REPORT_TAB;

		$valid_tabs = array(
			self::CATALOG_REPORT_TAB,
			self::INVALID_MESH_TAB,
			self::UNRECOGNIZED_MESH_TAB,
			self::MIGRATIONS_TAB,
		);

		if (
			! in_array(
				$tab,
				$valid_tabs,
				true
			)
		) {
			return self::CATALOG_REPORT_TAB;
		}

		return $tab;
	}

	/**
	 * Build an admin URL for a Product Tools tab.
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

	/**
	 * Get the CSS class for a navigation tab.
	 *
	 * @param string $tab        Tab slug.
	 * @param string $active_tab Active tab slug.
	 * @return string
	 */
	private function get_tab_class(
		string $tab,
		string $active_tab
	): string {

		if ( $tab === $active_tab ) {
			return 'nav-tab nav-tab-active';
		}

		return 'nav-tab';
	}
}
