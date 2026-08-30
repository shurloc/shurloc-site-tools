<?php
/**
 * SEO domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\SEO\Generators\FAQ_Schema_Generator;
use Shurloc\SiteTools\SEO\Integrations\FAQ_Schema_Integration;
use Shurloc\SiteTools\SEO\Parsers\FAQ_Schema_Parser;

/**
 * Bootstrap the SEO domain.
 */
final class Bootstrap {

	/**
	 * Register SEO functionality.
	 *
	 * @return void
	 */
	public function register(): void {

		$faq_schema_parser = new FAQ_Schema_Parser();

		$faq_schema_generator = new FAQ_Schema_Generator();

		$faq_schema_integration = new FAQ_Schema_Integration(
			parser: $faq_schema_parser,
			generator: $faq_schema_generator,
		);

		$faq_schema_integration->register();
	}
}
