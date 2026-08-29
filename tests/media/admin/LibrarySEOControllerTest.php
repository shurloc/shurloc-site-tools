<?php
/**
 * Tests for the Media Library SEO controller.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media\Admin;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\Media\Services\SEO_Service;
use WP_Query;

/**
 * Tests the Media Library SEO controller.
 */
final class LibrarySEOControllerTest extends TestCase {

	/**
	 * Media SEO service.
	 *
	 * @var SEO_Service
	 */
	private SEO_Service $seo_service;

	/**
	 * Controller under test.
	 *
	 * @var Library_SEO_Controller
	 */
	private Library_SEO_Controller $controller;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		global $pagenow;

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_post_meta']       = array();
		$GLOBALS['shurloc_test_is_admin']        = true;
		$GLOBALS['shurloc_test_styles']          = array();
		$GLOBALS['shurloc_test_is_main_query']   = true;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only current admin page.
		$pagenow = 'upload.php';

		$_GET = array();

		$this->seo_service = new SEO_Service();

		$this->controller = new Library_SEO_Controller(
			seo_service: $this->seo_service,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		global $pagenow;

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$GLOBALS['shurloc_test_post_meta']       = array();
		$GLOBALS['shurloc_test_is_admin']        = true;
		$GLOBALS['shurloc_test_styles']          = array();
		$GLOBALS['shurloc_test_is_main_query']   = true;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset test-only current admin page.
		$pagenow = null;

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Verify the controller registers its WordPress hooks.
	 *
	 * @return void
	 */
	public function test_register_adds_media_library_hooks(): void {

		$this->controller->register();

		self::assertContains(
			array(
				$this->controller,
				'add_columns',
			),
			$GLOBALS['shurloc_test_filters']
				['manage_upload_columns']
		);

		self::assertContains(
			array(
				$this->controller,
				'render_column',
			),
			$GLOBALS['shurloc_test_actions']
				['manage_media_custom_column']
		);

		self::assertContains(
			array(
				$this->controller,
				'add_sortable_columns',
			),
			$GLOBALS['shurloc_test_filters']
				['manage_upload_sortable_columns']
		);

		self::assertContains(
			array(
				$this->controller,
				'configure_media_query',
			),
			$GLOBALS['shurloc_test_actions']
				['pre_get_posts']
		);

		self::assertContains(
			array(
				$this->controller,
				'render_alt_text_filter',
			),
			$GLOBALS['shurloc_test_actions']
				['restrict_manage_posts']
		);

		self::assertContains(
			array(
				$this->controller,
				'enqueue_assets',
			),
			$GLOBALS['shurloc_test_actions']
				['admin_enqueue_scripts']
		);
	}

	/**
	 * Verify the custom Media Library columns are added after the title.
	 *
	 * @return void
	 */
	public function test_add_columns_adds_alt_text_and_seo_status_columns(): void {

		$columns = array(
			'cb'     => 'Checkbox',
			'title'  => 'File',
			'author' => 'Author',
		);

		$result = $this->controller->add_columns(
			columns: $columns,
		);

		self::assertSame(
			array(
				'cb',
				'title',
				'alt_text',
				'seo_status',
				'author',
			),
			array_keys( $result )
		);

		self::assertSame(
			'Alt Text',
			$result['alt_text']
		);

		self::assertSame(
			'SEO Status',
			$result['seo_status']
		);
	}

	/**
	 * Verify the Alt Text column renders stored alt text.
	 *
	 * @return void
	 */
	public function test_render_column_shows_alt_text(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = 'Mesh product detail';

		ob_start();

		$this->controller->render_column(
			column_name: 'alt_text',
			attachment_id: 100,
		);

		$output = (string) ob_get_clean();

		self::assertSame(
			'Mesh product detail',
			trim( $output )
		);
	}

	/**
	 * Verify the Alt Text column displays a missing indicator when empty.
	 *
	 * @return void
	 */
	public function test_render_column_shows_missing_alt_text(): void {

		ob_start();

		$this->controller->render_column(
			column_name: 'alt_text',
			attachment_id: 100,
		);

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'Missing Alt Text',
			$output
		);

		self::assertStringContainsString(
			'shurloc-media-seo-missing',
			$output
		);
	}

	/**
	 * Verify SEO Status displays the good state when alt text exists.
	 *
	 * @return void
	 */
	public function test_render_column_shows_good_seo_status(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = 'Mesh product detail';

		ob_start();

		$this->controller->render_column(
			column_name: 'seo_status',
			attachment_id: 100,
		);

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'shurloc-media-seo-status-good',
			$output
		);

		self::assertStringContainsString(
			'Alt text present',
			$output
		);

		self::assertStringContainsString(
			'✓',
			$output
		);
	}

	/**
	 * Verify SEO Status displays the missing state when alt text is absent.
	 *
	 * @return void
	 */
	public function test_render_column_shows_missing_seo_status(): void {

		ob_start();

		$this->controller->render_column(
			column_name: 'seo_status',
			attachment_id: 100,
		);

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'shurloc-media-seo-status-missing',
			$output
		);

		self::assertStringContainsString(
			'Alt text missing',
			$output
		);

		self::assertStringContainsString(
			'✗',
			$output
		);
	}

	/**
	 * Verify unrelated custom columns produce no output.
	 *
	 * @return void
	 */
	public function test_render_column_ignores_unknown_column(): void {

		ob_start();

		$this->controller->render_column(
			column_name: 'unknown',
			attachment_id: 100,
		);

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify the Alt Text column is sortable.
	 *
	 * @return void
	 */
	public function test_add_sortable_columns_adds_alt_text(): void {

		$columns = array(
			'title' => 'title',
		);

		$result = $this->controller->add_sortable_columns(
			columns: $columns,
		);

		self::assertSame(
			'alt_text',
			$result['alt_text']
		);

		self::assertSame(
			'title',
			$result['title']
		);
	}

	/**
	 * Verify Alt Text sorting uses attachment alt metadata.
	 *
	 * @return void
	 */
	public function test_configure_media_query_sets_alt_text_sorting(): void {

		$query = new WP_Query();

		$query->set(
			'orderby',
			'alt_text'
		);

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			SEO_Service::ALT_TEXT_META_KEY,
			$query->get( 'meta_key' )
		);

		self::assertSame(
			'meta_value',
			$query->get( 'orderby' )
		);
	}

	/**
	 * Verify the missing Alt Text filter configures the expected meta query.
	 *
	 * @return void
	 */
	public function test_configure_media_query_filters_missing_alt_text(): void {

		$_GET['alt_filter'] = 'missing';

		$query = new WP_Query();

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			array(
				'relation' => 'OR',
				array(
					'key'     => SEO_Service::ALT_TEXT_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => SEO_Service::ALT_TEXT_META_KEY,
					'value'   => '',
					'compare' => '=',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify the present Alt Text filter configures the expected meta query.
	 *
	 * @return void
	 */
	public function test_configure_media_query_filters_present_alt_text(): void {

		$_GET['alt_filter'] = 'present';

		$query = new WP_Query();

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			array(
				array(
					'key'     => SEO_Service::ALT_TEXT_META_KEY,
					'value'   => '',
					'compare' => '!=',
				),
			),
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify an unknown Alt Text filter is ignored.
	 *
	 * @return void
	 */
	public function test_configure_media_query_ignores_unknown_filter(): void {

		$_GET['alt_filter'] = 'invalid';

		$query = new WP_Query();

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			'',
			$query->get( 'meta_query' )
		);
	}

	/**
	 * Verify non-admin queries are not modified.
	 *
	 * @return void
	 */
	public function test_configure_media_query_skips_non_admin_requests(): void {

		$GLOBALS['shurloc_test_is_admin'] = false;

		$query = new WP_Query();

		$query->set(
			'orderby',
			'alt_text'
		);

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			'alt_text',
			$query->get( 'orderby' )
		);

		self::assertSame(
			'',
			$query->get( 'meta_key' )
		);
	}

	/**
	 * Verify secondary queries are not modified.
	 *
	 * @return void
	 */
	public function test_configure_media_query_skips_secondary_queries(): void {

		$query = new WP_Query();

		$GLOBALS['shurloc_test_is_main_query'] = false;

		$query->set(
			'orderby',
			'alt_text'
		);

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			'alt_text',
			$query->get( 'orderby' )
		);
	}

	/**
	 * Verify non-Media-Library admin queries are not modified.
	 *
	 * @return void
	 */
	public function test_configure_media_query_skips_other_admin_pages(): void {

		global $pagenow;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test-only current admin page.
		$pagenow = 'edit.php';

		$query = new WP_Query();

		$query->set(
			'orderby',
			'alt_text'
		);

		$this->controller->configure_media_query(
			query: $query,
		);

		self::assertSame(
			'alt_text',
			$query->get( 'orderby' )
		);
	}

	/**
	 * Verify the Alt Text filter is rendered for attachments.
	 *
	 * @return void
	 */
	public function test_render_alt_text_filter_shows_attachment_filter(): void {

		ob_start();

		$this->controller->render_alt_text_filter(
			post_type: 'attachment',
		);

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'name="alt_filter"',
			$output
		);

		self::assertStringContainsString(
			'All Images',
			$output
		);

		self::assertStringContainsString(
			'Missing Alt Text',
			$output
		);

		self::assertStringContainsString(
			'Has Alt Text',
			$output
		);
	}

	/**
	 * Verify the Alt Text filter is not rendered for other post types.
	 *
	 * @return void
	 */
	public function test_render_alt_text_filter_skips_other_post_types(): void {

		ob_start();

		$this->controller->render_alt_text_filter(
			post_type: 'post',
		);

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify the selected Alt Text filter is reflected in the UI.
	 *
	 * @return void
	 */
	public function test_render_alt_text_filter_marks_current_filter_selected(): void {

		$_GET['alt_filter'] = 'missing';

		ob_start();

		$this->controller->render_alt_text_filter(
			post_type: 'attachment',
		);

		$output = (string) ob_get_clean();

		self::assertMatchesRegularExpression(
			'/value="missing"[^>]*selected="selected"/',
			$output
		);
	}

	/**
	 * Verify Media Library SEO styles are enqueued on upload.php.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_on_media_library(): void {

		$this->controller->enqueue_assets(
			hook_suffix: 'upload.php',
		);

		self::assertArrayHasKey(
			'shurloc-media-library-seo',
			$GLOBALS['shurloc_test_styles']
		);

		self::assertSame(
			SHURLOC_SITE_TOOLS_URL .
				'assets/media/css/shurloc-media-library-seo.css',
			$GLOBALS['shurloc_test_styles']
				['shurloc-media-library-seo']['src']
		);
	}

	/**
	 * Verify Media Library SEO styles are not enqueued elsewhere.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_other_admin_pages(): void {

		$this->controller->enqueue_assets(
			hook_suffix: 'edit.php',
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_styles']
		);
	}
}
