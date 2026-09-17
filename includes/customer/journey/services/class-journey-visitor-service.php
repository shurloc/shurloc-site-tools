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
use WeakMap;

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
	 * UUIDs issued in this PHP request, keyed by database connection.
	 *
	 * A newly sent cookie is absent from $_COOKIE until the next request. Weak
	 * keys keep test and long-running process database replacements isolated.
	 *
	 * @var WeakMap<object,string>|null
	 */
	private static ?WeakMap $issued_uuids = null;

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
	 * UUID is sent to the browser before database insertion. Later resolutions
	 * in the same request reuse it even though $_COOKIE has not changed, so a
	 * temporary storage failure can retry under the same browser identifier.
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
			$uuid = self::issued_uuid_for_request();

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

				self::remember_issued_uuid( uuid: $uuid );
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

	/**
	 * Read the UUID already issued for this database in this request.
	 *
	 * @return string|null Issued UUID, or null before a cookie write.
	 */
	private static function issued_uuid_for_request(): ?string {
		global $wpdb;

		if ( null === self::$issued_uuids ) {
			return null;
		}

		return self::$issued_uuids[ $wpdb ] ?? null;
	}

	/**
	 * Retain a successfully issued UUID until the current PHP request ends.
	 *
	 * @param string $uuid Canonical visitor UUID sent in the response cookie.
	 * @return void
	 */
	private static function remember_issued_uuid( string $uuid ): void {
		global $wpdb;

		self::$issued_uuids ??= new WeakMap();

		self::$issued_uuids[ $wpdb ] = $uuid;
	}
}
