<?php
/**
 * Product domain bootstrap.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Product;

use Shurloc\SiteTools\Product\Admin\Admin_Menu;
use Shurloc\SiteTools\Product\Admin\Admin_Page_Controller;
use Shurloc\SiteTools\Product\Admin\Catalog_Report_Controller;
use Shurloc\SiteTools\Product\Admin\Primary_Product_Category_Metabox;
use Shurloc\SiteTools\Product\Admin\Product_Migrations_Controller;
use Shurloc\SiteTools\Product\Analyzers\Catalog_Analyzer;
use Shurloc\SiteTools\Product\Analyzers\Mesh_Product_Analyzer;
use Shurloc\SiteTools\Product\Factories\Mesh_Table_Data_Factory;
use Shurloc\SiteTools\Product\Frontend\Breadcrumb_Schema;
use Shurloc\SiteTools\Product\Frontend\Breadcrumb_Separator;
use Shurloc\SiteTools\Product\Frontend\Dynamic_Cross_Sells;
use Shurloc\SiteTools\Product\Frontend\Product_Breadcrumbs;
use Shurloc\SiteTools\Product\Frontend\Related_Products;
use Shurloc\SiteTools\Product\Generators\Product_Schema_Generator;
use Shurloc\SiteTools\Product\Integrations\Mesh_Product_Table_Assets;
use Shurloc\SiteTools\Product\Integrations\Mesh_Product_Table_Tab;
use Shurloc\SiteTools\Product\Integrations\Order_Buyer_Company_Integration;
use Shurloc\SiteTools\Product\Integrations\Product_Schema_Integration;
use Shurloc\SiteTools\Product\Integrations\Product_Tag_Pagination_Integration;
use Shurloc\SiteTools\Product\Integrations\WooCommerce_Schema_Integration;
use Shurloc\SiteTools\Product\Integrations\Yoast_Primary_Category_Integration;
use Shurloc\SiteTools\Product\Migrations\Yoast_Product_Meta_Cleanup_Migration;
use Shurloc\SiteTools\Product\Parsers\Mesh_Parser;
use Shurloc\SiteTools\Product\Renderers\Mesh_Product_Table_Renderer;
use Shurloc\SiteTools\Product\Renderers\Product_Schema_Renderer;
use Shurloc\SiteTools\Product\Services\Catalog_Analysis_Service;
use Shurloc\SiteTools\Product\Services\Mesh_Product_Data_Service;
use Shurloc\SiteTools\Product\Services\Mesh_Product_Schema_Service;
use Shurloc\SiteTools\Product\Services\Primary_Product_Category_Service;
use Shurloc\SiteTools\Product\Services\Product_Catalog_Service;
use Shurloc\SiteTools\Product\Services\Product_Recommendation_Eligibility_Service;
use Shurloc\SiteTools\Product\Services\Product_Schema_Service;
use Shurloc\SiteTools\Product\Shortcodes\Mesh_Product_Table_Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the Product domain.
 */
final class Bootstrap {

	/**
	 * Register the Product domain.
	 *
	 * @return void
	 */
	public function register(): void {

		$mesh_parser = new Mesh_Parser();

		$catalog_service = new Product_Catalog_Service();

		$mesh_analyzer = new Mesh_Product_Analyzer(
			parser: $mesh_parser,
		);

		$mesh_data_service = new Mesh_Product_Data_Service(
			catalog_service: $catalog_service,
			mesh_analyzer: $mesh_analyzer,
		);

		$catalog_analyzer = new Catalog_Analyzer(
			mesh_parser: $mesh_parser,
		);

		$analysis_service = new Catalog_Analysis_Service(
			catalog_service: $catalog_service,
			catalog_analyzer: $catalog_analyzer,
		);

		$mesh_schema_service = new Mesh_Product_Schema_Service(
			analyzer: $mesh_analyzer,
		);

		$schema_generator = new Product_Schema_Generator();

		$product_schema_service = new Product_Schema_Service(
			generator: $schema_generator,
			mesh_schema_service: $mesh_schema_service,
		);

		$schema_renderer = new Product_Schema_Renderer();

		$product_schema_integration = new Product_Schema_Integration(
			catalog_service: $catalog_service,
			schema_service: $product_schema_service,
			renderer: $schema_renderer,
		);
		$product_schema_integration->register();

		$woocommerce_schema_integration = new WooCommerce_Schema_Integration();
		$woocommerce_schema_integration->register();

		$primary_product_category_service =
			new Primary_Product_Category_Service();

		$primary_product_category_metabox =
			new Primary_Product_Category_Metabox(
				service: $primary_product_category_service,
			);
		$primary_product_category_metabox->register();

		$yoast_primary_category_integration =
			new Yoast_Primary_Category_Integration();
		$yoast_primary_category_integration->register();

		$catalog_report_controller = new Catalog_Report_Controller(
			catalog_service: $catalog_service,
			analysis_service: $analysis_service,
		);
		$catalog_report_controller->register();

		$yoast_product_meta_cleanup_migration =
			new Yoast_Product_Meta_Cleanup_Migration();

		$product_migrations_controller =
			new Product_Migrations_Controller(
				cleanup_migration: $yoast_product_meta_cleanup_migration,
			);
		$product_migrations_controller->register();

		$admin_page_controller = new Admin_Page_Controller(
			catalog_report_controller: $catalog_report_controller,
			migrations_controller: $product_migrations_controller,
		);

		$admin_menu = new Admin_Menu(
			product_page: $admin_page_controller,
		);
		$admin_menu->register();

		$mesh_table_data_factory = new Mesh_Table_Data_Factory();

		$mesh_table_renderer = new Mesh_Product_Table_Renderer();

		$mesh_table_shortcode = new Mesh_Product_Table_Shortcode(
			data_service: $mesh_data_service,
			table_data_factory: $mesh_table_data_factory,
			renderer: $mesh_table_renderer,
		);
		$mesh_table_shortcode->register();

		$mesh_table_assets = new Mesh_Product_Table_Assets(
			asset_url: SHURLOC_SITE_TOOLS_URL,
			asset_version: SHURLOC_SITE_TOOLS_VERSION,
		);
		$mesh_table_assets->register();

		$mesh_table_tab = new Mesh_Product_Table_Tab(
			shortcode: $mesh_table_shortcode,
		);
		$mesh_table_tab->register();

		$product_breadcrumbs = new Product_Breadcrumbs();
		$product_breadcrumbs->register();

		$breadcrumb_schema = new Breadcrumb_Schema();
		$breadcrumb_schema->register();

		$breadcrumb_separator = new Breadcrumb_Separator();
		$breadcrumb_separator->register();

		$recommendation_eligibility =
			new Product_Recommendation_Eligibility_Service();

		$related_products = new Related_Products(
			eligibility: $recommendation_eligibility,
		);
		$related_products->register();

		$dynamic_cross_sells = new Dynamic_Cross_Sells(
			eligibility: $recommendation_eligibility,
		);
		$dynamic_cross_sells->register();

		$product_tag_pagination = new Product_Tag_Pagination_Integration();
		$product_tag_pagination->register();

		$order_buyer_company = new Order_Buyer_Company_Integration();
		$order_buyer_company->register();
	}
}
