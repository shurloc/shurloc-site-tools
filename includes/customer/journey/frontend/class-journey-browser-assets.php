<?php
/**
 * Customer Journey browser tracking assets.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Frontend;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Page_Context_Resolver;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Rest\Journey_Browser_Ingestion_Controller;

/**
 * Enqueue the Journey transport on schema-ready public pages.
 *
 * Request-specific collection policy is enforced by the ingestion controller.
 * Applying that policy while rendering cacheable HTML would let an excluded
 * request populate the shared page cache without the tracker for eligible
 * visitors.
 */
final class Journey_Browser_Assets {
	/** Script handle shared with inline configuration. */
	public const SCRIPT_HANDLE = 'shurloc-journey-tracker';

	/**
	 * Installed Journey schema gate.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null $schema_migrator Schema gate.
	 */
	public function __construct( ?Journey_Schema_Migrator $schema_migrator = null ) {
		$this->schema_migrator = $schema_migrator ?? new Journey_Schema_Migrator();
	}

	/**
	 * Register the frontend enqueue hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the tracker with endpoint URLs, checkout context, and optional nonce.
	 *
	 * Anonymous pages carry no user-specific nonce, so they can be cached.
	 * Authenticated pages must not be served from a shared full-page cache.
	 * The REST permission callback applies consent, user exclusions, automated
	 * client detection, and the same schema check before accepting any event.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! $this->schema_migrator->is_ready() ) {
			return;
		}

		$namespace = Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE;
		$config    = array(
			'viewUrl'        => esc_url_raw( rest_url( $namespace . '/journey/view' ) ),
			'durationUrl'    => esc_url_raw( rest_url( $namespace . '/journey/duration' ) ),
			'nonce'          => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'isCheckoutPage' => $this->is_checkout_page(),
		);
		$json      = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( ! is_string( $json ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			SHURLOC_SITE_TOOLS_URL . 'assets/customer/js/shurloc-journey-tracker.js',
			array(),
			SHURLOC_SITE_TOOLS_VERSION,
			true
		);

		wp_add_inline_script( self::SCRIPT_HANDLE, 'window.shurlocJourneyBrowser = ' . $json . ';', 'before' );
	}

	/**
	 * Confirm the configured public checkout page, excluding its subroutes.
	 *
	 * Both classic checkout and Checkout Blocks use WooCommerce's configured
	 * checkout page. The resolver checks its canonical path and published state.
	 *
	 * @return bool Whether the current document is the checkout page.
	 */
	private function is_checkout_page(): bool {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}

		$request_uri = $_SERVER['REQUEST_URI'] ?? null;
		return is_string( $request_uri ) &&
			( new Journey_Page_Context_Resolver() )->is_checkout_page( page_uri: $request_uri );
	}
}
