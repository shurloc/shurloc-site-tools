# Shur-loc Site Tools

Shur-loc Site Tools is an internal WordPress plugin that consolidates
site-specific functionality for the Shur-loc website into a single,
organized plugin.

The plugin is structured by domain so related functionality can be
developed, tested, and maintained independently while sharing a common
bootstrap, autoloader, interfaces, and test infrastructure.

## Current Domains

### Checkout

Checkout and payment tools, including:

- Configurable raw-material and Sefar tariff fees.
- Customer-facing tariff tooltips in the cart and checkout.
- Payment-processing fees for configured WooCommerce gateways.
- Payment-gateway label customization in checkout, order, and email contexts.
- Direct-processing status handling for eligible offline-payment orders.
- Checkout settings and administration under the Shur-loc Tools menu.

### Customer

Customer administration and tracking tools, including:

- Customer activity tracking.
- Purchase tracking.
- Cart tracking and stored cart snapshots.
- WordPress Users table columns for customer information.
- Customer activity, purchase, and user filters.
- Cart detail panels on the Users screen.
- Purchase and cart data migrations.
- Migration locking and rerun support.
- Migration version and last-run tracking.
- Customer Tools admin page with Overview and Migrations tabs.

### Media

Media-library tools and administration functionality.

### Product

Product catalog and merchandising tools, including:

- Mesh specification parsing, recognition, and catalog reporting.
- Product and mesh structured data.
- Mesh product tables and WooCommerce product-tab integration.
- Product breadcrumbs, related products, and dynamic cross-sells.
- Primary product category management and Yoast integration.
- Product migrations and administration under the Shur-loc Tools menu.

### SEO

Site SEO functionality and related integrations.

## Requirements

Shur-loc Site Tools is intended for the Shur-loc WordPress/WooCommerce
site and requires:

- WordPress 7.0 or later.
- PHP 8.4 or later.
- WooCommerce.
- Yoast SEO (`wordpress-seo`).
- Shur-loc Tools (`shurloc-tools`).

Refer to `composer.json` and the project's development tooling
configuration for the authoritative PHP and package requirements.

## Installation

1.  Place the plugin in `wp-content/plugins/shurloc-site-tools/`.
2.  Run `composer install` when setting up a development copy.
3.  Activate **Shur-loc Site Tools** in WordPress.

The plugin bootstrap loads the Site Tools autoloader and registers each
domain.

## Project Structure

```text
shurloc-site-tools/
|-- assets/
|   |-- checkout/
|   |-- customer/
|   |-- media/
|   `-- product/
|-- includes/
|   |-- checkout/
|   |-- customer/
|   |-- media/
|   |-- product/
|   |-- seo/
|   |-- shared/
|   |-- class-autoloader.php
|   |-- constants.php
|   `-- bootstrap.php
|-- tests/
|   |-- checkout/
|   |-- customer/
|   |-- doubles/
|   |-- media/
|   |-- product/
|   |-- seo/
|   `-- stubs/
`-- shurloc-site-tools.php
```

## Architecture

### Domain Bootstraps

Each major domain owns a `Bootstrap` class responsible for constructing
its services, controllers, and other components and registering their
WordPress hooks.

The root plugin bootstrap loads and registers the plugin autoloader,
creates each domain bootstrap, and calls `register()` on each domain.

### Shared Interfaces

Cross-domain contracts belong under `includes/shared/interfaces/`.

For example, admin pages use the shared `Admin_Page_Interface` rather
than depending on an interface from another Shur-loc plugin.

### Namespaces

Plugin classes use the root namespace `Shurloc\SiteTools`.

Domain classes are grouped beneath it, such as:

```php
Shurloc\SiteTools\Checkout\Admin
Shurloc\SiteTools\Checkout\Integrations
Shurloc\SiteTools\Customer\Admin
Shurloc\SiteTools\Customer\Migrations
Shurloc\SiteTools\Customer\Services
Shurloc\SiteTools\Product\Integrations
Shurloc\SiteTools\SEO\Generators
```

## Customer Data Migrations

The Customer domain includes controlled migrations for rebuilding
tracking data for existing users.

Current migrations include:

- **Purchase Tracking Seeding** --- seeds each registered user's
  last-purchase data from the most recent qualifying WooCommerce
  order.
- **Cart Tracking Seeding** --- seeds stored cart snapshots from
  existing WooCommerce session data.

Migration controls include an enable checkbox, confirmation prompt,
running-state overlay, concurrent-run protection, last-run timestamp and
version displays, completion counts, and support for intentional reruns.

## Assets

Domain-specific assets are grouped beneath the domain name.

Domain assets include:

```text
assets/checkout/css/shurloc-tariff-tooltips.css
assets/checkout/js/shurloc-payment-processing-fee.js
assets/checkout/js/shurloc-tariff-tooltips.js
assets/customer/css/shurloc-customer-migrations.css
assets/customer/css/shurloc-user-cart-column.css
assets/customer/js/shurloc-customer-migrations.js
assets/customer/js/shurloc-user-cart-column.js
assets/media/css/shurloc-media-library-seo.css
assets/product/css/
assets/product/js/
```

Shur-loc asset filenames use the `shurloc-` prefix.

## Development Conventions

The project follows WordPress coding standards along with
Shur-loc-specific conventions:

- Use strict types in PHP files.
- Use namespaces for plugin classes.
- Use named parameters for calls to internal methods and constructors.
- Import global PHP classes with `use` statements instead of
  leading-backslash notation.
- Do not use `parent` as a variable or parameter name; use a
  descriptive name such as `parent_id` or `parent_term`.
- Do not use `default` as a variable or parameter name; use a
  descriptive name such as `default_value`.
- Prefix asset filenames with `shurloc-`.
- Include a file-level header docblock with
  `@package ShurlocSiteTools`.
- Keep tests organized to mirror the corresponding `includes/`
  structure where practical.

## Testing

The project uses PHPUnit with WordPress and WooCommerce stubs and test
doubles.

Run the project's configured test suite from the repository root using
the Composer scripts defined by the project.

Tests cover individual services and controllers as well as domain and
root bootstrap wiring.

Test globals use PHPDoc-style descriptive comments without `@var`
annotations.

## Static Analysis and Coding Standards

The project uses automated tooling for static analysis and
coding-standard checks.

Run the Composer scripts configured in `composer.json` before creating a
release. The project's configured commands are the authoritative source
for the exact test, PHPStan, and PHPCS invocations.

## Releases

Releases use semantic version tags such as `v0.5.0`.

Annotated release tags use one summary line followed by three detail
lines describing the primary release changes.

Before cutting a release:

1.  Run the complete automated test suite.
2.  Run static analysis and coding-standard checks.
3.  Verify the release on staging.
4.  Update the changelog and version information.
5.  Create and push the annotated Git tag.

## Changelog

See `CHANGELOG.md` for release history and notable changes.

## Status

Shur-loc Site Tools is an internal Shur-loc project and is actively
being expanded as functionality from existing site-specific plugins and
snippets is consolidated into domain-based components.
