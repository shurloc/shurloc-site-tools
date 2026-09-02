<?php
/**
 * Catalog report admin controller.
 *
 * Provides admin tools for exporting and analyzing the WooCommerce catalog.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Models\Catalog_Variation_Entry;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Services\Catalog_Analysis_Service;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service;

/**
 * Catalog report admin controller.
 */
final class Catalog_Report_Controller implements
	Catalog_Report_Actions_Interface {

	/**
	 * Product catalog service.
	 *
	 * @var Product_Catalog_Service
	 */
	private Product_Catalog_Service $catalog_service;

	/**
	 * Catalog analysis service.
	 *
	 * @var Catalog_Analysis_Service
	 */
	private Catalog_Analysis_Service $analysis_service;

	/**
	 * Request handler.
	 *
	 * @var Catalog_Report_Request_Handler
	 */
	private Catalog_Report_Request_Handler $request_handler;

	/**
	 * Constructor.
	 *
	 * @param Product_Catalog_Service  $catalog_service  Product catalog service.
	 * @param Catalog_Analysis_Service $analysis_service Catalog analysis service.
	 */
	public function __construct(
		Product_Catalog_Service $catalog_service,
		Catalog_Analysis_Service $analysis_service
	) {

		$this->catalog_service  = $catalog_service;
		$this->analysis_service = $analysis_service;

		$this->request_handler = new Catalog_Report_Request_Handler(
			actions: $this,
		);
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_init',
			array(
				$this->request_handler,
				'handle_request',
			)
		);
	}

	/**
	 * Render the catalog report tab.
	 *
	 * @return void
	 */
	public function render_catalog_report(): void {
		?>

		<h2>Export Catalog Variations</h2>

		<p>
			Export WooCommerce variation names as a JSON file for parser
			development and testing.
		</p>

		<form method="post">

			<?php wp_nonce_field( 'shurloc_export_variations' ); ?>

			<input
				type="hidden"
				name="shurloc_action"
				value="export_variations"
			/>

			<?php submit_button( 'Export Variations', 'primary', 'submit', false ); ?>

		</form>

		<hr>

		<h2>Generate Catalog Report</h2>

		<p>
			Analyze the WooCommerce catalog using the mesh parser and download
			a JSON report containing recognized, unrecognized, and invalid
			specifications.
		</p>

		<form method="post">

			<?php wp_nonce_field( 'shurloc_generate_catalog_report' ); ?>

			<input
				type="hidden"
				name="shurloc_action"
				value="generate_catalog_report"
			/>

			<?php submit_button( 'Generate Catalog Report', 'secondary', 'submit', false ); ?>

		</form>

		<?php
	}


	/**
	 * Render the invalid mesh products tab.
	 *
	 * @return void
	 */
	public function render_invalid_mesh_products(): void {

		$result = $this->analysis_service->analyze();

		$invalid_specifications = $result->get_invalid_specifications();

		usort(
			$invalid_specifications,
			static function ( array $left, array $right ): int {

				$product_comparison = $left['product_id'] <=> $right['product_id'];

				if ( 0 !== $product_comparison ) {
					return $product_comparison;
				}

				return strnatcasecmp(
					$left['variation'],
					$right['variation']
				);
			}
		);
		?>

	<h2>Invalid Mesh Products</h2>

	<p>
		Review purchasable product variations that were recognized as mesh
		specifications but did not parse into valid specifications.
	</p>

		<?php if ( empty( $invalid_specifications ) ) : ?>

		<div class="notice notice-success inline">

			<p>
				No invalid mesh product variations were found.
			</p>

		</div>

	<?php else : ?>

		<div class="notice notice-warning inline">

			<p>
				<?php
				$invalid_count = count( $invalid_specifications );

				echo esc_html(
					sprintf(
						/* translators: %d: Number of invalid paid product variations. */
						_n(
							'%d invalid paid variation was found.',
							'%d invalid paid variations were found.',
							$invalid_count,
							'shurloc-site-tools'
						),
						$invalid_count
					)
				);
				?>
			</p>

		</div>

		<table class="widefat fixed striped">

			<thead>

				<tr>
					<th scope="col">Product</th>
					<th scope="col">Variation</th>
					<th scope="col">Invalid Because</th>
					<th scope="col">Action</th>
				</tr>

			</thead>

			<tbody>

				<?php foreach ( $invalid_specifications as $entry ) : ?>

					<tr>

						<td>
							<strong>
								<?php echo esc_html( $entry['product_name'] ); ?>
							</strong>

							<br>

							<span>
								Product ID:
								<?php echo esc_html( (string) $entry['product_id'] ); ?>
							</span>
						</td>

						<td>
							<code>
								<?php echo esc_html( $entry['variation'] ); ?>
							</code>
						</td>

						<td>
							<?php
							$validation_errors = $entry['spec']->get_validation_errors();
							?>

							<?php if ( ! empty( $validation_errors ) ) : ?>

								<ul style="margin: 0;">

									<?php foreach ( $validation_errors as $validation_error ) : ?>

										<li>
											<?php echo esc_html( $validation_error ); ?>
										</li>

									<?php endforeach; ?>

								</ul>

							<?php else : ?>

								&mdash;

							<?php endif; ?>
						</td>

						<td>

							<?php if ( ! empty( $entry['edit_url'] ) ) : ?>

								<a
									href="<?php echo esc_url( $entry['edit_url'] ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									class="button button-secondary"
								>
									Edit Product
								</a>

							<?php else : ?>

								&mdash;

							<?php endif; ?>

						</td>

					</tr>

				<?php endforeach; ?>

			</tbody>

		</table>

	<?php endif; ?>

		<?php
	}

	/**
	 * Render the unrecognized mesh products tab.
	 *
	 * @return void
	 */
	public function render_unrecognized_mesh_products(): void {

		$result = $this->analysis_service->analyze();

		$unrecognized_variations = $result->get_unrecognized_variations();

		usort(
			$unrecognized_variations,
			static function ( array $left, array $right ): int {

				$product_comparison =
					$left['product_id'] <=> $right['product_id'];

				if ( 0 !== $product_comparison ) {
					return $product_comparison;
				}

				return strnatcasecmp(
					$left['variation'],
					$right['variation']
				);
			}
		);
		?>

	<h2>Unrecognized Mesh Products</h2>

	<p>
		Review purchasable product variations that were not recognized as
		mesh specifications.
	</p>

		<?php if ( empty( $unrecognized_variations ) ) : ?>

		<div class="notice notice-success inline">

			<p>
				No unrecognized mesh product variations were found.
			</p>

		</div>

	<?php else : ?>

		<div class="notice notice-warning inline">

			<p>
				<?php
				$unrecognized_count = count(
					$unrecognized_variations
				);

				echo esc_html(
					sprintf(
						/* translators: %d: Number of unrecognized paid product variations. */
						_n(
							'%d unrecognized paid variation was found.',
							'%d unrecognized paid variations were found.',
							$unrecognized_count,
							'shurloc-site-tools'
						),
						$unrecognized_count
					)
				);
				?>
			</p>

		</div>

		<table class="widefat fixed striped">

			<thead>

				<tr>
					<th scope="col">Product</th>
					<th scope="col">Variation</th>
					<th scope="col">Price</th>
					<th scope="col">Action</th>
				</tr>

			</thead>

			<tbody>

				<?php foreach ( $unrecognized_variations as $entry ) : ?>

					<tr>

						<td>
							<strong>
								<?php echo esc_html( $entry['product_name'] ); ?>
							</strong>

							<br>

							<span>
								Product ID:
								<?php echo esc_html( (string) $entry['product_id'] ); ?>
							</span>
						</td>

						<td>
							<code>
								<?php echo esc_html( $entry['variation'] ); ?>
							</code>
						</td>

						<td>
							<?php if ( null !== $entry['price'] ) : ?>

								<?php
								echo wp_kses_post(
									wc_price( $entry['price'] )
								);
								?>

							<?php else : ?>

								&mdash;

							<?php endif; ?>
						</td>

						<td>

							<?php if ( ! empty( $entry['edit_url'] ) ) : ?>

								<a
									href="<?php echo esc_url( $entry['edit_url'] ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									class="button button-secondary"
								>
									Edit Product
								</a>

							<?php else : ?>

								&mdash;

							<?php endif; ?>

						</td>

					</tr>

				<?php endforeach; ?>

			</tbody>

		</table>

	<?php endif; ?>

		<?php
	}

	/**
	 * Export WooCommerce catalog variations.
	 *
	 * @return void
	 */
	public function export_variations(): void {

		$this->verify_permissions();

		$this->download_json(
			filename: 'shurloc-variations.json',
			data: $this->get_catalog_variations(),
		);
	}

	/**
	 * Generate a catalog analysis report.
	 *
	 * @return void
	 */
	public function generate_catalog_report(): void {

		$this->verify_permissions();

		$parser = new Mesh_Parser();

		$analyzer = new Catalog_Analyzer(
			mesh_parser: $parser,
		);

		$report = $analyzer->analyze(
			entries: $this->get_catalog_entries(),
		);

		$this->download_json(
			filename: 'catalog-report.json',
			data: $report->to_array(),
		);
	}

	/**
	 * Collect all WooCommerce variation names.
	 *
	 * @return string[]
	 */
	private function get_catalog_variations(): array {

		$variations = array();

		foreach ( $this->get_catalog_entries() as $entry ) {

			$variations[] = $entry->variation;
		}

		return $variations;
	}

	/**
	 * Collect catalog variation entries from WooCommerce.
	 *
	 * @return Catalog_Variation_Entry[]
	 */
	private function get_catalog_entries(): array {

		$entries = array();

		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			),
		);

		foreach ( $product_ids as $product_id ) {

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$entries = array_merge(
				$entries,
				$this->catalog_service->get_product_variation_entries(
					product: $product,
				),
			);
		}

		usort(
			$entries,
			static function (
				Catalog_Variation_Entry $left,
				Catalog_Variation_Entry $right
			): int {

				return strnatcasecmp(
					$left->variation,
					$right->variation
				);
			}
		);

		return $entries;
	}

	/**
	 * Verify administrator permissions.
	 *
	 * @return void
	 */
	private function verify_permissions(): void {

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_die(
				esc_html__(
					'You do not have permission to perform this action.',
					'shurloc-site-tools'
				)
			);
		}
	}

	/**
	 * Download data as JSON.
	 *
	 * @param string              $filename Download filename.
	 * @param array<string,mixed> $data     Data to encode as JSON.
	 * @return void
	 */
	private function download_json(
		string $filename,
		array $data
	): void {

		header( 'Content-Type: application/json; charset=utf-8' );
		header(
			sprintf(
				'Content-Disposition: attachment; filename="%s"',
				$filename
			)
		);
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode(
			$data,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		exit;
	}
}
