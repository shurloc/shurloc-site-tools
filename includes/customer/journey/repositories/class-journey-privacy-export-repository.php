<?php
/**
 * Customer Journey personal-data export reads.
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
 * Read bounded Journey records linked to one WordPress user.
 *
 * The export deliberately omits the raw visitor UUID and WordPress user IDs.
 * Internal record IDs remain available so the WordPress adapter can create
 * stable item identifiers and relate an event to its session and device.
 *
 * @phpstan-type ExportData array<string,bool|int|string>
 * @phpstan-type ExportRecord array{
 *     record_type:'identity_period'|'session'|'event',
 *     record_id:int,
 *     identity_period_id:int,
 *     visitor_id:int,
 *     session_id:int|null,
 *     occurred_at:string,
 *     data:ExportData
 * }
 * @phpstan-type ExportPage array{records:list<ExportRecord>,has_more:bool}
 */
final class Journey_Privacy_Export_Repository {
	/** Identity-period export record. */
	public const IDENTITY_PERIOD = 'identity_period';

	/** Session export record. */
	public const SESSION = 'session';

	/** Event export record. */
	public const EVENT = 'event';

	/** Maximum records returned to one WordPress exporter request. */
	public const MAX_PAGE_SIZE = 100;

	/**
	 * Every column expected from the normalized union query.
	 *
	 * @var list<string>
	 */
	private const QUERY_COLUMNS = array(
		'record_type',
		'record_id',
		'identity_period_id',
		'visitor_id',
		'session_id',
		'sort_at',
		'linked_at',
		'record_ended_at',
		'occurred_authenticated',
		'began_authenticated',
		'last_activity_at',
		'landing_path',
		'referrer_host',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'page_view_count',
		'product_view_count',
		'cart_add_count',
		'cart_remove_count',
		'added_quantity',
		'removed_quantity',
		'checkout_started_count',
		'order_created_count',
		'active_ms',
		'event_type',
		'page_path',
		'post_id',
		'product_id',
		'variation_id',
		'quantity',
		'order_id',
		'related_object_id',
		'source',
	);

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
	 * Read one oldest-first page of all Journey records linked to a user.
	 *
	 * Null means invalid input, unavailable schema, or malformed storage data.
	 * The page number follows the WordPress personal-data exporter contract and
	 * starts at one.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $page      One-based exporter page.
	 * @param int $page_size Maximum records returned on the page.
	 * @return array|null Export page, or null on failure.
	 * @phpstan-return ExportPage|null
	 */
	public function get_user_page( int $user_id, int $page, int $page_size = 50 ): ?array {
		if (
			1 > $user_id ||
			1 > $page ||
			1 > $page_size ||
			self::MAX_PAGE_SIZE < $page_size ||
			$page - 1 > intdiv( PHP_INT_MAX, $page_size ) ||
			! $this->schema_migrator->is_ready()
		) {
			return null;
		}

		global $wpdb;

		$fetch_limit = $page_size + 1;
		$offset      = ( $page - 1 ) * $page_size;
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"(SELECT 'identity_period' AS record_type, 0 AS record_rank,
					p.id AS record_id, p.id AS identity_period_id, p.visitor_id, NULL AS session_id,
					p.started_at AS sort_at, p.linked_at, p.ended_at AS record_ended_at,
					NULL AS occurred_authenticated, NULL AS began_authenticated, NULL AS last_activity_at,
					NULL AS landing_path, NULL AS referrer_host, NULL AS utm_source, NULL AS utm_medium,
					NULL AS utm_campaign, NULL AS utm_term, NULL AS utm_content,
					NULL AS page_view_count, NULL AS product_view_count, NULL AS cart_add_count,
					NULL AS cart_remove_count, NULL AS added_quantity, NULL AS removed_quantity,
					NULL AS checkout_started_count, NULL AS order_created_count, NULL AS active_ms,
					NULL AS event_type, NULL AS page_path, NULL AS post_id, NULL AS product_id,
					NULL AS variation_id, NULL AS quantity, NULL AS order_id,
					NULL AS related_object_id, NULL AS source
				FROM %i p WHERE p.user_id = %d)
				UNION ALL
				(SELECT 'session' AS record_type, 1 AS record_rank,
					s.id AS record_id, s.identity_period_id, s.visitor_id, s.id AS session_id,
					s.started_at AS sort_at, NULL AS linked_at, s.ended_at AS record_ended_at,
					NULL AS occurred_authenticated, s.began_authenticated, s.last_activity_at,
					s.landing_path, s.referrer_host, s.utm_source, s.utm_medium, s.utm_campaign,
					s.utm_term, s.utm_content, s.page_view_count, s.product_view_count,
					s.cart_add_count, s.cart_remove_count, s.added_quantity, s.removed_quantity,
					s.checkout_started_count, s.order_created_count, s.active_ms,
					NULL AS event_type, NULL AS page_path, NULL AS post_id, NULL AS product_id,
					NULL AS variation_id, NULL AS quantity, NULL AS order_id,
					NULL AS related_object_id, NULL AS source
				FROM %i s INNER JOIN %i p ON p.id = s.identity_period_id
				WHERE p.user_id = %d AND (s.user_id_at_start IS NULL OR s.user_id_at_start = %d))
				UNION ALL
				(SELECT 'event' AS record_type, 2 AS record_rank,
					e.id AS record_id, s.identity_period_id, e.visitor_id, e.session_id,
					e.occurred_at AS sort_at, NULL AS linked_at, NULL AS record_ended_at,
					CASE WHEN e.user_id_at_event IS NULL THEN 0 ELSE 1 END AS occurred_authenticated,
					NULL AS began_authenticated, NULL AS last_activity_at,
					NULL AS landing_path, NULL AS referrer_host, NULL AS utm_source, NULL AS utm_medium,
					NULL AS utm_campaign, NULL AS utm_term, NULL AS utm_content,
					NULL AS page_view_count, NULL AS product_view_count, NULL AS cart_add_count,
					NULL AS cart_remove_count, NULL AS added_quantity, NULL AS removed_quantity,
					NULL AS checkout_started_count, NULL AS order_created_count, e.active_ms,
					e.event_type, e.page_path, e.post_id, e.product_id, e.variation_id,
					e.quantity, e.order_id, e.related_object_id, e.source
				FROM %i e
				INNER JOIN %i s ON s.id = e.session_id
				INNER JOIN %i p ON p.id = s.identity_period_id
				WHERE p.user_id = %d
				AND (s.user_id_at_start IS NULL OR s.user_id_at_start = %d)
				AND (e.user_id_at_event IS NULL OR e.user_id_at_event = %d))
				ORDER BY sort_at ASC, record_rank ASC, record_id ASC LIMIT %d OFFSET %d",
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$user_id,
				$wpdb->prefix . 'shurloc_journey_sessions',
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$user_id,
				$user_id,
				$wpdb->prefix . 'shurloc_journey_events',
				$wpdb->prefix . 'shurloc_journey_sessions',
				$wpdb->prefix . 'shurloc_journey_identity_periods',
				$user_id,
				$user_id,
				$user_id,
				$fetch_limit,
				$offset
			)
		);

		return $this->parse_page( rows: $rows, page_size: $page_size );
	}

	/**
	 * Validate and normalize a database page.
	 *
	 * @param mixed $rows      Database rows.
	 * @param int   $page_size Requested record count.
	 * @return array|null Parsed export page, or null for malformed data.
	 * @phpstan-return ExportPage|null
	 */
	private function parse_page( mixed $rows, int $page_size ): ?array {
		if ( ! is_array( $rows ) || count( $rows ) > $page_size + 1 ) {
			return null;
		}

		$has_more = count( $rows ) > $page_size;
		if ( $has_more ) {
			array_pop( $rows );
		}

		$records = array();
		foreach ( $rows as $row ) {
			$record = $this->parse_record( row: $row );
			if ( null === $record ) {
				return null;
			}

			$records[] = $record;
		}

		return array(
			'records'  => $records,
			'has_more' => $has_more,
		);
	}

	/**
	 * Parse one normalized record.
	 *
	 * @param mixed $row Database row.
	 * @return array|null Parsed record, or null for malformed data.
	 * @phpstan-return ExportRecord|null
	 */
	private function parse_record( mixed $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$values = get_object_vars( $row );
		foreach ( self::QUERY_COLUMNS as $column ) {
			if ( ! array_key_exists( $column, $values ) ) {
				return null;
			}
		}

		$record_type        = $values['record_type'];
		$record_id          = $this->integer( value: $values['record_id'], minimum: 1 );
		$identity_period_id = $this->integer( value: $values['identity_period_id'], minimum: 1 );
		$visitor_id         = $this->integer( value: $values['visitor_id'], minimum: 1 );
		$session_id         = $this->optional_id( value: $values['session_id'] );

		if (
			! is_string( $record_type ) ||
			null === $record_id ||
			null === $identity_period_id ||
			null === $visitor_id ||
			( null !== $values['session_id'] && null === $session_id ) ||
			! is_string( $values['sort_at'] ) ||
			null === $this->timestamp( value: $values['sort_at'] )
		) {
			return null;
		}

		$occurred_at = $values['sort_at'];

		switch ( $record_type ) {
			case self::IDENTITY_PERIOD:
				$normalized_type = self::IDENTITY_PERIOD;
				$data            = $this->parse_identity_period( row: $values, record_id: $record_id, session_id: $session_id, occurred_at: $occurred_at );
				break;
			case self::SESSION:
				$normalized_type = self::SESSION;
				$data            = $this->parse_session( row: $values, record_id: $record_id, session_id: $session_id, occurred_at: $occurred_at );
				break;
			case self::EVENT:
				$normalized_type = self::EVENT;
				$data            = $this->parse_event( row: $values, session_id: $session_id, occurred_at: $occurred_at );
				break;
			default:
				return null;
		}

		if ( null === $data ) {
			return null;
		}

		return array(
			'record_type'        => $normalized_type,
			'record_id'          => $record_id,
			'identity_period_id' => $identity_period_id,
			'visitor_id'         => $visitor_id,
			'session_id'         => $session_id,
			'occurred_at'        => $occurred_at,
			'data'               => $data,
		);
	}

	/**
	 * Parse identity-period fields.
	 *
	 * @param array    $row         Database row.
	 * @param int      $record_id   Period ID.
	 * @param int|null $session_id  Session ID, which must be null.
	 * @param string   $occurred_at Period start time.
	 * @return array|null Export data, or null for malformed data.
	 * @phpstan-param array<string,mixed> $row
	 * @phpstan-return ExportData|null
	 */
	private function parse_identity_period( array $row, int $record_id, ?int $session_id, string $occurred_at ): ?array {
		$linked_at = $this->optional_timestamp( value: $row['linked_at'] );
		$ended_at  = $this->optional_timestamp( value: $row['record_ended_at'] );

		if ( $record_id !== $this->integer( value: $row['identity_period_id'], minimum: 1 ) || null !== $session_id || false === $linked_at || false === $ended_at ) {
			return null;
		}

		$data = array( 'started_at' => $occurred_at );
		if ( null !== $linked_at ) {
			$data['linked_at'] = $linked_at;
		}
		if ( null !== $ended_at ) {
			$data['ended_at'] = $ended_at;
		}

		return $data;
	}

	/**
	 * Parse session summary and attribution fields.
	 *
	 * @param array    $row         Database row.
	 * @param int      $record_id   Session record ID.
	 * @param int|null $session_id  Session ID.
	 * @param string   $occurred_at Session start time.
	 * @return array|null Export data, or null for malformed data.
	 * @phpstan-param array<string,mixed> $row
	 * @phpstan-return ExportData|null
	 */
	private function parse_session( array $row, int $record_id, ?int $session_id, string $occurred_at ): ?array {
		$authenticated = $this->binary( value: $row['began_authenticated'] );
		$ended_at      = $this->optional_timestamp( value: $row['record_ended_at'] );

		if (
			$record_id !== $session_id ||
			null === $authenticated ||
			! is_string( $row['last_activity_at'] ) ||
			null === $this->timestamp( value: $row['last_activity_at'] ) ||
			false === $ended_at
		) {
			return null;
		}

		$string_fields = array( 'landing_path', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		$strings       = array();
		foreach ( $string_fields as $field ) {
			$value = $row[ $field ];
			if ( null !== $value && ! is_string( $value ) ) {
				return null;
			}
			if ( is_string( $value ) ) {
				$strings[ $field ] = $value;
			}
		}

		$integer_fields = array( 'page_view_count', 'product_view_count', 'cart_add_count', 'cart_remove_count', 'checkout_started_count', 'order_created_count', 'active_ms' );
		$integers       = array();
		foreach ( $integer_fields as $field ) {
			$value = $this->integer( value: $row[ $field ], minimum: 0 );
			if ( null === $value ) {
				return null;
			}
			$integers[ $field ] = $value;
		}

		$added_quantity   = $this->decimal( value: $row['added_quantity'] );
		$removed_quantity = $this->decimal( value: $row['removed_quantity'] );
		if ( null === $added_quantity || null === $removed_quantity ) {
			return null;
		}

		$data = array(
			'began_authenticated'    => $authenticated,
			'started_at'             => $occurred_at,
			'last_activity_at'       => $row['last_activity_at'],
			'page_view_count'        => $integers['page_view_count'],
			'product_view_count'     => $integers['product_view_count'],
			'cart_add_count'         => $integers['cart_add_count'],
			'cart_remove_count'      => $integers['cart_remove_count'],
			'added_quantity'         => $added_quantity,
			'removed_quantity'       => $removed_quantity,
			'checkout_started_count' => $integers['checkout_started_count'],
			'order_created_count'    => $integers['order_created_count'],
			'active_ms'              => $integers['active_ms'],
		);

		if ( null !== $ended_at ) {
			$data['ended_at'] = $ended_at;
		}
		foreach ( $strings as $field => $value ) {
			$data[ $field ] = $value;
		}

		return $data;
	}

	/**
	 * Parse event fields without exposing stored user or idempotency values.
	 *
	 * @param array    $row         Database row.
	 * @param int|null $session_id  Session ID.
	 * @param string   $occurred_at Event time.
	 * @return array|null Export data, or null for malformed data.
	 * @phpstan-param array<string,mixed> $row
	 * @phpstan-return ExportData|null
	 */
	private function parse_event( array $row, ?int $session_id, string $occurred_at ): ?array {
		$authenticated = $this->binary( value: $row['occurred_authenticated'] );
		$active_ms     = $this->integer( value: $row['active_ms'], minimum: 0 );

		if (
			null === $session_id ||
			null === $authenticated ||
			null === $active_ms ||
			! is_string( $row['event_type'] ) ||
			'' === $row['event_type']
		) {
			return null;
		}

		$strings = array();
		foreach ( array( 'page_path', 'source' ) as $field ) {
			$value = $row[ $field ];
			if ( null !== $value && ! is_string( $value ) ) {
				return null;
			}
			if ( is_string( $value ) ) {
				$strings[ $field ] = $value;
			}
		}

		$ids = array();
		foreach ( array( 'post_id', 'product_id', 'variation_id', 'order_id', 'related_object_id' ) as $field ) {
			$value = $this->optional_id( value: $row[ $field ] );
			if ( null !== $row[ $field ] && null === $value ) {
				return null;
			}
			$ids[ $field ] = $value;
		}

		$quantity = null;
		if ( null !== $row['quantity'] ) {
			$quantity = $this->decimal( value: $row['quantity'] );
			if ( null === $quantity ) {
				return null;
			}
		}

		$data = array(
			'occurred_authenticated' => $authenticated,
			'event_type'             => $row['event_type'],
			'occurred_at'            => $occurred_at,
			'active_ms'              => $active_ms,
		);

		foreach ( $strings as $field => $value ) {
			$data[ $field ] = $value;
		}
		if ( null !== $quantity ) {
			$data['quantity'] = $quantity;
		}
		foreach ( $ids as $field => $value ) {
			if ( null !== $value ) {
				$data[ $field ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Parse an optional timestamp while preserving invalid/null distinction.
	 *
	 * @param mixed $value Stored value.
	 * @return string|false|null Timestamp, false when invalid, or null.
	 */
	private function optional_timestamp( mixed $value ): string|false|null {
		if ( null === $value ) {
			return null;
		}

		return is_string( $value ) && null !== $this->timestamp( value: $value ) ? $value : false;
	}

	/**
	 * Parse a nullable positive database identifier.
	 *
	 * @param mixed $value Stored value.
	 * @return int|null Identifier or null.
	 */
	private function optional_id( mixed $value ): ?int {
		return null === $value ? null : $this->integer( value: $value, minimum: 1 );
	}

	/**
	 * Parse a database boolean.
	 *
	 * @param mixed $value Stored value.
	 * @return bool|null Boolean or null when invalid.
	 */
	private function binary( mixed $value ): ?bool {
		if ( 0 === $value || '0' === $value ) {
			return false;
		}
		if ( 1 === $value || '1' === $value ) {
			return true;
		}

		return null;
	}

	/**
	 * Validate a non-negative schema decimal.
	 *
	 * @param mixed $value Stored decimal.
	 * @return string|null Canonical decimal, or null when invalid.
	 */
	private function decimal( mixed $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9][0-9]{0,11})\.[0-9]{4}$/D', $value ) ? $value : null;
	}

	/**
	 * Parse a canonical integer in the platform range.
	 *
	 * @param mixed $value   Stored value.
	 * @param int   $minimum Smallest accepted integer.
	 * @return int|null Integer or null.
	 */
	private function integer( mixed $value, int $minimum ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$parsed = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => $minimum ) ) );
		return false === $parsed ? null : $parsed;
	}

	/**
	 * Parse a real UTC MySQL datetime.
	 *
	 * @param string $value UTC datetime.
	 * @return int|null Unix timestamp or null.
	 */
	private function timestamp( string $value ): ?int {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $value ? $parsed->getTimestamp() : null;
	}
}
