<?php
/**
 * Customer Journey session storage.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;
use Throwable;

/**
 * Serializes session boundaries for one visitor without changing identity history.
 *
 * @phpstan-type OpenSession array{id:int,started_at:string,last_activity_at:string}
 */
final class Journey_Session_Repository {
	/**
	 * Journey schema readiness check.
	 *
	 * @var Journey_Schema_Migrator
	 */
	private Journey_Schema_Migrator $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @param Journey_Schema_Migrator|null $schema_migrator Journey schema check.
	 */
	public function __construct( ?Journey_Schema_Migrator $schema_migrator = null ) {
		$this->schema_migrator = $schema_migrator ?? new Journey_Schema_Migrator();
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Transaction failures are caught and returned as null.
	/**
	 * Continue a visitor's session or start one after the inactivity timeout.
	 *
	 * The caller supplies the centrally configured timeout and sanitized
	 * attribution. Only an accepted activity calls this method. The visitor row
	 * lock makes the lookup, close, and insert one serialized transaction.
	 * Identity and attribution columns describe the session's start and are not
	 * rewritten when the visitor signs in or later arrives from another source.
	 *
	 * @param int         $visitor_id        Existing visitor ID.
	 * @param int         $identity_period_id Identity period at session start.
	 * @param int|null    $user_id_at_start  Current user ID, or null for anonymous.
	 * @param string      $observed_at       Server-generated UTC MySQL datetime.
	 * @param int         $timeout_seconds   Inactivity timeout supplied by caller.
	 * @param string|null $landing_path      Sanitized landing path.
	 * @param string|null $referrer_host     Sanitized referrer host.
	 * @param string|null $utm_source        Sanitized UTM source.
	 * @param string|null $utm_medium        Sanitized UTM medium.
	 * @param string|null $utm_campaign      Sanitized UTM campaign.
	 * @param string|null $utm_term          Sanitized UTM term.
	 * @param string|null $utm_content       Sanitized UTM content.
	 * @param string|null $user_agent        Bounded user-agent at session start.
	 * @param string      $client_type       Normalized client type.
	 * @param string|null $client_name       Normalized client name.
	 * @param string      $device_type       Normalized device type.
	 * @param int         $classification_version Classifier rules version.
	 * @return int|null Session ID, or null when storage is unavailable.
	 */
	public function resolve_current(
		int $visitor_id,
		int $identity_period_id,
		?int $user_id_at_start,
		string $observed_at,
		int $timeout_seconds,
		?string $landing_path = null,
		?string $referrer_host = null,
		?string $utm_source = null,
		?string $utm_medium = null,
		?string $utm_campaign = null,
		?string $utm_term = null,
		?string $utm_content = null,
		?string $user_agent = null,
		string $client_type = 'unknown',
		?string $client_name = null,
		string $device_type = 'unknown',
		int $classification_version = 0
	): ?int {
		$observed_timestamp = $this->timestamp( value: $observed_at );
		if (
			0 >= $visitor_id ||
			0 >= $identity_period_id ||
			( null !== $user_id_at_start && 0 >= $user_id_at_start ) ||
			0 >= $timeout_seconds ||
			null === $observed_timestamp ||
			! $this->is_valid_client_snapshot(
				user_agent: $user_agent,
				client_type: $client_type,
				client_name: $client_name,
				device_type: $device_type,
				classification_version: $classification_version,
			) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		try {
			$this->lock_visitor( visitor_id: $visitor_id );
			$current    = $this->get_open_session( visitor_id: $visitor_id );
			$session_id = null;

			if ( null !== $current ) {
				$started_timestamp = $this->timestamp( value: $current['started_at'] );
				$last_timestamp    = $this->timestamp( value: $current['last_activity_at'] );

				if (
					null === $started_timestamp ||
					null === $last_timestamp ||
					$last_timestamp < $started_timestamp ||
					$observed_timestamp < $started_timestamp
				) {
					throw new RuntimeException( 'Journey session time is invalid.' );
				}

				if ( $observed_timestamp - $last_timestamp < $timeout_seconds ) {
					if ( $observed_timestamp > $last_timestamp ) {
						$this->touch_session(
							session_id: $current['id'],
							visitor_id: $visitor_id,
							observed_at: $observed_at,
						);
					}

					$session_id = $current['id'];
				} else {
					$this->close_session(
						session_id: $current['id'],
						visitor_id: $visitor_id,
						ended_at: $current['last_activity_at'],
					);
				}
			}

			if ( null === $session_id ) {
				$session_id = $this->insert_session(
					visitor_id: $visitor_id,
					identity_period_id: $identity_period_id,
					user_id_at_start: $user_id_at_start,
					started_at: $observed_at,
					landing_path: $landing_path,
					referrer_host: $referrer_host,
					utm_source: $utm_source,
					utm_medium: $utm_medium,
					utm_campaign: $utm_campaign,
					utm_term: $utm_term,
					utm_content: $utm_content,
					user_agent: $user_agent,
					client_type: $client_type,
					client_name: $client_name,
					device_type: $device_type,
					classification_version: $classification_version,
				);
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Journey session transaction could not commit.' );
			}

			return $session_id;
		} catch ( Throwable $error ) {
			unset( $error );
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
	}
	// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing

	/**
	 * Lock the parent visitor before examining its open session.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return void
	 * @throws RuntimeException When the visitor row is unavailable.
	 */
	private function lock_visitor( int $visitor_id ): void {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d FOR UPDATE',
				$wpdb->prefix . 'shurloc_journey_visitors',
				$visitor_id
			)
		);

		if ( ( ! is_int( $found ) && ! is_string( $found ) ) ||
			filter_var( $found, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) !== $visitor_id ) {
			throw new RuntimeException( 'Journey visitor is unavailable.' );
		}
	}

	/**
	 * Read at most two open rows so ambiguous session state fails closed.
	 *
	 * @param int $visitor_id Visitor ID.
	 * @return OpenSession|null Open session, or null before the first session.
	 * @throws RuntimeException When open session data is unavailable.
	 */
	private function get_open_session( int $visitor_id ): ?array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, started_at, last_activity_at FROM %i WHERE visitor_id = %d AND ended_at IS NULL ORDER BY started_at DESC, id DESC LIMIT 2 FOR UPDATE',
				$this->table_name(),
				$visitor_id
			)
		);

		if ( ! is_array( $rows ) || 1 < count( $rows ) ) {
			throw new RuntimeException( 'Journey open sessions are unavailable or ambiguous.' );
		}

		if ( array() === $rows ) {
			return null;
		}

		$row = $rows[0];
		if (
			! is_object( $row ) ||
			! isset( $row->id, $row->started_at, $row->last_activity_at ) ||
			! is_string( $row->started_at ) ||
			! is_string( $row->last_activity_at )
		) {
			throw new RuntimeException( 'Journey open session is invalid.' );
		}

		if ( ! is_int( $row->id ) && ! is_string( $row->id ) ) {
			throw new RuntimeException( 'Journey session ID is invalid.' );
		}

		$id = filter_var( $row->id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $id ) {
			throw new RuntimeException( 'Journey session ID is invalid.' );
		}

		return array(
			'id'               => $id,
			'started_at'       => $row->started_at,
			'last_activity_at' => $row->last_activity_at,
		);
	}

	/**
	 * Advance only a newer activity time.
	 *
	 * @param int    $session_id Session ID.
	 * @param int    $visitor_id Visitor ID.
	 * @param string $observed_at UTC activity time.
	 * @return void
	 * @throws RuntimeException When the conditional update fails.
	 */
	private function touch_session( int $session_id, int $visitor_id, string $observed_at ): void {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET last_activity_at = %s WHERE id = %d AND visitor_id = %d AND ended_at IS NULL AND last_activity_at < %s',
				$this->table_name(),
				$observed_at,
				$session_id,
				$visitor_id,
				$observed_at
			)
		);

		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Journey session activity could not advance.' );
		}
	}

	/**
	 * Close an expired session at its last actual activity time.
	 *
	 * @param int    $session_id Session ID.
	 * @param int    $visitor_id Visitor ID.
	 * @param string $ended_at Last recorded activity time.
	 * @return void
	 * @throws RuntimeException When the conditional close fails.
	 */
	private function close_session( int $session_id, int $visitor_id, string $ended_at ): void {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET ended_at = %s WHERE id = %d AND visitor_id = %d AND ended_at IS NULL',
				$this->table_name(),
				$ended_at,
				$session_id,
				$visitor_id
			)
		);

		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Journey session could not close.' );
		}
	}

	/**
	 * Insert a session with its immutable start identity and attribution.
	 *
	 * @param int         $visitor_id         Visitor ID.
	 * @param int         $identity_period_id Start identity period ID.
	 * @param int|null    $user_id_at_start   Start user ID, or null.
	 * @param string      $started_at         UTC start time.
	 * @param string|null $landing_path       Sanitized landing path.
	 * @param string|null $referrer_host      Sanitized referrer host.
	 * @param string|null $utm_source         Sanitized UTM source.
	 * @param string|null $utm_medium         Sanitized UTM medium.
	 * @param string|null $utm_campaign       Sanitized UTM campaign.
	 * @param string|null $utm_term           Sanitized UTM term.
	 * @param string|null $utm_content        Sanitized UTM content.
	 * @param string|null $user_agent         Bounded user-agent at session start.
	 * @param string      $client_type        Normalized client type.
	 * @param string|null $client_name        Normalized client name.
	 * @param string      $device_type        Normalized device type.
	 * @param int         $classification_version Classifier rules version.
	 * @return int New session ID.
	 * @throws RuntimeException When insertion fails.
	 */
	private function insert_session(
		int $visitor_id,
		int $identity_period_id,
		?int $user_id_at_start,
		string $started_at,
		?string $landing_path,
		?string $referrer_host,
		?string $utm_source,
		?string $utm_medium,
		?string $utm_campaign,
		?string $utm_term,
		?string $utm_content,
		?string $user_agent,
		string $client_type,
		?string $client_name,
		string $device_type,
		int $classification_version
	): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table_name(),
			array(
				'visitor_id'             => $visitor_id,
				'identity_period_id'     => $identity_period_id,
				'user_id_at_start'       => $user_id_at_start,
				'began_authenticated'    => null === $user_id_at_start ? 0 : 1,
				'started_at'             => $started_at,
				'last_activity_at'       => $started_at,
				'landing_path'           => $landing_path,
				'referrer_host'          => $referrer_host,
				'utm_source'             => $utm_source,
				'utm_medium'             => $utm_medium,
				'utm_campaign'           => $utm_campaign,
				'utm_term'               => $utm_term,
				'utm_content'            => $utm_content,
				'user_agent'             => $user_agent,
				'client_type'            => $client_type,
				'client_name'            => $client_name,
				'device_type'            => $device_type,
				'classification_version' => $classification_version,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( 1 !== $inserted || 0 >= $wpdb->insert_id ) {
			throw new RuntimeException( 'Journey session could not be inserted.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Confirm a client snapshot fits the V3 session columns.
	 *
	 * @param string|null $user_agent        Bounded user-agent.
	 * @param string      $client_type       Normalized client type.
	 * @param string|null $client_name       Normalized client name.
	 * @param string      $device_type       Normalized device type.
	 * @param int         $classification_version Classifier rules version.
	 * @return bool Whether the complete snapshot is safe to store.
	 */
	private function is_valid_client_snapshot(
		?string $user_agent,
		string $client_type,
		?string $client_name,
		string $device_type,
		int $classification_version
	): bool {
		if (
			0 > $classification_version ||
			65535 < $classification_version ||
			1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $client_type ) ||
			1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $device_type )
		) {
			return false;
		}

		if ( null !== $user_agent && ! $this->is_valid_snapshot_text( value: $user_agent, max_bytes: 1024 ) ) {
			return false;
		}

		return null === $client_name ||
			$this->is_valid_snapshot_text( value: $client_name, max_bytes: 64 );
	}

	/**
	 * Validate one non-empty, trimmed, printable UTF-8 snapshot value.
	 *
	 * @param string $value     Snapshot value.
	 * @param int    $max_bytes Database byte limit used by this repository.
	 * @return bool Whether the value is safe to store.
	 */
	private function is_valid_snapshot_text( string $value, int $max_bytes ): bool {
		return '' !== $value &&
			trim( $value ) === $value &&
			$max_bytes >= strlen( $value ) &&
			wp_check_invalid_utf8( $value ) === $value &&
			1 !== preg_match( '/[\x00-\x1F\x7F]/', $value );
	}

	/**
	 * Parse a strict UTC MySQL datetime for timeout arithmetic.
	 *
	 * @param string $value UTC MySQL datetime.
	 * @return int|null Unix timestamp, or null when invalid.
	 */
	private function timestamp( string $value ): ?int {
		$datetime = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			new DateTimeZone( 'UTC' )
		);

		return false !== $datetime && $datetime->format( 'Y-m-d H:i:s' ) === $value
			? $datetime->getTimestamp()
			: null;
	}

	/**
	 * Get the current site's Journey sessions table name.
	 *
	 * @return string Full table name.
	 */
	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'shurloc_journey_sessions';
	}
}
