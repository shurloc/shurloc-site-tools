<?php
/**
 * Customer Tools admin menu.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Shared\Interfaces\Admin_Page_Interface;

/**
 * Registers Customer Tools admin UI.
 */
final class Admin_Menu {

	/**
	 * Parent Shur-loc Tools menu slug.
	 *
	 * @var string
	 */
	private const PARENT_MENU_SLUG = 'shurloc-tools';

	/**
	 * Customer Tools menu slug.
	 *
	 * @var string
	 */
	private const CUSTOMER_MENU_SLUG = 'shurloc-site-tools';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Customer menu position.
	 *
	 * @var int
	 */
	private const CUSTOMER_MENU_POSITION = 30;

	/**
	 * Customer page.
	 *
	 * @var Admin_Page_Interface
	 */
	private Admin_Page_Interface $customer_page;

	/**
	 * Constructor.
	 *
	 * @param Admin_Page_Interface $customer_page Customer page.
	 */
	public function __construct(
		Admin_Page_Interface $customer_page
	) {

		$this->customer_page = $customer_page;
	}

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
			self::CUSTOMER_MENU_POSITION
		);

		add_action(
			'shurloc_tools_overview',
			array(
				$this,
				'render_overview_section',
			),
			self::CUSTOMER_MENU_POSITION
		);
	}

	/**
	 * Register the Customer Tools submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__(
				'Shur-loc Customer Tools',
				'shurloc-site-tools'
			),
			__(
				'Customers',
				'shurloc-site-tools'
			),
			self::CAPABILITY,
			self::CUSTOMER_MENU_SLUG,
			array(
				$this->customer_page,
				'render_page',
			),
			self::CUSTOMER_MENU_POSITION
		);
	}

	/**
	 * Render the Customer Tools overview section.
	 *
	 * @return void
	 */
	public function render_overview_section(): void {
		?>
		<h2>
			<?php
			echo esc_html__(
				'Customers',
				'shurloc-site-tools'
			);
			?>
		</h2>

		<p>
			<?php
			echo esc_html__(
				'Customer tools.',
				'shurloc-site-tools'
			);
			?>
		</p>

		<p>
			<a
				href="<?php echo esc_url( $this->get_customer_tools_url() ); ?>"
				class="button button-primary"
			>
				<?php
				echo esc_html__(
					'Open Customer Tools',
					'shurloc-site-tools'
				);
				?>
			</a>
		</p>
		<?php
	}

	/**
	 * Get the Customer Tools admin URL.
	 *
	 * @return string
	 */
	private function get_customer_tools_url(): string {

		return add_query_arg(
			array(
				'page' => self::CUSTOMER_MENU_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}
}
