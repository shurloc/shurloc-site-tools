<?php
/**
 * Customer Journey collection eligibility policy.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use WP_User;

/**
 * Central decision for whether a request and user may collect Journey data.
 */
final class Journey_Collection_Policy {
	/**
	 * User-agent classifier shared with Journey session inspection.
	 *
	 * @var Journey_User_Agent_Classifier
	 */
	private Journey_User_Agent_Classifier $user_agent_classifier;

	/**
	 * Filter for site consent and other request-level collection rules.
	 *
	 * Receives the default decision and the current WP_User. A consent
	 * integration should return false when collection is not permitted.
	 */
	public const COLLECTION_ALLOWED_FILTER = 'shurloc_site_tools_journey_collection_allowed';

	/**
	 * Filter supplying WordPress role slugs to exclude from collection.
	 *
	 * Receives an empty list and the current WP_User.
	 */
	public const EXCLUDED_ROLES_FILTER = 'shurloc_site_tools_journey_excluded_roles';

	/**
	 * Filter supplying WordPress capabilities to exclude from collection.
	 *
	 * Receives an empty list and the current WP_User.
	 */
	public const EXCLUDED_CAPABILITIES_FILTER = 'shurloc_site_tools_journey_excluded_capabilities';

	/**
	 * Create the central Journey collection policy.
	 *
	 * @param Journey_User_Agent_Classifier|null $user_agent_classifier Shared classifier.
	 */
	public function __construct( ?Journey_User_Agent_Classifier $user_agent_classifier = null ) {
		$this->user_agent_classifier = $user_agent_classifier ?? new Journey_User_Agent_Classifier();
	}

	/**
	 * Determine whether the current request and user may collect Journey data.
	 *
	 * WordPress admin AJAX may host a later Journey ingestion endpoint; that
	 * endpoint must validate its own action before calling this policy.
	 *
	 * @param WP_User|null $user Current user, or null to read it from WordPress.
	 * @return bool True when the request, consent policy, and user are eligible.
	 */
	public function allows_collection( ?WP_User $user = null ): bool {
		if ( wp_doing_cron() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return false;
		}

		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( ! is_string( $user_agent ) ) {
			return false;
		}

		$classification = $this->user_agent_classifier->classify( user_agent: $user_agent );
		if ( in_array( $classification['client_type'], array( 'crawler', 'http_client' ), true ) ) {
			return false;
		}

		$user = $user ?? wp_get_current_user();

		if ( true !== apply_filters( self::COLLECTION_ALLOWED_FILTER, true, $user ) ) {
			return false;
		}

		if ( 0 >= $user->ID ) {
			return true;
		}

		$excluded_roles = apply_filters( self::EXCLUDED_ROLES_FILTER, array(), $user );
		if ( ! is_array( $excluded_roles ) ) {
			return false;
		}

		foreach ( $excluded_roles as $role ) {
			if ( ! is_string( $role ) || '' === $role ) {
				return false;
			}

			if ( in_array( $role, $user->roles, true ) ) {
				return false;
			}
		}

		$excluded_capabilities = apply_filters(
			self::EXCLUDED_CAPABILITIES_FILTER,
			array(),
			$user
		);

		if ( ! is_array( $excluded_capabilities ) ) {
			return false;
		}

		foreach ( $excluded_capabilities as $capability ) {
			if ( ! is_string( $capability ) || '' === $capability ) {
				return false;
			}

			if ( user_can( $user, $capability ) ) {
				return false;
			}
		}

		return true;
	}
}
