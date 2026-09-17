<?php
/**
 * Customer Journey browser tracking assets.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Frontend;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Rest\Journey_Browser_Ingestion_Controller;

/**
 * Enqueue tracking only when this page may collect Journey activity.
 *
 * The script is added to the Customer bootstrap after its asset exists.
 */
final class Journey_Browser_Assets {
	/** Script handle shared with inline configuration. */
	public const SCRIPT_HANDLE = 'shurloc-journey-tracker';

	/**
	 * Central collection policy.
	 *
	 * @var Journey_Collection_Policy
	 */
	private Journey_Collection_Policy $collection_policy;

	/**
	 * Installed Journey schema gate.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Collection_Policy|null $collection_policy Collection policy.
	 * @param Journey_Schema_Migrator|null   $schema_migrator    Schema gate.
	 */
	public function __construct(
		?Journey_Collection_Policy $collection_policy = null,
		?Journey_Schema_Migrator $schema_migrator = null
	) {
		$this->collection_policy = $collection_policy ?? new Journey_Collection_Policy();
		$this->schema_migrator   = $schema_migrator ?? new Journey_Schema_Migrator();
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
	 * Enqueue the tracker with only its endpoint URLs and optional REST nonce.
	 *
	 * Anonymous pages carry no user-specific nonce, so they can be cached.
	 * Authenticated pages must not be served from a shared full-page cache.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! $this->collection_policy->allows_collection() || ! $this->schema_migrator->is_ready() ) {
			return;
		}

		$namespace = Journey_Browser_Ingestion_Controller::ROUTE_NAMESPACE;
		$config    = array(
			'viewUrl'     => esc_url_raw( rest_url( $namespace . '/journey/view' ) ),
			'durationUrl' => esc_url_raw( rest_url( $namespace . '/journey/duration' ) ),
			'nonce'       => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
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
}
