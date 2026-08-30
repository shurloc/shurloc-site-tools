<?php
/**
 * FAQ schema generator.
 *
 * Converts normalized FAQ data into Schema.org FAQPage structured data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Generators;

defined( 'ABSPATH' ) || exit;

/**
 * Generates FAQPage schema data.
 *
 * @phpstan-type FAQItem array{
 *     question:string,
 *     answer:string
 * }
 *
 * @phpstan-type FAQSchemaQuestion array{
 *     '@type':string,
 *     name:string,
 *     acceptedAnswer:array{
 *         '@type':string,
 *         text:string
 *     }
 * }
 */
final class FAQ_Schema_Generator {

	/**
	 * Generate FAQPage schema data.
	 *
	 * Empty or incomplete FAQ items are ignored.
	 *
	 * @param array<int,FAQItem> $faq_items Parsed FAQ items.
	 * @return array{
	 *     '@context':string,
	 *     '@type':string,
	 *     mainEntity:array<int,FAQSchemaQuestion>
	 * }|array{}
	 */
	public function generate(
		array $faq_items
	): array {

		$main_entity = array();

		foreach ( $faq_items as $faq_item ) {

			$question = trim(
				$faq_item['question']
			);

			$answer = trim(
				$faq_item['answer']
			);

			if (
				'' === $question ||
				'' === $answer
			) {
				continue;
			}

			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $main_entity ) ) {
			return array();
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $main_entity,
		);
	}
}
