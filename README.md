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
- First-party customer Journey collection, reporting, retention, export, and
  erasure.
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

## Customer Journey

The Customer Journey subsystem records a bounded set of first-party behavioral
events in dedicated database tables. Collection, storefront tracking, reports,
retention, and WordPress privacy callbacks all stop safely when the Journey
schema is unavailable.

### Database Schema and Migrations

Journey schema version `1` uses four InnoDB tables. Every table name starts
with the current WordPress `$wpdb->prefix`:

- `shurloc_journey_visitors` stores the opaque visitor UUID, created and last
  seen times, and first-touch attribution. `visitor_uuid` is unique, and
  `last_seen_at` is indexed for lifecycle queries.
- `shurloc_journey_identity_periods` records anonymous and authenticated
  periods for each visitor. Its indexes support visitor, WordPress user, start
  time, and end time queries.
- `shurloc_journey_sessions` stores the identity at session start, session
  boundaries, landing attribution, and aggregate event counters. Its indexes
  support visitor and identity-period chronology and last-activity cleanup.
- `shurloc_journey_events` stores chronological event facts, object IDs,
  quantity, cumulative visible duration, source, and an optional idempotency
  key. The idempotency key is unique. Additional indexes support session,
  visitor, user, date, type, product, and order queries.

The integer schema version is independent of the plugin version and is stored
in the `shurloc_customer_journey_db_version` option. Authorized admin requests
run pending migrations sequentially. Migration locking prevents concurrent
upgrades, and the version advances only after table, column, index, and InnoDB
verification succeeds. A failure disables schema-dependent Journey work and
shows an administrator notice with an explicit retry action. Deactivation does
not delete Journey tables or collected data.

### Visitor Identity

The first-party `shurloc_visitor_id` cookie contains only a random UUID. It is
HTTP-only, SameSite Lax, host-only, scoped to the WordPress cookie path, and
marked Secure on HTTPS. Its default lifetime is 365 days and is independent of
database retention.

A missing or invalid cookie is replaced only after the central collection
policy and schema readiness checks pass. Each browser UUID has a history of
identity periods:

- A new anonymous visitor starts an anonymous period.
- A returning anonymous visitor continues that visitor and period.
- Authentication links the preceding anonymous period to the WordPress user
  and starts a separate authenticated period.
- Later events retain their user-at-event value, so historical anonymous
  activity remains distinguishable in reports.
- Multiple browser UUIDs can be associated with the same WordPress user.

No email address, WordPress user ID, or other personally identifying value is
stored in the browser cookie.

### Sessions and Attribution

A session continues while accepted activities are no more than 30 minutes
apart. The timeout measures the gap between recorded activities rather than a
mouse or keyboard inactivity period. A timeout starts a new session and closes
the previous one. Authentication can occur within an existing session; the
session's start identity remains fixed while each event records its identity
at the time of that event.

First-touch visitor attribution and session landing attribution retain only:

- the URL path without its query string or fragment;
- the referrer hostname without a path or query string; and
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`.

Malformed, oversized, credential-bearing, IP-address referrers, and other
query parameters are discarded.

### Event Definitions

Journey v1 accepts these event types:

| Event | Meaning |
| --- | --- |
| `PAGE_VIEW` | A non-product page became visible in the browser. |
| `PRODUCT_VIEW` | A product page became visible. It counts as one page view and one product view; no second `PAGE_VIEW` is written. |
| `ADD_TO_CART` | A successful WooCommerce add, restore, or positive quantity change. It stores server-provided product, variation, and added quantity values. |
| `REMOVE_FROM_CART` | A successful WooCommerce removal or negative quantity change. It stores server-provided product, variation, and removed quantity values. |
| `CHECKOUT_STARTED` | One distinct entry to a verified checkout page. A reload keeps the entry token, leaving checkout clears it, and a back-forward-cache return creates a new entry. |
| `ORDER_CREATED` | A persisted WooCommerce order record was created. It does not mean the order was paid, completed, cancelled, or refunded. |

`ORDER_CREATED` supports classic checkout and Checkout Blocks through the
classic checkout order-created hook and WooCommerce Store API hooks. Its
idempotency key is derived from the order ID, so repeated hooks do not create
duplicate events. Future order lifecycle events can use separate event types
without changing the event table.

### Browser Tracking and Duration

The small vanilla JavaScript tracker posts page views to
`shurloc-site-tools/v1/journey/view` and cumulative duration updates to
`shurloc-site-tools/v1/journey/duration`. It starts a view only when
`document.visibilityState` is `visible`. It pauses on hidden or suspended
documents, resumes on visibility, and sends checkpoints every two minutes and
at visibility or page-lifecycle boundaries.

There is no mouse or keyboard inactivity cutoff. Visible duration is an
estimate of foreground-page time. Cumulative updates are monotonic and update
the original view and session delta rather than adding the same interval more
than once.

Visitor creation happens during ingestion, so cacheable HTML contains no
visitor identifier. Anonymous requests do not require a per-visitor nonce;
logged-in requests receive a WordPress REST nonce. The endpoint also checks
collection eligibility, schema readiness, content type, payload size, exact
field allowlists, page context, and same-origin browser headers. These controls
reduce browser abuse but do not authenticate arbitrary scripted clients.

### Collection Policy and Consent

One central policy gates asset loading, identity resolution, sessions, and
event ingestion. It excludes cron, ordinary admin requests, malformed user
agents, and a small set of common crawler and command-line client signatures.
The bot filter is intentionally heuristic and is not a comprehensive crawler
database.

Consent management should integrate through
`shurloc_site_tools_journey_collection_allowed` and return `false` whenever
the current request or user must not be tracked. Roles and capabilities can be
excluded centrally through the filters listed below. No role or capability is
excluded by default.

### Admin Reporting

Administrators with `manage_options` can use the **Journeys** tab in Customer
Tools. The report supports a WordPress customer or a never-linked anonymous
visitor, groups events by local date and session, shows session and date
totals, and distinguishes activity recorded while anonymous from activity
recorded while authenticated.

Reports default to seven local calendar days, allow at most 31 days, and read
50 events at a time with keyset pagination. The anonymous selector also reads
50 visitors at a time. It uses internal labels such as `Anonymous Visitor #42`
and does not expose raw visitor UUIDs. Once a visitor has been linked to an
account, its history is available through the customer report.

### Retention

The retention policy has separate scopes for raw events, anonymous history,
and identified history. All three periods default to `null`, so scheduled
cleanup deletes no Journey data until the site supplies approved positive day
values through `shurloc_site_tools_journey_retention_days`.

Cleanup is scheduled daily and processes bounded batches of 100 roots by
default. A lock serializes cleanup. When more expired data remains, a single
continuation event is scheduled one minute later. Identified cleanup removes
expired sessions and history and clears expired attribution; anonymous cleanup
removes expired never-linked histories. Raw-event cleanup can use its own
cutoff independently of session-summary retention.

### Privacy Export and Erasure

Journey registers with WordPress's personal-data exporter and eraser. Both
resolve the confirmed privacy-request email to an existing WordPress account;
Journey itself does not store email addresses.

Exports are paged and include readable identity-period, session, and event
records without raw visitor UUIDs or WordPress user IDs. Erasure deletes that
user's linked events, sessions, and identity periods in bounded transactions,
then removes a visitor row only when it is orphaned. A failed erasure reports
that data may remain instead of claiming success.

### Configuration Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `shurloc_site_tools_journey_collection_allowed` | `true` | Consent and request-level collection decision. Receives the current `WP_User`. |
| `shurloc_site_tools_journey_excluded_roles` | `[]` | Role slugs excluded from collection. Receives the current `WP_User`. |
| `shurloc_site_tools_journey_excluded_capabilities` | `[]` | Capabilities excluded from collection. Receives the current `WP_User`. |
| `shurloc_site_tools_journey_visitor_cookie_lifetime` | `31536000` | Positive visitor-cookie lifetime in seconds. |
| `shurloc_site_tools_journey_session_timeout_seconds` | `1800` | Positive gap between accepted activities before a new session. |
| `shurloc_site_tools_journey_duration_grace_seconds` | `60` | Server allowance for duration request latency and timestamp precision; maximum 3600. |
| `shurloc_site_tools_journey_retention_days` | all scopes `null` | Positive retention days for `raw_events`, `anonymous_history`, and `identified_history`. |
| `shurloc_site_tools_journey_retention_batch_size` | `100` | Roots handled by each retention operation; maximum 1000. |
| `shurloc_site_tools_journey_privacy_erasure_batch_size` | `100` | Maximum dependent rows removed per privacy operation; maximum 1000. |
| `shurloc_site_tools_journey_privacy_export_page_size` | `50` | Records returned by each privacy exporter call; maximum 100. |

Journey also uses the scheduled action hooks
`shurloc_site_tools_journey_retention_cleanup` and
`shurloc_site_tools_journey_retention_continue` for daily and continuation
cleanup runs.

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
