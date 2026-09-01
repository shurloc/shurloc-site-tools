<?php
/**
 * Mesh table row DTO.
 *
 * Represents a single presentation-ready row for the mesh product table.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\DTO;

/**
 * Mesh table row DTO.
 */
final class Mesh_Table_Row {

	/**
	 * Mesh count.
	 *
	 * @var int|null
	 */
	private ?int $mesh_count;

	/**
	 * Thread diameter.
	 *
	 * @var int|null
	 */
	private ?int $thread_diameter;

	/**
	 * Mesh color.
	 *
	 * @var string|null
	 */
	private ?string $color;

	/**
	 * Mesh modifier.
	 *
	 * @var string|null
	 */
	private ?string $modifier;

	/**
	 * Pack size.
	 *
	 * @var string|null
	 */
	private ?string $pack_size;

	/**
	 * Variation price.
	 *
	 * @var float|null
	 */
	private ?float $price;

	/**
	 * Variation selection value.
	 *
	 * This value matches the corresponding WooCommerce variation
	 * dropdown option value.
	 *
	 * @var string
	 */
	private string $variation_value;

	/**
	 * Constructor.
	 *
	 * @param int|null    $mesh_count      Mesh count.
	 * @param int|null    $thread_diameter Thread diameter.
	 * @param string|null $color           Mesh color.
	 * @param string|null $modifier        Mesh modifier.
	 * @param string|null $pack_size       Pack size.
	 * @param float|null  $price           Variation price.
	 * @param string      $variation_value Variation selection value.
	 */
	public function __construct(
		?int $mesh_count,
		?int $thread_diameter,
		?string $color,
		?string $modifier,
		?string $pack_size,
		?float $price,
		string $variation_value
	) {

		$this->mesh_count      = $mesh_count;
		$this->thread_diameter = $thread_diameter;
		$this->color           = $color;
		$this->modifier        = $modifier;
		$this->pack_size       = $pack_size;
		$this->price           = $price;
		$this->variation_value = $variation_value;
	}

	/**
	 * Get mesh count.
	 *
	 * @return int|null Mesh count.
	 */
	public function get_mesh_count(): int|null {

		return $this->mesh_count;
	}

	/**
	 * Get thread diameter.
	 *
	 * @return ?int Thread diameter.
	 */
	public function get_thread_diameter(): ?int {

		return $this->thread_diameter;
	}

	/**
	 * Get color.
	 *
	 * @return string Mesh color.
	 */
	public function get_color(): ?string {

		return $this->color;
	}

	/**
	 * Get modifier.
	 *
	 * @return string|null Mesh modifier.
	 */
	public function get_modifier(): ?string {

		return $this->modifier;
	}

	/**
	 * Get pack size.
	 *
	 * @return string|null Pack size.
	 */
	public function get_pack_size(): ?string {

		return $this->pack_size;
	}

	/**
	 * Get price.
	 *
	 * @return float|null Variation price.
	 */
	public function get_price(): ?float {

		return $this->price;
	}

	/**
	 * Get the variation selection value.
	 *
	 * @return string Variation selection value.
	 */
	public function get_variation_value(): string {

		return $this->variation_value;
	}
}
