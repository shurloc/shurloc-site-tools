<?php
/**
 * FAQ schema integration.
 *
 * Renders FAQPage structured data for the configured FAQ page.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Integrations;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\SEO\Generators\FAQ_Schema_Generator;
use Shurloc\SiteTools\SEO\Parsers\FAQ_Schema_Parser;
use WP_Post;

/**
 * Integrates FAQ schema generation with WordPress.
 */
final class FAQ_Schema_Integration {

	/**
	 * FAQ page ID.
	 *
	 * @var int
	 */
	private const FAQ_PAGE_ID = 2190;

	/**
	 * FAQ schema parser.
	 *
	 * @var FAQ_Schema_Parser
	 */
	private FAQ_Schema_Parser $parser;

	/**
	 * FAQ schema generator.
	 *
	 * @var FAQ_Schema_Generator
	 */
	private FAQ_Schema_Generator $generator;

	/**
	 * Constructor.
	 *
	 * @param FAQ_Schema_Parser    $parser    FAQ schema parser.
	 * @param FAQ_Schema_Generator $generator FAQ schema generator.
	 */
	public function __construct(
		FAQ_Schema_Parser $parser,
		FAQ_Schema_Generator $generator
	) {

		$this->parser    = $parser;
		$this->generator = $generator;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_head',
			array(
				$this,
				'render_schema',
			),
			99
		);
	}

	/**
	 * Render FAQPage structured data.
	 *
	 * @return void
	 */
	public function render_schema(): void {

		if ( ! is_page( self::FAQ_PAGE_ID ) ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$content = apply_filters(
			'the_content',
			$post->post_content
		);

		if (
			! is_string( $content ) ||
			'' === trim( $content )
		) {
			return;
		}

		$faq_items = $this->parser->parse(
			content: $content,
		);

		if ( empty( $faq_items ) ) {
			return;
		}

		$schema = $this->generator->generate(
			faq_items: $faq_items,
		);

		if ( empty( $schema ) ) {
			return;
		}

		$encoded_schema = wp_json_encode(
			$schema,
			JSON_UNESCAPED_UNICODE |
				JSON_UNESCAPED_SLASHES
		);

		if ( false === $encoded_schema ) {
			return;
		}

		echo "\n";
		?>
		<script type="application/ld+json">
			<?php
			echo wp_kses(
				$encoded_schema,
				array()
			);
			?>
		</script>
		<?php
		echo "\n";
	}
}
