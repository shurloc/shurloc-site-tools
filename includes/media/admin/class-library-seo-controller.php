<?php
/**
 * Media Library SEO controller.
 *
 * Adds SEO-related columns, sorting, and filtering to the WordPress Media
 * Library.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media\Admin;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Media\Services\SEO_Service;
use WP_Query;

/**
 * Manages Media Library SEO tools.
 */
final class Library_SEO_Controller {

	/**
	 * Alt Text column key.
	 */
	private const ALT_TEXT_COLUMN = 'alt_text';

	/**
	 * SEO Status column key.
	 */
	private const SEO_STATUS_COLUMN = 'seo_status';

	/**
	 * Alt Text filter query parameter.
	 */
	private const ALT_FILTER_PARAM = 'alt_filter';

	/**
	 * Missing Alt Text filter value.
	 */
	private const FILTER_MISSING = 'missing';

	/**
	 * Present Alt Text filter value.
	 */
	private const FILTER_PRESENT = 'present';

	/**
	 * Media SEO service.
	 *
	 * @var SEO_Service
	 */
	private SEO_Service $seo_service;

	/**
	 * Constructor.
	 *
	 * @param SEO_Service $seo_service Media SEO service.
	 */
	public function __construct(
		SEO_Service $seo_service
	) {

		$this->seo_service = $seo_service;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'manage_upload_columns',
			array(
				$this,
				'add_columns',
			)
		);

		add_action(
			'manage_media_custom_column',
			array(
				$this,
				'render_column',
			),
			10,
			2
		);

		add_filter(
			'manage_upload_sortable_columns',
			array(
				$this,
				'add_sortable_columns',
			)
		);

		add_action(
			'pre_get_posts',
			array(
				$this,
				'configure_media_query',
			)
		);

		add_action(
			'restrict_manage_posts',
			array(
				$this,
				'render_alt_text_filter',
			),
			10,
			1
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
	 * Add Alt Text and SEO Status columns to the Media Library.
	 *
	 * Columns are inserted immediately after the attachment title.
	 *
	 * @param array<string,string> $columns Existing Media Library columns.
	 * @return array<string,string>
	 */
	public function add_columns(
		array $columns
	): array {

		$new_columns = array();

		foreach ( $columns as $key => $label ) {

			$new_columns[ $key ] = $label;

			if ( 'title' !== $key ) {
				continue;
			}

			$new_columns[ self::ALT_TEXT_COLUMN ] =
				__(
					'Alt Text',
					'shurloc-site-tools'
				);

			$new_columns[ self::SEO_STATUS_COLUMN ] =
				__(
					'SEO Status',
					'shurloc-site-tools'
				);
		}

		return $new_columns;
	}

	/**
	 * Render a custom Media Library column.
	 *
	 * @param string $column_name   Column key.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function render_column(
		string $column_name,
		int $attachment_id
	): void {

		if ( self::ALT_TEXT_COLUMN === $column_name ) {

			$alt_text = $this->seo_service->get_alt_text(
				attachment_id: $attachment_id,
			);

			if ( '' !== $alt_text ) {
				echo esc_html( $alt_text );
				return;
			}

			?>
			<span class="shurloc-media-seo-missing">
				<?php
				echo esc_html__(
					'Missing Alt Text',
					'shurloc-site-tools'
				);
				?>
			</span>
			<?php

			return;
		}

		if ( self::SEO_STATUS_COLUMN !== $column_name ) {
			return;
		}

		if (
			$this->seo_service->has_alt_text(
				attachment_id: $attachment_id,
			)
		) {
			?>
			<span
				class="shurloc-media-seo-status shurloc-media-seo-status-good"
				aria-label="<?php echo esc_attr__( 'Alt text present', 'shurloc-site-tools' ); ?>"
				title="<?php echo esc_attr__( 'Alt text present', 'shurloc-site-tools' ); ?>"
			>
				✓
			</span>
			<?php

			return;
		}

		?>
		<span
			class="shurloc-media-seo-status shurloc-media-seo-status-missing"
			aria-label="<?php echo esc_attr__( 'Alt text missing', 'shurloc-site-tools' ); ?>"
			title="<?php echo esc_attr__( 'Alt text missing', 'shurloc-site-tools' ); ?>"
		>
			✗
		</span>
		<?php
	}

	/**
	 * Make the Alt Text column sortable.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string>
	 */
	public function add_sortable_columns(
		array $columns
	): array {

		$columns[ self::ALT_TEXT_COLUMN ] =
			self::ALT_TEXT_COLUMN;

		return $columns;
	}

	/**
	 * Configure Media Library sorting and Alt Text filtering.
	 *
	 * @param WP_Query $query WordPress query.
	 * @return void
	 */
	public function configure_media_query(
		WP_Query $query
	): void {

		global $pagenow;

		if (
			! is_admin() ||
			! $query->is_main_query() ||
			'upload.php' !== $pagenow
		) {
			return;
		}

		if (
			self::ALT_TEXT_COLUMN ===
			$query->get( 'orderby' )
		) {
			$query->set(
				'meta_key',
				SEO_Service::ALT_TEXT_META_KEY
			);

			$query->set(
				'orderby',
				'meta_value'
			);
		}

		$filter = $this->get_alt_text_filter();

		if ( self::FILTER_MISSING === $filter ) {

			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     =>
							SEO_Service::ALT_TEXT_META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     =>
							SEO_Service::ALT_TEXT_META_KEY,
						'value'   => '',
						'compare' => '=',
					),
				)
			);

			return;
		}

		if ( self::FILTER_PRESENT === $filter ) {

			$query->set(
				'meta_query',
				array(
					array(
						'key'     =>
							SEO_Service::ALT_TEXT_META_KEY,
						'value'   => '',
						'compare' => '!=',
					),
				)
			);
		}
	}

	/**
	 * Render the Alt Text Media Library filter.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_alt_text_filter(
		string $post_type
	): void {

		if ( 'attachment' !== $post_type ) {
			return;
		}

		$selected_filter = $this->get_alt_text_filter();

		?>
		<select name="<?php echo esc_attr( self::ALT_FILTER_PARAM ); ?>">
			<option value="">
				<?php
				echo esc_html__(
					'All Images',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_MISSING ); ?>"
				<?php selected( $selected_filter, self::FILTER_MISSING ); ?>
			>
				<?php
				echo esc_html__(
					'Missing Alt Text',
					'shurloc-site-tools'
				);
				?>
			</option>

			<option
				value="<?php echo esc_attr( self::FILTER_PRESENT ); ?>"
				<?php selected( $selected_filter, self::FILTER_PRESENT ); ?>
			>
				<?php
				echo esc_html__(
					'Has Alt Text',
					'shurloc-site-tools'
				);
				?>
			</option>
		</select>
		<?php
	}

	/**
	 * Enqueue Media Library SEO styles.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets(
		string $hook_suffix
	): void {

		if ( 'upload.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'shurloc-media-library-seo',
			SHURLOC_SITE_TOOLS_URL .
				'assets/media/css/shurloc-media-library-seo.css',
			array(),
			SHURLOC_SITE_TOOLS_VERSION
		);
	}

	/**
	 * Get the requested Alt Text filter.
	 *
	 * Unknown filter values are treated as no filter.
	 *
	 * @return string
	 */
	private function get_alt_text_filter(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Media Library filter.
		if ( ! isset( $_GET[ self::ALT_FILTER_PARAM ] ) ) {
			return '';
		}

		$filter = sanitize_key(
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Media Library filter.
				$_GET[ self::ALT_FILTER_PARAM ]
			)
		);

		if (
			self::FILTER_MISSING !== $filter &&
			self::FILTER_PRESENT !== $filter
		) {
			return '';
		}

		return $filter;
	}
}
