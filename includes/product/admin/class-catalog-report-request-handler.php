<?php
/**
 * Catalog report request handler.
 *
 * Handles admin form submissions for catalog tools.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product\Admin;

/**
 * Catalog report request handler.
 */
final class Catalog_Report_Request_Handler {

	/**
	 * Catalog report actions.
	 *
	 * @var Catalog_Report_Actions_Interface
	 */
	private Catalog_Report_Actions_Interface $actions;

	/**
	 * Constructor.
	 *
	 * @param Catalog_Report_Actions_Interface $actions Catalog report controller.
	 */
	public function __construct(
		Catalog_Report_Actions_Interface $actions
	) {

		$this->actions = $actions;
	}

	/**
	 * Handle admin requests.
	 *
	 * @return void
	 */
	public function handle_request(): void {

		if ( ! isset( $_POST['shurloc_action'] ) ) {
			return;
		}

		$action = sanitize_key(
			wp_unslash(
				$_POST['shurloc_action']
			)
		);

		switch ( $action ) {

			case 'export_variations':
				check_admin_referer(
					'shurloc_export_variations'
				);

				$this->actions->export_variations();

				break;

			case 'generate_catalog_report':
				check_admin_referer(
					'shurloc_generate_catalog_report'
				);

				$this->actions->generate_catalog_report();

				break;
		}
	}
}
