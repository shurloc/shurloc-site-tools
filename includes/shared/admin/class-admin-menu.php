<?php
/**
 * Shared Shur-loc Tools admin menu.
 *
 * Registers the Shur-loc Tools top-level menu, Overview submenu,
 * and shared overview rendering hook.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Shared\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the shared Shur-loc Tools admin menu.
 */
final class Admin_Menu {

	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'shurloc-tools';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_menu',
			array(
				$this,
				'register_menu',
			),
			10
		);
	}

	/**
	 * Register the shared Shur-loc Tools admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {

		add_menu_page(
			'Shur-loc Tools',
			'Shur-loc Tools',
			self::CAPABILITY,
			self::MENU_SLUG,
			array(
				$this,
				'render_overview_page',
			),
			'dashicons-admin-tools',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Shur-loc Tools',
			'Overview',
			self::CAPABILITY,
			self::MENU_SLUG,
			array(
				$this,
				'render_overview_page',
			),
			0
		);
	}

	/**
	 * Render the Shur-loc Tools overview page.
	 *
	 * Individual Shur-loc plugins contribute their own overview sections
	 * through the shurloc_tools_overview action.
	 *
	 * @return void
	 */
	public function render_overview_page(): void {
		?>

		<div class="wrap">

			<h1>Shur-loc Tools</h1>

			<p>
				Administrative tools for Shur-loc WordPress and WooCommerce operations.
			</p>

			<?php
			/**
			 * Fires when rendering the Shur-loc Tools overview page.
			 *
			 * Shur-loc plugins should use this action to render their own overview
			 * sections. Suggested priorities:
			 *
			 * 10 - Products
			 * 20 - Customers
			 * 30 - Checkout
			 *
			 * @since 0.6.0
			 */

			do_action( 'shurloc_tools_overview' );
			?>

		</div>

		<?php
	}
}
