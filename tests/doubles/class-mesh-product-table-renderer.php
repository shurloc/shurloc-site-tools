<?php
/**
 * Mesh product table renderer double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;

/**
 * Mesh product table renderer double.
 */
final class Mesh_Product_Table_Renderer_Double implements Mesh_Product_Table_Renderer_Interface {

	/**
	 * Rendered output.
	 *
	 * @var string
	 */
	private string $output;

	/**
	 * Render calls.
	 *
	 * @var Mesh_Table_Data[]
	 */
	private array $calls = array();

	/**
	 * Constructor.
	 *
	 * @param string $output Rendered output.
	 */
	public function __construct(
		string $output
	) {

		$this->output = $output;
	}

	/**
	 * Render mesh table.
	 *
	 * @param Mesh_Table_Data $data Table data.
	 * @return string Rendered HTML.
	 */
	public function render(
		Mesh_Table_Data $data
	): string {

		$this->calls[] = $data;

		return $this->output;
	}

	/**
	 * Get render calls.
	 *
	 * @return Mesh_Table_Data[]
	 */
	public function get_calls(): array {

		return $this->calls;
	}
}
