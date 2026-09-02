<?php
/**
 * Mesh product table renderer.
 *
 * Renders a customer-facing HTML table of recognized mesh variations.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Renderers;

use Shurloc\SiteTools\Product\DTO\Mesh_Table_Data;

/**
 * Mesh product table renderer.
 */
final class Mesh_Product_Table_Renderer implements Mesh_Product_Table_Renderer_Interface {

	/**
	 * Render a mesh specification table.
	 *
	 * Returns fully escaped HTML suitable for direct output.
	 *
	 * @param Mesh_Table_Data $data Presentation-ready table data.
	 * @return string HTML table.
	 */
	public function render(
		Mesh_Table_Data $data
	): string {

		if ( ! $data->has_rows() ) {
			return '';
		}

		$rows = $data->get_rows();

		$has_modifier  = false;
		$has_pack_size = false;

		foreach ( $rows as $row ) {

			if ( null !== $row->get_modifier() ) {
				$has_modifier = true;
			}

			if ( null !== $row->get_pack_size() ) {
				$has_pack_size = true;
			}
		}

		$html  = '<div class="shurloc-mesh-table-wrapper">';
		$html .= '<table class="shurloc-mesh-specification-table">';

		$html .= '<caption>';
		$html .= esc_html(
			'Available Mesh Specifications'
		);
		$html .= '</caption>';

		$html .= '<thead>';
		$html .= '<tr>';

		$html .= '<th scope="col" class="shurloc-mesh-table-mesh">';
		$html .= esc_html(
			'Mesh'
		);
		$html .= '</th>';

		$html .= '<th scope="col" class="shurloc-mesh-table-thread">';
		$html .= esc_html(
			'Thread'
		);
		$html .= '</th>';

		if ( $has_modifier ) {

			$html .= '<th scope="col" class="shurloc-mesh-table-modifier">';
			$html .= esc_html(
				'Type'
			);
			$html .= '</th>';
		}

		$html .= '<th scope="col" class="shurloc-mesh-table-color">';
		$html .= esc_html(
			'Color'
		);
		$html .= '</th>';

		if ( $has_pack_size ) {

			$html .= '<th scope="col" class="shurloc-mesh-table-pack-size">';
			$html .= esc_html(
				'Pack Size'
			);
			$html .= '</th>';
		}

		$html .= '<th scope="col" class="shurloc-mesh-table-price">';
		$html .= esc_html(
			'Price'
		);
		$html .= '</th>';

		$html .= '</tr>';
		$html .= '</thead>';

		$html .= '<tbody>';

		foreach ( $rows as $row ) {

			$html .= '<tr';
			$html .= ' class="shurloc-mesh-table-row"';
			$html .= ' data-variation-value="';
			$html .= esc_attr(
				$row->get_variation_value()
			);
			$html .= '"';
			$html .= ' tabindex="0"';
			$html .= ' role="button"';
			$html .= '>';

			$html .= '<td data-label="Mesh" class="shurloc-mesh-table-mesh">';
			$html .= esc_html(
				(string) $row->get_mesh_count()
			);
			$html .= '</td>';

			$html .= '<td data-label="Thread" class="shurloc-mesh-table-thread">';
			$html .= esc_html(
				(string) $row->get_thread_diameter()
			);
			$html .= '</td>';

			if ( $has_modifier ) {

				$html .= '<td data-label="Type" class="shurloc-mesh-table-modifier">';

				if ( null !== $row->get_modifier() ) {

					$html .= esc_html(
						$row->get_modifier()
					);
				}

				$html .= '</td>';
			}

			$html .= '<td data-label="Color" class="shurloc-mesh-table-color">';
			$html .= esc_html(
				$row->get_color() ?? '—'
			);
			$html .= '</td>';

			if ( $has_pack_size ) {

				$html .= '<td data-label="Pack Size" class="shurloc-mesh-table-pack-size">';

				if ( null !== $row->get_pack_size() ) {

					$html .= esc_html(
						$row->get_pack_size()
					);
				}

				$html .= '</td>';
			}

			$html .= '<td data-label="Price" class="shurloc-mesh-table-price">';

			if ( null !== $row->get_price() ) {

				$html .= esc_html(
					sprintf(
						'$%.2f',
						$row->get_price()
					)
				);
			}

			$html .= '</td>';

			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '</div>';

		return $html;
	}
}
