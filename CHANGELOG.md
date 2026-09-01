# Changelog

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
