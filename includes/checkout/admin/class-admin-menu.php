<?php
/**
 * Checkout Tools admin menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Registers Checkout Tools admin UI.
 */
final class Admin_Menu {

	/**
	 * Parent ShurLoc Tools menu slug.
	 *
	 * @var string
	 */
	private const PARENT_MENU_SLUG = 'shurloc-tools';

	/**
	 * Checkout Tools menu slug.
	 *
	 * @var string
	 */
	private const CHECKOUT_MENU_SLUG = 'shurloc-checkout-tools';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Checkout menu position.
	 *
	 * @var int
	 */
	private const CHECKOUT_MENU_POSITION = 40;

	/**
	 * Checkout page.
	 *
	 * @var Admin_Page_Interface
	 */
	private Admin_Page_Interface $checkout_page;

	/**
	 * Constructor.
	 *
	 * @param Admin_Page_Interface $checkout_page Checkout page.
	 */
	public function __construct(
		Admin_Page_Interface $checkout_page
	) {
		$this->checkout_page = $checkout_page;
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
			self::CHECKOUT_MENU_POSITION
		);

		add_action(
			'shurloc_tools_overview',
			array( $this, 'render_overview_section' ),
			self::CHECKOUT_MENU_POSITION
		);
	}

	/**
	 * Register the Checkout Tools submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			self::PARENT_MENU_SLUG,
			'ShurLoc Checkout Tools',
			'Checkout',
			self::CAPABILITY,
			self::CHECKOUT_MENU_SLUG,
			array( $this->checkout_page, 'render_page' ),
			self::CHECKOUT_MENU_POSITION
		);
	}

	/**
	 * Render the Checkout Tools overview section.
	 *
	 * @return void
	 */
	public function render_overview_section(): void {
		?>
		<h2>Checkout</h2>

		<p>
			Checkout and payment tools.
		</p>

		<p>
			<a
				href="<?php echo esc_url( $this->get_checkout_tools_url() ); ?>"
				class="button button-primary"
			>
				Open Checkout Tools
			</a>
		</p>
		<?php
	}

	/**
	 * Get the Checkout Tools admin URL.
	 *
	 * @return string
	 */
	private function get_checkout_tools_url(): string {
		return add_query_arg(
			array(
				'page' => self::CHECKOUT_MENU_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}
}
