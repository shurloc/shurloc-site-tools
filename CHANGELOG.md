# Changelog

## [0.5.0] - 2026-09-03

### Added

- Migrated the Checkout Tools domain into `shurloc-site-tools`.
- Added configurable raw-material and Sefar tariff fee calculation and customer-facing tooltip functionality.
- Added payment-processing fees for configured WooCommerce payment gateways.
- Added payment-gateway label customization for checkout, order details, and email contexts.
- Added direct-processing status handling for eligible offline-payment orders.
- Added the Checkout Tools settings page, admin page controller, and admin-menu integration.
- Added Checkout-specific JavaScript and CSS assets under `assets/checkout/`.
- Added Checkout domain bootstrap registration to the main Site Tools bootstrap.
- Added PHPUnit coverage for Checkout settings, admin components, tariff handling, payment functionality, frontend assets, and bootstrap wiring.
- Added and extended WordPress and WooCommerce test stubs and doubles required by the Checkout domain.

### Changed

- Consolidated Checkout Tools classes under the `Shurloc\SiteTools\Checkout` namespace.
- Removed the redundant `Shurloc_` prefix from migrated Checkout class names.
- Migrated shared admin dependencies to the Site Tools shared interfaces.
- Updated Checkout asset names and paths to the `assets/checkout/` Site Tools convention.
- Preserved existing option names, menu slugs, hook names, asset handles, payment-gateway identifiers, and other runtime contracts for compatibility.
- Integrated Checkout test support with the existing Site Tools hook, enqueue, capability, and WooCommerce test infrastructure.

## [0.4.1] - 2026-09-04

### Bugfixes

- Corrected product and customer page slugs - the domains aere overlapping in the admin UI.
- Added a handler for the "Clear" link on mesh product pages to reset the table selection.

## [0.4.0] - 2026-09-03

### Added

- Migrated the Product Tools domain into `shurloc-site-tools`.
- Added mesh specification parsing, recognition, and catalog analysis.
- Added catalog reports for recognized, unrecognized, and invalid mesh variations.
- Added product catalog, mesh-product data, recommendation eligibility, and primary-category services.
- Added product and mesh structured-data generation and WooCommerce schema integration.
- Added mesh product table rendering, shortcode support, WooCommerce product-tab integration, and frontend assets.
- Added product breadcrumbs, breadcrumb schema handling, breadcrumb separators, related products, and dynamic cross-sells.
- Added primary product category management and Yoast primary-category compatibility.
- Added product-tag archive pagination and WooCommerce order buyer-company integration.
- Added the Product Tools admin area, catalog reporting, and migration controls.
- Added the Yoast product metadata cleanup migration.
- Added product-specific CSS and JavaScript assets under `assets/product/`.
- Added Product domain bootstrap registration to the main Site Tools bootstrap.
- Added catalog fixture data and integration coverage for catalog reporting, mesh recognition, and mesh product table rendering.
- Added PHPUnit coverage for Product models, parsers, analyzers, reports, services, DTOs, factories, renderers, shortcodes, frontend behavior, integrations, migrations, admin components, and bootstrap wiring.
- Added and extended WordPress and WooCommerce test stubs and doubles required by the Product domain.

### Changed

- Consolidated Product Tools classes under the `Shurloc\SiteTools\Product` namespace.
- Removed the redundant `Shurloc_` prefix from migrated Product class and interface names.
- Migrated shared admin dependencies to the Site Tools shared interfaces.
- Updated Product Tools text-domain usage to `shurloc-site-tools`.
- Updated Product asset names and paths to the `assets/product/` Site Tools convention.
- Changed the Product Tools admin page slug to `shurloc-site-tools`.
- Preserved existing persisted option names, metadata keys, hooks, shortcode names, asset handles, and other runtime identifiers where required for compatibility.
- Integrated Product test fixtures, data providers, stubs, and doubles with the shared Site Tools test bootstrap.
- Replaced fully qualified global test-class references with imports.

## [0.3.0] - 2026-08-31

### Added

- Migrated the Customer Tools domain into `shurloc-site-tools`.
- Added customer activity tracking services and admin columns.
- Added customer purchase tracking services, columns, and filters.
- Added customer cart tracking services and the Users table cart details column.
- Added customer user filters and activity/purchase filtering.
- Added purchase and cart data migrations for existing customer data.
- Added migration locking, rerun support, last-run timestamps, and migration version tracking.
- Added the Customer Tools admin page with Overview and Migrations tabs.
- Added migration confirmation and running-state UI.
- Added Customer Tools admin menu integration.
- Added customer-specific JavaScript and CSS assets under `assets/customer/`.
- Added Customer domain bootstrap registration to the main Site Tools bootstrap.
- Added PHPUnit coverage for customer services, admin components, migrations, and bootstrap wiring.
- Added WordPress and WooCommerce test stubs and doubles required by the Customer domain.

### Changed

- Consolidated Customer Tools namespaces under `Shurloc\SiteTools\Customer`.
- Migrated shared admin dependencies to the Site Tools shared interfaces.
- Updated Customer Tools asset names and paths to the `shurloc-` prefixed Site Tools convention.
- Updated Customer Tools text domain usage to `shurloc-site-tools`.
- Preserved existing customer migration option names and tracking metadata for backward compatibility.

## [0.2.0] - 2026-08-29

### Added

- Added the SEO domain.
- Added FAQ content parsing for structured data generation.
- Added FAQPage schema generation.
- Added FAQ schema integration for the Shur-Loc FAQ page.
- Added PHPUnit coverage for the FAQ schema parser, generator, integration, and SEO domain bootstrap.

## [0.1.0] - 2026-08-29

### Added

- Initial release of the Shur-Loc Site Tools plugin.
- Added namespaced plugin architecture with namespace-to-directory autoloading.
- Added shared plugin bootstrap and domain bootstrap structure.
- Added shared admin page interface.
- Migrated Media Tools functionality into the Media domain.
- Added Media Library Alt Text and SEO Status columns.
- Added Media Library sorting and filtering by attachment alt text.
- Added Media Library SEO styling.
- Added PHPUnit coverage for the autoloader, Media services, Media controller, Media bootstrap, and plugin bootstrap.
