<?php
/**
 * Customer Journey report session context reads.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Shurloc\SiteTools\Customer\Journey\Migrations\Journey_Schema_Migrator;

/**
 * Fetch session headings, attribution, and client inspection data.
 *
 * A session may contain activity from more than one identity period. Its
 * summary counters describe the whole session, so customer-specific totals
 * must be calculated from the identity-scoped event rows instead. Admin
 * controllers must check report-viewing capability before calling this class.
 *
 * @phpstan-type SessionContext array{
 *     id:int,
 *     visitor_id:int,
 *     identity_period_id:int,
 *     user_id_at_start:int|null,
 *     began_authenticated:bool,
 *     started_at:string,
 *     last_activity_at:string,
 *     ended_at:string|null,
 *     landing_path:string|null,
 *     referrer_host:string|null,
 *     utm_source:string|null,
 *     utm_medium:string|null,
 *     utm_campaign:string|null,
 *     utm_term:string|null,
 *     utm_content:string|null,
 *     user_agent:string|null,
 *     client_type:string,
 *     client_name:string|null,
 *     device_type:string,
 *     classification_version:int
 * }
 */
final class Journey_Report_Session_Repository {
	/** At most one session per event in the largest report page. */
	public const MAX_SESSION_IDS = Journey_Report_Repository::MAX_PAGE_SIZE;

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

	/**
	 * Load only the requested sessions, keyed by their internal IDs.
	 *
	 * Missing IDs are omitted so event rows can still render without context.
	 * Null means invalid input, unavailable schema, or malformed database data.
	 * Session attribution describes the start of the whole session; a later
	 * presenter must not treat it as customer-specific after identity changes.
	 *
	 * @param array $session_ids IDs collected from one report event page.
	 * @return array<int,SessionContext>|null Session contexts or null on failure.
	 * @phpstan-param list<mixed> $session_ids
	 */
	public function get_by_ids( array $session_ids ): ?array {
		if ( count( $session_ids ) > self::MAX_SESSION_IDS || ! $this->schema_migrator->is_ready() ) {
			return null;
		}

		foreach ( $session_ids as $session_id ) {
			if ( ! is_int( $session_id ) || 0 >= $session_id ) {
				return null;
			}
		}

		if ( array() === $session_ids ) {
			return array();
		}

		$unique_ids = array_values( array_unique( $session_ids ) );
		$requested  = array_fill_keys( $unique_ids, true );

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $unique_ids ), '%d' ) );
		$sql          = 'SELECT id, visitor_id, identity_period_id, user_id_at_start, began_authenticated, started_at, last_activity_at, ended_at, landing_path, referrer_host, utm_source, utm_medium, utm_campaign, utm_term, utm_content, user_agent, client_type, client_name, device_type, classification_version FROM %i WHERE id IN (' . $placeholders . ') ORDER BY id ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The dynamic fragment contains only fixed %d placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $wpdb->prefix . 'shurloc_journey_sessions', ...$unique_ids ) );

		if ( ! is_array( $rows ) || count( $rows ) > count( $unique_ids ) ) {
			return null;
		}

		$contexts = array();
		foreach ( $rows as $row ) {
			$context = $this->parse_session( row: $row );
			if ( null === $context || ! isset( $requested[ $context['id'] ] ) || isset( $contexts[ $context['id'] ] ) ) {
				return null;
			}

			$contexts[ $context['id'] ] = $context;
		}

		return $contexts;
	}

	/**
	 * Validate a session row before it reaches the reporting presenter.
	 *
	 * @param mixed $row Database result.
	 * @return SessionContext|null Parsed context or null for a malformed row.
	 */
	private function parse_session( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id                     = $this->positive_id( value: $row->id ?? null );
		$visitor_id             = $this->positive_id( value: $row->visitor_id ?? null );
		$identity_period_id     = $this->positive_id( value: $row->identity_period_id ?? null );
		$began_authenticated    = $this->flag( value: $row->began_authenticated ?? null );
		$client_type            = $this->normalized_token( value: $row->client_type ?? null );
		$device_type            = $this->normalized_token( value: $row->device_type ?? null );
		$classification_version = $this->integer(
			value: $row->classification_version ?? null,
			minimum: 0,
			maximum: 65535
		);

		if (
			null === $id || null === $visitor_id || null === $identity_period_id || null === $began_authenticated ||
			null === $client_type || null === $device_type || null === $classification_version ||
			! property_exists( $row, 'user_agent' ) || ! property_exists( $row, 'client_name' ) ||
			! isset( $row->started_at, $row->last_activity_at ) ||
			! is_string( $row->started_at ) || ! is_string( $row->last_activity_at ) ||
			! $this->valid_time( value: $row->started_at ) || ! $this->valid_time( value: $row->last_activity_at ) ||
			$row->last_activity_at < $row->started_at ||
			( null !== ( $row->ended_at ?? null ) && ( ! is_string( $row->ended_at ) || ! $this->valid_time( value: $row->ended_at ) || $row->ended_at < $row->last_activity_at ) )
		) {
			return null;
		}

		$user_id = null;
		if ( null !== ( $row->user_id_at_start ?? null ) ) {
			$user_id = $this->positive_id( value: $row->user_id_at_start );
			if ( null === $user_id ) {
				return null;
			}
		}

		if ( ( null === $user_id && $began_authenticated ) || ( null !== $user_id && ! $began_authenticated ) ) {
			return null;
		}

		foreach ( array( 'landing_path', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $field ) {
			if ( null !== ( $row->$field ?? null ) && ! is_string( $row->$field ) ) {
				return null;
			}
		}

		$user_agent  = $row->user_agent;
		$client_name = $row->client_name;
		if (
			( null !== $user_agent && ( ! is_string( $user_agent ) || ! $this->valid_snapshot_text( value: $user_agent, max_bytes: 1024 ) ) ) ||
			( null !== $client_name && ( ! is_string( $client_name ) || ! $this->valid_snapshot_text( value: $client_name, max_bytes: 64 ) ) )
		) {
			return null;
		}

		return array(
			'id'                     => $id,
			'visitor_id'             => $visitor_id,
			'identity_period_id'     => $identity_period_id,
			'user_id_at_start'       => $user_id,
			'began_authenticated'    => $began_authenticated,
			'started_at'             => $row->started_at,
			'last_activity_at'       => $row->last_activity_at,
			'ended_at'               => $row->ended_at ?? null,
			'landing_path'           => $row->landing_path ?? null,
			'referrer_host'          => $row->referrer_host ?? null,
			'utm_source'             => $row->utm_source ?? null,
			'utm_medium'             => $row->utm_medium ?? null,
			'utm_campaign'           => $row->utm_campaign ?? null,
			'utm_term'               => $row->utm_term ?? null,
			'utm_content'            => $row->utm_content ?? null,
			'user_agent'             => $user_agent,
			'client_type'            => $client_type,
			'client_name'            => $client_name,
			'device_type'            => $device_type,
			'classification_version' => $classification_version,
		);
	}

	/**
	 * Parse a positive database identifier without lossy coercion.
	 *
	 * @param mixed $value Stored identifier.
	 * @return int|null Valid ID or null.
	 */
	private function positive_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Parse a bounded stored integer without lossy coercion.
	 *
	 * @param mixed $value   Stored value.
	 * @param int   $minimum Minimum accepted value.
	 * @param int   $maximum Maximum accepted value.
	 * @return int|null Valid integer or null.
	 */
	private function integer( mixed $value, int $minimum, int $maximum ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => $minimum,
					'max_range' => $maximum,
				),
			)
		);
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Parse a normalized client or device token.
	 *
	 * @param mixed $value Stored token.
	 * @return string|null Valid token or null.
	 */
	private function normalized_token( mixed $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $value )
			? $value
			: null;
	}

	/**
	 * Validate one bounded printable UTF-8 client snapshot value.
	 *
	 * @param string $value     Stored snapshot value.
	 * @param int    $max_bytes Column byte limit.
	 * @return bool Whether the stored value is valid.
	 */
	private function valid_snapshot_text( string $value, int $max_bytes ): bool {
		return '' !== $value &&
			trim( $value ) === $value &&
			$max_bytes >= strlen( $value ) &&
			wp_check_invalid_utf8( $value ) === $value &&
			1 !== preg_match( '/[\x00-\x1F\x7F]/', $value );
	}

	/**
	 * Parse a stored tinyint flag.
	 *
	 * @param mixed $value Database flag.
	 * @return bool|null Flag or null for an invalid value.
	 */
	private function flag( mixed $value ): ?bool {
		if ( 0 === $value || '0' === $value ) {
			return false;
		}

		if ( 1 === $value || '1' === $value ) {
			return true;
		}

		return null;
	}

	/**
	 * Require a canonical UTC MySQL datetime.
	 *
	 * @param string $value Stored timestamp.
	 * @return bool Whether the timestamp is valid.
	 */
	private function valid_time( string $value ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $value;
	}
}
