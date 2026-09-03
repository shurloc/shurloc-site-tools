<?php
/**
 * Tests for the catalog variation entry model.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Catalog variation entry tests.
 */
final class CatalogVariationEntryTest extends TestCase {

	/**
	 * Verify constructor values are available as public properties.
	 *
	 * @return void
	 */
	public function test_constructor_assigns_properties(): void {

		$entry = $this->create_entry();

		self::assertSame( '110/80 White', $entry->variation );
		self::assertSame( 20.50, $entry->price );
		self::assertSame( 123, $entry->product_id );
		self::assertSame( 'Mesh Product', $entry->product_name );
		self::assertSame( 'https://example.com/edit/123', $entry->edit_url );
	}

	/**
	 * Verify identical entries compare as equal.
	 *
	 * @return void
	 */
	public function test_equals_returns_true_for_identical_entries(): void {

		self::assertTrue(
			$this->create_entry()->equals(
				$this->create_entry()
			)
		);
	}

	/**
	 * Verify entries with different values do not compare as equal.
	 *
	 * @param string     $variation    Variation name.
	 * @param float|null $price        Variation price.
	 * @param int        $product_id   Product ID.
	 * @param string     $product_name Product name.
	 * @param string     $edit_url     Product edit URL.
	 * @return void
	 */
	#[DataProvider( 'different_entry_provider' )]
	public function test_equals_returns_false_when_a_value_differs(
		string $variation,
		?float $price,
		int $product_id,
		string $product_name,
		string $edit_url
	): void {

		$other = new Catalog_Variation_Entry(
			variation: $variation,
			price: $price,
			product_id: $product_id,
			product_name: $product_name,
			edit_url: $edit_url,
		);

		self::assertFalse(
			$this->create_entry()->equals( $other )
		);
	}

	/**
	 * Provide entries with one differing value.
	 *
	 * @return array<string,array{string,float|null,int,string,string}>
	 */
	public static function different_entry_provider(): array {

		return array(
			'variation'    => array( '160/64 White', 20.50, 123, 'Mesh Product', 'https://example.com/edit/123' ),
			'price'        => array( '110/80 White', 25.00, 123, 'Mesh Product', 'https://example.com/edit/123' ),
			'product ID'   => array( '110/80 White', 20.50, 456, 'Mesh Product', 'https://example.com/edit/123' ),
			'product name' => array( '110/80 White', 20.50, 123, 'Other Product', 'https://example.com/edit/123' ),
			'edit URL'     => array( '110/80 White', 20.50, 123, 'Mesh Product', 'https://example.com/edit/456' ),
		);
	}

	/**
	 * Verify conversion to an associative array.
	 *
	 * @return void
	 */
	public function test_to_array_returns_all_values(): void {

		self::assertSame(
			array(
				'variation'    => '110/80 White',
				'price'        => 20.50,
				'product_id'   => 123,
				'product_name' => 'Mesh Product',
				'edit_url'     => 'https://example.com/edit/123',
			),
			$this->create_entry()->to_array()
		);
	}

	/**
	 * Verify a null price is preserved.
	 *
	 * @return void
	 */
	public function test_null_price_is_preserved(): void {

		$entry = $this->create_entry(
			price: null,
		);

		self::assertNull( $entry->price );
		self::assertNull( $entry->to_array()['price'] );
	}

	/**
	 * Create a catalog variation entry fixture.
	 *
	 * @param float|null $price Variation price.
	 * @return Catalog_Variation_Entry
	 */
	private function create_entry(
		?float $price = 20.50
	): Catalog_Variation_Entry {

		return new Catalog_Variation_Entry(
			variation: '110/80 White',
			price: $price,
			product_id: 123,
			product_name: 'Mesh Product',
			edit_url: 'https://example.com/edit/123',
		);
	}
}
