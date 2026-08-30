<?php
/**
 * FAQ schema parser.
 *
 * Parses rendered FAQ page HTML into normalized FAQ question and answer data.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Parsers;

defined( 'ABSPATH' ) || exit;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Parses FAQ content into structured question and answer data.
 *
 * @phpstan-type FAQItem array{
 *     question:string,
 *     answer:string
 * }
 */
final class FAQ_Schema_Parser {

	/**
	 * Minimum accepted answer length.
	 *
	 * @var int
	 */
	private const MINIMUM_ANSWER_LENGTH = 25;

	/**
	 * Parse rendered FAQ HTML.
	 *
	 * Only H3 elements whose text ends with a question mark are treated as
	 * potential FAQ questions. The answer consists of following element
	 * siblings until another heading element is encountered.
	 *
	 * @param string $content Rendered page content.
	 * @return array<int,FAQItem>
	 */
	public function parse(
		string $content
	): array {

		if ( '' === trim( $content ) ) {
			return array();
		}

		$previous_libxml_setting =
			libxml_use_internal_errors( true );

		$dom = new DOMDocument();

		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' .
				'<div id="shurloc-faq-content">' .
				$content .
				'</div>',
			LIBXML_HTML_NOIMPLIED |
			LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();

		libxml_use_internal_errors(
			$previous_libxml_setting
		);

		if ( ! $loaded ) {
			return array();
		}

		$xpath = new DOMXPath( $dom );

		$headings = $xpath->query( '//h3' );

		if (
			false === $headings ||
			0 === $headings->length
		) {
			return array();
		}

		$faq_items = array();

		foreach ( $headings as $heading ) {

			if ( ! $heading instanceof DOMElement ) {
				continue;
			}

			$question = $this->normalize_text(
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
				text: $heading->textContent,
			);

			if ( ! $this->is_question( question: $question ) ) {
				continue;
			}

			$answer = $this->get_answer(
				heading: $heading,
				dom: $dom,
			);

			if (
				self::MINIMUM_ANSWER_LENGTH >
				strlen( $answer )
			) {
				continue;
			}

			$faq_items[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $faq_items;
	}

	/**
	 * Determine whether heading text represents an FAQ question.
	 *
	 * @param string $question Heading text.
	 * @return bool
	 */
	private function is_question(
		string $question
	): bool {

		if ( '' === $question ) {
			return false;
		}

		return 1 === preg_match(
			'/\?\s*$/u',
			$question
		);
	}

	/**
	 * Get the answer content following an FAQ heading.
	 *
	 * Parsing stops at the next heading of any level. Standalone image and
	 * iframe elements are ignored.
	 *
	 * @param DOMElement  $heading FAQ heading.
	 * @param DOMDocument $dom     Parsed document.
	 * @return string
	 */
	private function get_answer(
		DOMElement $heading,
		DOMDocument $dom
	): string {

		$answer = '';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
		$node = $heading->nextSibling;

		while ( $node instanceof DOMNode ) {

			if ( $this->is_heading_node( node: $node ) ) {
				break;
			}

			if (
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
				XML_ELEMENT_NODE === $node->nodeType &&
				$node instanceof DOMElement
			) {

				if ( $this->should_skip_element( element: $node ) ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
					$node = $node->nextSibling;
					continue;
				}

				$html = $dom->saveHTML( $node );

				if ( false !== $html ) {

					$text = trim(
						wp_strip_all_tags( $html )
					);

					if ( '' !== $text ) {
						$answer .= ' ' . $text;
					}
				}
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
			$node = $node->nextSibling;
		}

		return $this->normalize_text(
			text: $answer,
		);
	}

	/**
	 * Determine whether a DOM node is a heading.
	 *
	 * @param DOMNode $node DOM node.
	 * @return bool
	 */
	private function is_heading_node(
		DOMNode $node
	): bool {

		if (
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
			XML_ELEMENT_NODE !== $node->nodeType ||
			! $node instanceof DOMElement
		) {
			return false;
		}

		return 1 === preg_match(
			'/^h[1-6]$/i',
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
			$node->nodeName
		);
	}

	/**
	 * Determine whether an element should be ignored when building an answer.
	 *
	 * @param DOMElement $element DOM element.
	 * @return bool
	 */
	private function should_skip_element(
		DOMElement $element
	): bool {

		return in_array(
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property name.
			strtolower( $element->nodeName ),
			array(
				'img',
				'iframe',
			),
			true
		);
	}

	/**
	 * Normalize whitespace in parsed text.
	 *
	 * @param string $text Text to normalize.
	 * @return string
	 */
	private function normalize_text(
		string $text
	): string {

		$text = str_replace(
			"\xc2\xa0",
			' ',
			$text
		);

		$normalized = preg_replace(
			'/\s+/u',
			' ',
			$text
		);

		if ( null === $normalized ) {
			return '';
		}

		return trim( $normalized );
	}
}
