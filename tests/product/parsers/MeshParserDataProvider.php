<?php
/**
 * Test data for the mesh parser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Parsers;

use Shurloc\SiteTools\Product\Models\Mesh_Specification;

/**
 * Mesh parser test cases.
 */
final class MeshParserDataProvider {

	/**
	 * Standard mesh specifications.
	 *
	 * @return array<string, array{0:string,1:Mesh_Specification}>
	 */
	public static function standard_mesh(): array {

		return array(
			'110/80 Yellow'             => array(
				'110/80 Yellow $23.75',
				self::spec(
					raw: '110/80 Yellow $23.75',
					mesh_count: 110,
					thread_diameter: 80,
					color: 'Yellow',
					price_text: '$23.75',
					recognized: true,
				),
			),

			'60/120 White'              => array(
				'60/120 White $22.36',
				self::spec(
					raw: '60/120 White $22.36',
					mesh_count: 60,
					thread_diameter: 120,
					color: 'White',
					price_text: '$22.36',
					recognized: true,
				),
			),

			'110/80 Orange'             => array(
				'110/180 Orange $23.75',
				self::spec(
					raw: '110/180 Orange $23.75',
					mesh_count: 110,
					thread_diameter: 180,
					price_text: '$23.75',
					unknown_tokens: array( 'Orange' ),
					recognized: true,
				),
			),

			'110/71 (S) White'          => array(
				'110/71 (S) White $23.75',
				self::spec(
					raw: '110/71 (S) White $23.75',
					mesh_count: 110,
					thread_diameter: 71,
					modifier: 'S',
					color: 'White',
					price_text: '$23.75',
					recognized: true,
				),
			),

			'110/71 White (s)'          => array(
				'110/71 White (s) $23.75',
				self::spec(
					raw: '110/71 White (s) $23.75',
					mesh_count: 110,
					thread_diameter: 71,
					modifier: 'S',
					color: 'White',
					price_text: '$23.75',
					recognized: true,
				),
			),

			'110/71 White HD'           => array(
				'110/71 White HD $23.75',
				self::spec(
					raw: '110/71 White HD $23.75',
					mesh_count: 110,
					thread_diameter: 71,
					modifier: 'HD',
					color: 'White',
					price_text: '$23.75',
					recognized: true,
				),
			),

			'5 Pack - 110/80 Yellow'    => array(
				'5 Pack - 110/80 Yellow ($98.55)',
				self::spec(
					raw: '5 Pack - 110/80 Yellow ($98.55)',
					mesh_count: 110,
					thread_diameter: 80,
					color: 'Yellow',
					pack_size: '5 Pack',
					price_text: '($98.55)',
					recognized: true,
				),
			),

			'5 Pack - 110/80 yellow'    => array(
				'5 Pack - 110/80 yellow ($98.55)',
				self::spec(
					raw: '5 Pack - 110/80 yellow ($98.55)',
					mesh_count: 110,
					thread_diameter: 80,
					color: 'Yellow',
					pack_size: '5 Pack',
					price_text: '($98.55)',
					recognized: true,
				),
			),

			'230/40 Thin Thread Yellow' => array(
				'230/40 Thin Thread Yellow $32.21',
				self::spec(
					raw: '230/40 Thin Thread Yellow $32.21',
					mesh_count: 230,
					thread_diameter: 40,
					modifier: 'S',
					color: 'Yellow',
					price_text: '$32.21',
					recognized: true,
				),
			),

			'110/ 71 (S) White'         => array(
				'110/ 71 (S) White $23.75',
				self::spec(
					raw: '110/ 71 (S) White $23.75',
					mesh_count: 110,
					thread_diameter: 71,
					modifier: 'S',
					color: 'White',
					price_text: '$23.75',
					recognized: true,
				),
			),
		);
	}

	/**
	 * Recognized mesh specifications.
	 *
	 * @return array<string, array{string}>
	 */
	public static function recognized_mesh(): array {

		return array(
			'recognized' => array(
				'110/80 Yellow $23.75',
			),
		);
	}

	/**
	 * Unrecognized variation specifications.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unrecognized_variations(): array {

		return array(
			'separator' => array(
				'-----',
			),

			'gallon'    => array(
				'1 Gallon ($92.00)',
			),
		);
	}

	/**
	 * Create a specification value object.
	 *
	 * @param string      $raw             Raw variation string.
	 * @param int|null    $mesh_count      Mesh count.
	 * @param int|null    $thread_diameter Thread diameter.
	 * @param string|null $modifier        Modifier.
	 * @param string|null $color           Mesh color.
	 * @param string|null $pack_size       Pack size.
	 * @param string|null $price_text      Price token.
	 * @param bool        $recognized      Whether specification is recognized.
	 * @param string[]    $unknown_tokens  Unknown tokens.
	 * @return Mesh_Specification
	 */
	private static function spec(
		string $raw,
		?int $mesh_count = null,
		?int $thread_diameter = null,
		?string $modifier = null,
		?string $color = null,
		?string $pack_size = null,
		?string $price_text = null,
		bool $recognized = false,
		array $unknown_tokens = array(),
	): Mesh_Specification {

		return new Mesh_Specification(
			$raw,
			$mesh_count,
			$thread_diameter,
			$modifier,
			$color,
			$pack_size,
			$price_text,
			$recognized,
			$unknown_tokens,
		);
	}

	/**
	 * Price extraction test cases.
	 *
	 * @return array<array{string,string}>
	 */
	public static function prices(): array {

		return array(
			array(
				'110/80 Yellow $23.75',
				'$23.75',
			),
			array(
				'160/64 White $100.00',
				'$100.00',
			),
			array(
				'200/55 Black $5.99',
				'$5.99',
			),
		);
	}

	/**
	 * Color extraction test cases.
	 *
	 * @return array<array{string,string}>
	 */
	public static function colors(): array {

		return array(
			array(
				'110/80 Yellow $20.00',
				'Yellow',
			),
			array(
				'160/64 White $25.00',
				'White',
			),
		);
	}

	/**
	 * Suffix variation test cases.
	 *
	 * @return array<array{string}>
	 */
	public static function suffix_variations(): array {

		return array(
			array( '110/80 Yellow (HD) $20.00' ),
			array( '110/80 Yellow (S) $20.00' ),
			array( '110/80 Yellow (s) $20.00' ),
		);
	}
}
