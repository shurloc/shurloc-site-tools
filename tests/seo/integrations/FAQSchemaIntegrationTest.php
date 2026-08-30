<?php
/**
 * Tests for the FAQ schema integration.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Integrations;

use PHPUnit\Framework\TestCase;
use Shurloc\SiteTools\SEO\Generators\FAQ_Schema_Generator;
use Shurloc\SiteTools\SEO\Parsers\FAQ_Schema_Parser;
use WP_Post;

/**
 * Tests the FAQ schema integration.
 */
final class FAQSchemaIntegrationTest extends TestCase {

	/**
	 * Parser.
	 *
	 * @var FAQ_Schema_Parser
	 */
	private FAQ_Schema_Parser $parser;

	/**
	 * Generator.
	 *
	 * @var FAQ_Schema_Generator
	 */
	private FAQ_Schema_Generator $generator;

	/**
	 * Integration under test.
	 *
	 * @var FAQ_Schema_Integration
	 */
	private FAQ_Schema_Integration $integration;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_page_id']          = 0;
		$GLOBALS['shurloc_test_post']             = null;
		$GLOBALS['shurloc_test_filtered_content'] = null;

		$this->parser = new FAQ_Schema_Parser();

		$this->generator = new FAQ_Schema_Generator();

		$this->integration = new FAQ_Schema_Integration(
			parser: $this->parser,
			generator: $this->generator,
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']          = array();
		$GLOBALS['shurloc_test_action_metadata']  = array();
		$GLOBALS['shurloc_test_page_id']          = 0;
		$GLOBALS['shurloc_test_post']             = null;
		$GLOBALS['shurloc_test_filtered_content'] = null;

		parent::tearDown();
	}

	/**
	 * Verify the integration registers its wp_head action.
	 *
	 * @return void
	 */
	public function test_register_adds_wp_head_action(): void {

		$this->integration->register();

		self::assertContains(
			array(
				$this->integration,
				'render_schema',
			),
			$GLOBALS['shurloc_test_actions']['wp_head']
		);

		self::assertSame(
			99,
			$GLOBALS['shurloc_test_action_metadata']
				['wp_head'][0]['priority']
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_action_metadata']
				['wp_head'][0]['accepted_args']
		);
	}

	/**
	 * Verify schema is not rendered on other pages.
	 *
	 * @return void
	 */
	public function test_render_schema_skips_other_pages(): void {

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify schema is not rendered when the current post is missing.
	 *
	 * @return void
	 */
	public function test_render_schema_skips_missing_post(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify schema is not rendered for empty content.
	 *
	 * @return void
	 */
	public function test_render_schema_skips_empty_content(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		$GLOBALS['shurloc_test_post'] =
			new WP_Post(
				(object) array(
					'ID'           => 2190,
					'post_content' => '',
				)
			);

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify schema is not rendered when no FAQ items are found.
	 *
	 * @return void
	 */
	public function test_render_schema_skips_content_without_faq_items(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		$GLOBALS['shurloc_test_post'] =
			new WP_Post(
				(object) array(
					'ID'           => 2190,
					'post_content' => '
						<h3>General Information</h3>
						<p>
							This heading is not a question and should not
							produce FAQ schema.
						</p>
					',
				)
			);

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify valid FAQ schema is rendered.
	 *
	 * @return void
	 */
	public function test_render_schema_outputs_faq_json_ld(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		$GLOBALS['shurloc_test_post'] =
			new WP_Post(
				(object) array(
					'ID'           => 2190,
					'post_content' => '
						<h3>What is Shur-loc mesh?</h3>
						<p>
							Shur-loc mesh is used for screening and filtration
							applications in many industries.
						</p>
					',
				)
			);

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'<script type="application/ld+json">',
			$output
		);

		self::assertStringContainsString(
			'"@context":"https://schema.org"',
			$output
		);

		self::assertStringContainsString(
			'"@type":"FAQPage"',
			$output
		);

		self::assertStringContainsString(
			'"name":"What is Shur-loc mesh?"',
			$output
		);

		self::assertStringContainsString(
			'"@type":"Answer"',
			$output
		);

		self::assertStringContainsString(
			'Shur-loc mesh is used for screening and filtration applications in many industries.',
			$output
		);
	}

	/**
	 * Verify filtered content is used rather than raw post content.
	 *
	 * @return void
	 */
	public function test_render_schema_uses_filtered_content(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		$GLOBALS['shurloc_test_post'] =
			new WP_Post(
				(object) array(
					'ID'           => 2190,
					'post_content' => '
						<h3>Raw content heading</h3>
					',
				)
			);

		$GLOBALS['shurloc_test_filtered_content'] = '
			<h3>What does filtered content contain?</h3>
			<p>
				Filtered content contains the rendered FAQ answer and is
				long enough to produce schema output.
			</p>
		';

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'What does filtered content contain?',
			$output
		);

		self::assertStringNotContainsString(
			'Raw content heading',
			$output
		);
	}

	/**
	 * Verify multiple FAQ items are rendered.
	 *
	 * @return void
	 */
	public function test_render_schema_outputs_multiple_faq_items(): void {

		$GLOBALS['shurloc_test_page_id'] = 2190;

		$GLOBALS['shurloc_test_post'] =
			new WP_Post(
				(object) array(
					'ID'           => 2190,
					'post_content' => '
						<h3>What sizes are available?</h3>
						<p>
							Many mesh sizes are available for different
							screening applications and requirements.
						</p>

						<h3>What materials are available?</h3>
						<p>
							Several materials are available depending on the
							application and operating environment.
						</p>
					',
				)
			);

		ob_start();

		$this->integration->render_schema();

		$output = (string) ob_get_clean();

		self::assertStringContainsString(
			'What sizes are available?',
			$output
		);

		self::assertStringContainsString(
			'What materials are available?',
			$output
		);
	}
}
