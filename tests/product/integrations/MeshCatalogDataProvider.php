<?php
/**
 * Catalog fixture data provider.
 *
 * Loads variation names exported from WooCommerce and converts them into
 * catalog variation entries for analyzer tests.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Integrations;

use JsonException;
use RuntimeException;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;

/**
 * Catalog data provider.
 */
final class MeshCatalogDataProvider {

	/**
	 * Load catalog variation entries from the catalog fixture.
	 *
	 * Converts exported variation names into catalog variation entries for
	 * analyzer integration tests.
	 *
	 * @return Catalog_Variation_Entry[]
	 * @throws JsonException    If the JSON fixture is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public static function load_catalog(): array {

		$filename = dirname( __DIR__ ) . '/data/catalog-variations.json';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture.
		$json = file_get_contents( $filename );

		if ( false === $json ) {
			throw new RuntimeException(
				'Unable to read catalog fixture.'
			);
		}

		$variations = json_decode(
			$json,
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		if ( ! is_array( $variations ) ) {
			throw new RuntimeException(
				'Catalog fixture does not contain an array.'
			);
		}

		$entries = array();

		foreach ( $variations as $variation ) {

			if ( ! is_string( $variation ) ) {
				continue;
			}

			$entries[] = new Catalog_Variation_Entry(
				$variation,
				null,
				0,
				'Fixture Product',
				''
			);
		}

		return $entries;
	}

	/**
	 * Return catalog variations as PHPUnit datasets.
	 *
	 * @return array<string, array{0:Catalog_Variation_Entry}>
	 * @throws JsonException    If the JSON fixture is invalid.
	 * @throws RuntimeException If the fixture cannot be read.
	 */
	public static function catalog_variations(): array {

		$data = array();

		foreach ( self::load_catalog() as $entry ) {
			$data[ $entry->variation ] = array( $entry );
		}

		return $data;
	}
}
