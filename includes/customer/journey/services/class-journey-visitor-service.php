<?php
/**
 * Customer Journey visitor identity resolution.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Services;

defined( 'ABSPATH' ) || exit;

use Random\RandomException;
use Shurloc\SiteTools\Customer\Journey\Journey_Collection_Policy;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_Cookie;
use Shurloc\SiteTools\Customer\Journey\Journey_Visitor_UUID;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Identity_Period_Repository;
use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Visitor_Repository;

/**
 * Resolve the current browser and WordPress user to one Journey identity.
 *
 * @phpstan-type VisitorIdentity array{
 *     visitor_id:int,
 *     visitor_uuid:string,
 *     identity_period_id:int,
 *     user_id_at_event:int|null
 * }
 */
final class Journey_Visitor_Service {
	/**
	 * Central request and consent policy.
	 *
	 * @var Journey_Collection_Policy
	 */
	private Journey_Collection_Policy $collection_policy;

	/**
	 * First-party visitor cookie transport.
	 *
	 * @var Journey_Visitor_Cookie
	 */
	private Journey_Visitor_Cookie $cookie;

	/**
	 * Visitor row storage.
	 *
	 * @var Journey_Visitor_Repository
	 */
	private Journey_Visitor_Repository $visitors;

	/**
	 * Anonymous and authenticated identity history.
	 *
	 * @var Journey_Identity_Period_Repository
	 */
	private Journey_Identity_Period_Repository $identity_periods;

	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Collection_Policy|null          $collection_policy Request policy.
	 * @param Journey_Visitor_Cookie|null             $cookie            Visitor cookie.
	 * @param Journey_Visitor_Repository|null         $visitors          Visitor storage.
	 * @param Journey_Identity_Period_Repository|null $identity_periods Identity history.
	 * @param Journey_Schema_Migrator|null            $schema_migrator    Schema gate.
	 */
	public function __construct(
		?Journey_Collection_Policy $collection_policy = null,
		?Journey_Visitor_Cookie $cookie = null,
		?Journey_Visitor_Repository $visitors = null,
		?Journey_Identity_Period_Repository $identity_periods = null,
		?Journey_Schema_Migrator $schema_migrator = null
	) {
		$this->collection_policy = $collection_policy ?? new Journey_Collection_Policy();
		$this->cookie            = $cookie ?? new Journey_Visitor_Cookie();
		$this->visitors          = $visitors ?? new Journey_Visitor_Repository();
		$this->identity_periods  = $identity_periods ?? new Journey_Identity_Period_Repository();
		$this->schema_migrator   = $schema_migrator ?? new Journey_Schema_Migrator();
	}

	/**
	 * Resolve the current request without accepting a browser-supplied user ID.
	 *
	 * Policy and schema checks precede cookie access and database work. A new
	 * UUID is sent to the browser before database insertion, so a temporary
	 * storage failure can be retried under the same browser identifier.
	 *
	 * The caller may record an event only when this method returns an identity.
	 *
	 * @return VisitorIdentity|null Current identity, or null when collection is unavailable.
	 * @phpstan-impure
	 */
	public function resolve(): ?array {
		$user = wp_get_current_user();

		if (
			! $this->collection_policy->allows_collection( user: $user ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		$uuid = $this->cookie->read();

		if ( null === $uuid ) {
			try {
				$uuid = Journey_Visitor_UUID::generate();
			} catch ( RandomException $error ) {
				unset( $error );
				return null;
			}

			if ( ! $this->cookie->write( uuid: $uuid ) ) {
				return null;
			}
		}

		$observed_at = gmdate( 'Y-m-d H:i:s' );
		$visitor_id  = $this->visitors->find_or_create(
			uuid: $uuid,
			seen_at: $observed_at,
		);

		if ( null === $visitor_id ) {
			return null;
		}

		$user_id   = 0 < $user->ID ? (int) $user->ID : null;
		$period_id = $this->identity_periods->ensure_current(
			visitor_id: $visitor_id,
			user_id: $user_id,
			observed_at: $observed_at,
		);

		if ( null === $period_id ) {
			return null;
		}

		return array(
			'visitor_id'         => $visitor_id,
			'visitor_uuid'       => $uuid,
			'identity_period_id' => $period_id,
			'user_id_at_event'   => $user_id,
		);
	}
}
