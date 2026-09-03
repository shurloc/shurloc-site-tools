<?php
/**
 * Primary product category metabox.
 *
 * Adds and manages the primary WooCommerce product category selector on the
 * product edit screen.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Product\Services\Primary_Product_Category_Service;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the primary product category metabox.
 */
final class Primary_Product_Category_Metabox {

	/**
	 * Metabox ID.
	 */
	private const METABOX_ID =
		'shurloc_primary_product_category';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION =
		'shurloc_save_primary_product_category';

	/**
	 * Nonce field name.
	 */
	private const NONCE_FIELD =
		'shurloc_primary_product_category_nonce';

	/**
	 * Form field name.
	 */
	private const FIELD_NAME =
		'shurloc_primary_product_category';

	/**
	 * Product post type.
	 */
	private const POST_TYPE = 'product';

	/**
	 * Product category taxonomy.
	 */
	private const TAXONOMY = 'product_cat';

	/**
	 * Primary category service.
	 *
	 * @var Primary_Product_Category_Service
	 */
	private Primary_Product_Category_Service $service;

	/**
	 * Constructor.
	 *
	 * @param Primary_Product_Category_Service $service Primary category service.
	 */
	public function __construct(
		Primary_Product_Category_Service $service
	) {

		$this->service = $service;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'add_meta_boxes_product',
			array(
				$this,
				'add_metabox',
			)
		);

		add_action(
			'save_post_product',
			array(
				$this,
				'save',
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
	 * Add the primary category metabox.
	 *
	 * @return void
	 */
	public function add_metabox(): void {

		add_meta_box(
			self::METABOX_ID,
			'Primary Category',
			array(
				$this,
				'render',
			),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render the primary category metabox.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public function render(
		WP_Post $post
	): void {

		$selected =
			$this->service->get_primary_category_id(
				product_id: $post->ID,
			);

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		wp_nonce_field(
			self::NONCE_ACTION,
			self::NONCE_FIELD
		);

		if ( is_wp_error( $terms ) ) {
			echo esc_html__(
				'Product categories could not be loaded.',
				'shurloc-site-tools'
			);

			return;
		}

		$tree = $this->build_term_tree(
			terms: $terms,
		);

		?>
		<p>
			<label
				for="<?php echo esc_attr( self::FIELD_NAME ); ?>"
				class="screen-reader-text"
			>
				<?php
				echo esc_html__(
					'Primary Category',
					'shurloc-site-tools'
				);
				?>
			</label>

			<select
				id="<?php echo esc_attr( self::FIELD_NAME ); ?>"
				name="<?php echo esc_attr( self::FIELD_NAME ); ?>"
				class="shurloc-primary-product-category"
				style="width:100%;"
			>
				<option
					value="0"
					<?php selected( $selected, 0 ); ?>
				>
					<?php
					echo esc_html__(
						'No Category',
						'shurloc-site-tools'
					);
					?>
				</option>

				<?php
				$this->render_term_options(
					tree: $tree,
					selected: $selected,
				);
				?>
			</select>
		</p>
		<?php
	}

	/**
	 * Save the selected primary category.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save(
		int $post_id
	): void {

		if (
		defined( 'DOING_AUTOSAVE' ) &&
		DOING_AUTOSAVE
		) {
			return;
		}

		if (
		! current_user_can(
			'edit_post',
			$post_id
		)
		) {
			return;
		}

		if (
		! isset(
			$_POST[ self::NONCE_FIELD ]
		)
		) {
			return;
		}

		$nonce = sanitize_text_field(
			wp_unslash(
				$_POST[ self::NONCE_FIELD ]
			)
		);

		if (
		! wp_verify_nonce(
			$nonce,
			self::NONCE_ACTION
		)
		) {
			return;
		}

		if (
		! isset(
			$_POST[ self::FIELD_NAME ]
		)
		) {
			return;
		}

		$value = sanitize_text_field(
			wp_unslash(
				$_POST[ self::FIELD_NAME ]
			)
		);

		$term_id = absint( $value );

		if ( 0 === $term_id ) {

			$this->service->clear_primary_category(
				product_id: $post_id,
			);

			return;
		}

		$this->service->set_primary_category(
			product_id: $post_id,
			term_id: $term_id,
		);
	}

	/**
	 * Enqueue metabox assets on product edit screens.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets(
		string $hook_suffix
	): void {

		if (
		'post.php' !== $hook_suffix &&
		'post-new.php' !== $hook_suffix
		) {
			return;
		}

		$screen = get_current_screen();

		if (
		null === $screen ||
		self::POST_TYPE !== $screen->post_type
		) {
			return;
		}

		wp_enqueue_script(
			'selectWoo'
		);

		wp_enqueue_style(
			'select2'
		);

		wp_enqueue_style(
			'shurloc-primary-product-category',
			SHURLOC_SITE_TOOLS_URL .
			'assets/product/css/shurloc-primary-product-category.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);

		wp_enqueue_script(
			'shurloc-primary-product-category',
			SHURLOC_SITE_TOOLS_URL .
			'assets/product/js/shurloc-primary-product-category.js',
			array(
				'jquery',
				'selectWoo',
			),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);
	}

	/**
	 * Build a product category tree indexed by parent term ID.
	 *
	 * @param array<int,WP_Term> $terms Product category terms.
	 * @return array<int,array<int,WP_Term>>
	 */
	private function build_term_tree(
		array $terms
	): array {

		$tree = array();

		foreach ( $terms as $term ) {
			$tree[ $term->parent ][] = $term;
		}

		return $tree;
	}

	/**
	 * Render hierarchical product category options.
	 *
	 * @param array<int,array<int,WP_Term>> $tree      Category tree.
	 * @param int                           $selected  Selected term ID.
	 * @param int                           $parent_id Parent term ID.
	 * @param int                           $level     Hierarchy level.
	 * @return void
	 */
	private function render_term_options(
		array $tree,
		int $selected,
		int $parent_id = 0,
		int $level = 0
	): void {

		if ( empty( $tree[ $parent_id ] ) ) {
			return;
		}

		foreach ( $tree[ $parent_id ] as $term ) {

			$indent = str_repeat(
				'— ',
				$level
			);

			?>
			<option
				value="<?php echo esc_attr( (string) $term->term_id ); ?>"
			<?php selected( $selected, $term->term_id ); ?>
			>
			<?php
			echo esc_html(
				$indent . $term->name
			);
			?>
			</option>
				<?php

				$this->render_term_options(
					tree: $tree,
					selected: $selected,
					parent_id: $term->term_id,
					level: $level + 1,
				);
		}
	}
}
