<?php
/**
 * Mesh specification parser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Parsers;

use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Parses mesh specification strings.
 */
class Mesh_Parser {

	/**
	 * Parse a mesh specification.
	 *
	 * @param string $text Raw variation text.
	 * @return Mesh_Specification Parsed mesh specification.
	 */
	public function parse(
		string $text
	): Mesh_Specification {

		$recognized      = false;
		$mesh_count      = null;
		$thread_diameter = null;
		$modifier        = null;
		$color           = null;
		$pack_size       = null;
		$price_text      = null;
		$unknown_tokens  = array();

		$remaining = $this->normalize( $text );

		// Recognize a mesh variation.
		if ( preg_match(
			'/\d+\/\d+/',
			$remaining
		) ) {
			$recognized = true;
		}

		$price_text = $this->extract_price(
			$remaining
		);

		$pack_size = $this->extract_pack_size(
			$remaining
		);

		$tokens = preg_split(
			'/\s+/',
			$remaining
		);

		if ( false !== $tokens ) {

			foreach ( $tokens as $token ) {

				$this->classify_token(
					$token,
					$mesh_count,
					$thread_diameter,
					$modifier,
					$color,
					$unknown_tokens
				);
			}
		}

		return new Mesh_Specification(
			$text,
			$mesh_count,
			$thread_diameter,
			$modifier,
			$color,
			$pack_size,
			$price_text,
			$recognized,
			$unknown_tokens
		);
	}


	/**
	 * Normalize a mesh specification string.
	 *
	 * @param string $text Raw variation text.
	 * @return string Normalized text.
	 */
	private function normalize(
		string $text
	): string {

		$text = trim( $text );

		$text = preg_replace(
			'/\s+/',
			' ',
			$text
		);

		if ( is_null( $text ) ) {
			return '';
		}

		$text = preg_replace(
			'/Thin\s+Thread/i',
			'(S)',
			$text
		);

		if ( is_null( $text ) ) {
			return '';
		}

		$text = preg_replace(
			'/\s*\/\s*/i',
			'/',
			$text
		);

		if ( is_null( $text ) ) {
			return '';
		}

		return $text;
	}


	/**
	 * Extract price from specification string.
	 *
	 * @param string $remaining Specification string.
	 * @return string|null Price token.
	 */
	private function extract_price(
		string &$remaining
	): ?string {

		if ( preg_match(
			'/(\(?\$\d+\.\d{2}\)?)$/',
			$remaining,
			$matches
		) ) {

			$remaining = trim(
				substr(
					$remaining,
					0,
					-strlen( $matches[1] )
				)
			);

			return $matches[1];
		}

		return null;
	}


	/**
	 * Extract pack size from specification string.
	 *
	 * @param string $remaining Specification string.
	 * @return string|null Pack size.
	 */
	private function extract_pack_size(
		string &$remaining
	): ?string {

		if ( preg_match(
			'/^(\d+\s+Pack)(\s*\-\s*)/i',
			$remaining,
			$matches
		) ) {

			$remaining = trim(
				substr(
					$remaining,
					strlen( $matches[1] ) + strlen( $matches[2] )
				)
			);

			return $matches[1];
		}

		return null;
	}


	/**
	 * Classify a token from the specification string.
	 *
	 * @param string      $token             Token to classify.
	 * @param int|null    &$mesh_count       Mesh count.
	 * @param int|null    &$thread_diameter  Thread diameter.
	 * @param string|null &$modifier      Modifier.
	 * @param string|null &$color         Color.
	 * @param string[]    &$unknown_tokens  Unknown tokens.
	 * @return void
	 */
	private function classify_token(
		string $token,
		?int &$mesh_count,
		?int &$thread_diameter,
		?string &$modifier,
		?string &$color,
		array &$unknown_tokens
	): void {

		if ( preg_match(
			'/^(\d+)\/(\d+)$/',
			$token,
			$matches
		) ) {

			$mesh_count      = (int) $matches[1];
			$thread_diameter = (int) $matches[2];

			return;
		}

		if (
			'white' === strtolower( $token ) ||
			'yellow' === strtolower( $token )
		) {

			$color = ucfirst( strtolower( $token ) );

			return;
		}

		if ( preg_match(
			'/^\s*\(?(S|M|HD)\)?\s*$/i',
			$token,
			$matches
		) ) {

			$modifier = strtoupper( $matches[1] );

			return;
		}

		$unknown_tokens[] = $token;
	}
}
