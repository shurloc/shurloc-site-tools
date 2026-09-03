<?php
/**
 * Tests for the catalog product entry model.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

use PHPUnit\Framework\TestCase;

/**
 * Catalog product entry tests.
 */
final class CatalogProductEntryTest extends TestCase {

	/**
	 * Verify constructor values are available as public properties.
	 *
	 * @return void
	 */
	public function test_constructor_assigns_properties(): void {

		$variation = new Catalog_Variation_Entry(
			variation: '110/80 White',
			price: 20.50,
			product_id: 123,
			product_name: 'Mesh Product',
			edit_url: 'https://example.com/edit/123',
		);

		$aggregate_rating = array(
			'ratingValue' => 4.8,
			'reviewCount' => 12,
		);

		$reviews = array(
			array(
				'author' => 'Customer',
			),
		);

		$entry = new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Mesh Product',
			edit_url: 'https://example.com/edit/123',
			product_url: 'https://example.com/product/mesh-product',
			sku: 'MESH-123',
			image_url: 'https://example.com/image.jpg',
			short_description: 'Short description.',
			description: 'Full description.',
			category: 'Mesh',
			price: 20.50,
			regular_price: 25.00,
			sale_price: 20.50,
			availability: 'InStock',
			brand: 'Shur-Loc',
			manufacturer: 'Shur-Loc Fabric System',
			aggregate_rating: $aggregate_rating,
			reviews: $reviews,
			variations: array( $variation ),
		);

		self::assertSame( 123, $entry->product_id );
		self::assertSame( 'Mesh Product', $entry->product_name );
		self::assertSame( 'https://example.com/edit/123', $entry->edit_url );
		self::assertSame( 'https://example.com/product/mesh-product', $entry->product_url );
		self::assertSame( 'MESH-123', $entry->sku );
		self::assertSame( 'https://example.com/image.jpg', $entry->image_url );
		self::assertSame( 'Short description.', $entry->short_description );
		self::assertSame( 'Full description.', $entry->description );
		self::assertSame( 'Mesh', $entry->category );
		self::assertSame( 20.50, $entry->price );
		self::assertSame( 25.00, $entry->regular_price );
		self::assertSame( 20.50, $entry->sale_price );
		self::assertSame( 'InStock', $entry->availability );
		self::assertSame( 'Shur-Loc', $entry->brand );
		self::assertSame( 'Shur-Loc Fabric System', $entry->manufacturer );
		self::assertSame( $aggregate_rating, $entry->aggregate_rating );
		self::assertSame( $reviews, $entry->reviews );
		self::assertSame( array( $variation ), $entry->variations );
	}

	/**
	 * Verify nullable values and empty collections are preserved.
	 *
	 * @return void
	 */
	public function test_constructor_preserves_nullable_values_and_empty_collections(): void {

		$entry = new Catalog_Product_Entry(
			product_id: 123,
			product_name: 'Mesh Product',
			edit_url: 'https://example.com/edit/123',
			product_url: 'https://example.com/product/mesh-product',
			sku: 'MESH-123',
			image_url: null,
			short_description: '',
			description: '',
			category: null,
			price: null,
			regular_price: null,
			sale_price: null,
			availability: 'OutOfStock',
			brand: null,
			manufacturer: '',
			aggregate_rating: null,
			reviews: array(),
			variations: array(),
		);

		self::assertNull( $entry->image_url );
		self::assertNull( $entry->category );
		self::assertNull( $entry->price );
		self::assertNull( $entry->regular_price );
		self::assertNull( $entry->sale_price );
		self::assertNull( $entry->brand );
		self::assertNull( $entry->aggregate_rating );
		self::assertSame( array(), $entry->reviews );
		self::assertSame( array(), $entry->variations );
	}
}
