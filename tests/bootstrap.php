<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ShurlocSiteTools
 */

use Shurloc\SiteTools\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

/**
 * Load Composer's autoloader.
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Load plugin autoloader.
 *
 * The autoloader cannot load itself, so this remains a manual include.
 */
require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

$shurloc_autoloader = new Autoloader(
	base_directory: dirname( __DIR__ ) . '/includes',
);

$shurloc_autoloader->register();

require_once __DIR__ . '/product/parsers/MeshParserDataProvider.php';

/**
 * Load stubs and test doubles.
 */
require_once __DIR__ . '/stubs/wordpress-functions.php';

require_once dirname( __DIR__ ) . '/includes/constants.php';
require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

require_once __DIR__ . '/stubs/customer-formatters-functions.php';
require_once __DIR__ . '/stubs/customer-services-functions.php';
require_once __DIR__ . '/stubs/customer-admin-functions.php';
require_once __DIR__ . '/stubs/customer-migrations-functions.php';

require_once __DIR__ . '/stubs/woocommerce-functions.php';

require_once __DIR__ . '/doubles/class-wp-query.php';
require_once __DIR__ . '/doubles/class-wp-post.php';
require_once __DIR__ . '/doubles/class-wp-screen.php';
require_once __DIR__ . '/doubles/class-wp-user.php';
require_once __DIR__ . '/doubles/class-catalog-report-actions.php';
require_once __DIR__ . '/doubles/class-catalog-report-controller.php';
require_once __DIR__ . '/doubles/class-product-catalog-service.php';
require_once __DIR__ . '/doubles/class-mesh-product-analyzer.php';
require_once __DIR__ . '/doubles/class-mesh-product-data-service.php';
require_once __DIR__ . '/doubles/class-mesh-product-schema-service.php';
require_once __DIR__ . '/doubles/class-mesh-product-table-renderer.php';
require_once __DIR__ . '/doubles/class-mesh-product-table-shortcode.php';
require_once __DIR__ . '/doubles/class-product-schema-renderer.php';
require_once __DIR__ . '/doubles/class-product-schema-service.php';
require_once __DIR__ . '/doubles/class-wc-datetime.php';
require_once __DIR__ . '/doubles/class-wc-order.php';
require_once __DIR__ . '/doubles/class-wc-product.php';
require_once __DIR__ . '/doubles/class-wc-product-variation.php';
require_once __DIR__ . '/doubles/class-test-wc-product.php';
require_once __DIR__ . '/doubles/class-test-wc-product-variation.php';
require_once __DIR__ . '/doubles/class-wc-cart.php';
require_once __DIR__ . '/doubles/class-wc-cart-double.php';
require_once __DIR__ . '/doubles/class-test-wc-cart.php';
require_once __DIR__ . '/doubles/class-woocommerce.php';
require_once __DIR__ . '/doubles/class-wp-user-query.php';
require_once __DIR__ . '/doubles/class-shurloc-test-wpdb.php';
