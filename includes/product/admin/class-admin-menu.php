<?php
/**
 * Product Tools admin menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Registers Product Tools admin UI.
 */
final class Admin_Menu {

	/**
	 * Parent ShurLoc Tools menu slug.
	 */
	private const PARENT_MENU_SLUG = 'shurloc-site-tools';

	/**
	 * Product Tools menu slug.
	 */
	private const PRODUCT_MENU_SLUG = 'shurloc-site-tools-products';

	/**
	 * Required capability.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Product menu position.
	 */
	private const PRODUCT_MENU_POSITION = 20;

	/**
	 * Product page.
	 *
	 * @var Admin_Page_Interface
	 */
	private Admin_Page_Interface $product_page;

	/**
	 * Constructor.
	 *
	 * @param Admin_Page_Interface $product_page Product page.
	 */
	public function __construct(
		Admin_Page_Interface $product_page
	) {
		$this->product_page = $product_page;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_menu',
			array( $this, 'register_menu' ),
			self::PRODUCT_MENU_POSITION
		);

		add_action(
			'shurloc_site_tools_overview',
			array( $this, 'render_overview_section' ),
			self::PRODUCT_MENU_POSITION
		);
	}

	/**
	 * Register the Product Tools submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			'ShurLoc Product Tools',
			'Products',
			self::CAPABILITY,
			self::PRODUCT_MENU_SLUG,
			array( $this->product_page, 'render_page' ),
			self::PRODUCT_MENU_POSITION
		);
	}

	/**
	 * Render the Product Tools overview section.
	 *
	 * @return void
	 */
	public function render_overview_section(): void {
		?>
		<h2>Products</h2>

		<ul>
			<li><?php echo esc_html__( 'Catalog reports and mesh-product reviews.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Mesh product tables.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Structured data and primary-category support.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Product navigation and recommendations.', 'shurloc-site-tools' ); ?></li>
			<li><?php echo esc_html__( 'Product data migrations.', 'shurloc-site-tools' ); ?></li>
		</ul>

		<p>
			<a
				href="<?php echo esc_url( $this->get_product_tools_url() ); ?>"
				class="button button-primary"
			>
				Open Product Tools
			</a>
		</p>
		<?php
	}

	/**
	 * Get the Product Tools admin URL.
	 *
	 * @return string
	 */
	private function get_product_tools_url(): string {

		return add_query_arg(
			array(
				'page' => self::PRODUCT_MENU_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}
}
