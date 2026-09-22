<?php
/**
 * Customer Journey WordPress personal-data exporter.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

defined( 'ABSPATH' ) || exit;

use Shurloc\SiteTools\Customer\Journey\Repositories\Journey_Privacy_Export_Repository;
use WP_User;

/**
 * Registers and serves the Journey personal-data exporter callback.
 *
 * @phpstan-import-type ExportRecord from Journey_Privacy_Export_Repository
 * @phpstan-type ExportDatum array{name:string,value:string}
 * @phpstan-type ExportItem array{
 *     group_id:string,
 *     group_label:string,
 *     item_id:string,
 *     data:list<ExportDatum>
 * }
 * @phpstan-type ExportResult array{data:list<ExportItem>,done:bool}
 */
final class Journey_Privacy_Exporter {
	/** Key used in WordPress's personal-data exporter registry. */
	public const EXPORTER_KEY = 'shurloc-site-tools-customer-journey';

	/** WordPress export group identifier. */
	public const GROUP_ID = 'shurloc-customer-journey';

	/** Filter controlling the number of Journey records exported per request. */
	public const PAGE_SIZE_FILTER = 'shurloc_site_tools_journey_privacy_export_page_size';

	/** Default number of Journey records exported per request. */
	public const DEFAULT_PAGE_SIZE = 50;

	/**
	 * Bounded export storage.
	 *
	 * @var Journey_Privacy_Export_Repository
	 */
	private Journey_Privacy_Export_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Journey_Privacy_Export_Repository|null $repository Export storage.
	 */
	public function __construct( ?Journey_Privacy_Export_Repository $repository = null ) {
		$this->repository = $repository ?? new Journey_Privacy_Export_Repository();
	}

	/**
	 * Register the Journey exporter with WordPress privacy tools.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			array( $this, 'register_exporter' )
		);
	}

	/**
	 * Add the Journey exporter without replacing another plugin's callbacks.
	 *
	 * @param array $exporters Registered personal-data exporters.
	 * @return array Registered personal-data exporters.
	 * @phpstan-param array<string,array{exporter_friendly_name:string,callback:callable}> $exporters
	 * @phpstan-return array<string,array{exporter_friendly_name:string,callback:callable}>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[ self::EXPORTER_KEY ] = array(
			'exporter_friendly_name' => __( 'Customer Journey data', 'shurloc-site-tools' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Export one bounded page for the account matching an email address.
	 *
	 * Journey stores no email address, so an account must still exist for the
	 * server to resolve the privacy request to its stored WordPress user ID.
	 *
	 * @param string $email_address Confirmed privacy-request email address.
	 * @param int    $page          WordPress exporter page, starting at one.
	 * @return array WordPress personal-data exporter result.
	 * @phpstan-return ExportResult
	 */
	public function export( string $email_address, int $page = 1 ): array {
		if ( 1 > $page ) {
			return $this->failure_result();
		}

		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User || 1 > $user->ID ) {
			return $this->result( done: true );
		}

		$export_page = $this->repository->get_user_page(
			user_id: $user->ID,
			page: $page,
			page_size: $this->page_size(),
		);

		if ( null === $export_page ) {
			return $this->failure_result();
		}

		$items = array();
		foreach ( $export_page['records'] as $record ) {
			$items[] = $this->export_item( record: $record );
		}

		return $this->result(
			data: $items,
			done: ! $export_page['has_more'],
		);
	}

	/**
	 * Return a safe exporter page size.
	 *
	 * @return int Positive bounded page size.
	 */
	private function page_size(): int {
		$page_size = apply_filters( self::PAGE_SIZE_FILTER, self::DEFAULT_PAGE_SIZE );

		return is_int( $page_size ) &&
			1 <= $page_size &&
			Journey_Privacy_Export_Repository::MAX_PAGE_SIZE >= $page_size
			? $page_size
			: self::DEFAULT_PAGE_SIZE;
	}

	/**
	 * Convert one normalized Journey record to a WordPress export item.
	 *
	 * @param array $record Normalized repository record.
	 * @return array WordPress export item.
	 * @phpstan-param ExportRecord $record
	 * @phpstan-return ExportItem
	 */
	private function export_item( array $record ): array {
		$data = array(
			$this->datum(
				name: __( 'Record type', 'shurloc-site-tools' ),
				value: $this->record_type_label( record_type: $record['record_type'] )
			),
			$this->datum(
				name: __( 'Device reference', 'shurloc-site-tools' ),
				value: __( 'Device', 'shurloc-site-tools' ) . ' ' . $record['visitor_id']
			),
			$this->datum(
				name: __( 'Identity period reference', 'shurloc-site-tools' ),
				value: __( 'Identity period', 'shurloc-site-tools' ) . ' ' . $record['identity_period_id']
			),
		);

		if ( null !== $record['session_id'] ) {
			$data[] = $this->datum(
				name: __( 'Session reference', 'shurloc-site-tools' ),
				value: __( 'Session', 'shurloc-site-tools' ) . ' ' . $record['session_id']
			);
		}

		foreach ( $record['data'] as $field => $value ) {
			$label = $this->field_label( field: $field );
			if ( null === $label ) {
				continue;
			}

			$data[] = $this->datum(
				name: $label,
				value: $this->field_value( field: $field, value: $value )
			);
		}

		return array(
			'group_id'    => self::GROUP_ID,
			'group_label' => __( 'Customer Journey', 'shurloc-site-tools' ),
			'item_id'     => 'journey-' . str_replace( '_', '-', $record['record_type'] ) . '-' . $record['record_id'],
			'data'        => $data,
		);
	}

	/**
	 * Build a WordPress export name/value pair.
	 *
	 * @param string $name  Field label.
	 * @param string $value Exported value.
	 * @return array Export datum.
	 * @phpstan-return ExportDatum
	 */
	private function datum( string $name, string $value ): array {
		return array(
			'name'  => $name,
			'value' => $value,
		);
	}

	/**
	 * Return a readable record-type label.
	 *
	 * @param string $record_type Repository record type.
	 * @return string Label.
	 */
	private function record_type_label( string $record_type ): string {
		return match ( $record_type ) {
			Journey_Privacy_Export_Repository::IDENTITY_PERIOD => __( 'Identity period', 'shurloc-site-tools' ),
			Journey_Privacy_Export_Repository::SESSION => __( 'Session', 'shurloc-site-tools' ),
			Journey_Privacy_Export_Repository::EVENT => __( 'Event', 'shurloc-site-tools' ),
			default => $record_type,
		};
	}

	/**
	 * Return a readable label for an allowlisted repository field.
	 *
	 * @param string $field Repository field.
	 * @return string|null Label, or null for a future unsupported field.
	 */
	private function field_label( string $field ): ?string {
		return match ( $field ) {
			'started_at'             => __( 'Started at (UTC)', 'shurloc-site-tools' ),
			'linked_at'              => __( 'Linked to account at (UTC)', 'shurloc-site-tools' ),
			'ended_at'               => __( 'Ended at (UTC)', 'shurloc-site-tools' ),
			'began_authenticated'    => __( 'Began authenticated', 'shurloc-site-tools' ),
			'last_activity_at'       => __( 'Last activity at (UTC)', 'shurloc-site-tools' ),
			'landing_path'           => __( 'Landing path', 'shurloc-site-tools' ),
			'referrer_host'          => __( 'Referrer host', 'shurloc-site-tools' ),
			'utm_source'             => __( 'UTM source', 'shurloc-site-tools' ),
			'utm_medium'             => __( 'UTM medium', 'shurloc-site-tools' ),
			'utm_campaign'           => __( 'UTM campaign', 'shurloc-site-tools' ),
			'utm_term'               => __( 'UTM term', 'shurloc-site-tools' ),
			'utm_content'            => __( 'UTM content', 'shurloc-site-tools' ),
			'page_view_count'        => __( 'Page views', 'shurloc-site-tools' ),
			'product_view_count'     => __( 'Product views', 'shurloc-site-tools' ),
			'cart_add_count'         => __( 'Cart additions', 'shurloc-site-tools' ),
			'cart_remove_count'      => __( 'Cart removals', 'shurloc-site-tools' ),
			'added_quantity'         => __( 'Quantity added', 'shurloc-site-tools' ),
			'removed_quantity'       => __( 'Quantity removed', 'shurloc-site-tools' ),
			'checkout_started_count' => __( 'Checkouts started', 'shurloc-site-tools' ),
			'order_created_count'    => __( 'Orders created', 'shurloc-site-tools' ),
			'active_ms'              => __( 'Estimated active viewing time', 'shurloc-site-tools' ),
			'occurred_authenticated' => __( 'Occurred while authenticated', 'shurloc-site-tools' ),
			'event_type'             => __( 'Event', 'shurloc-site-tools' ),
			'occurred_at'            => __( 'Occurred at (UTC)', 'shurloc-site-tools' ),
			'page_path'              => __( 'Page path', 'shurloc-site-tools' ),
			'post_id'                => __( 'Post ID', 'shurloc-site-tools' ),
			'product_id'             => __( 'Product ID', 'shurloc-site-tools' ),
			'variation_id'           => __( 'Variation ID', 'shurloc-site-tools' ),
			'quantity'               => __( 'Quantity', 'shurloc-site-tools' ),
			'order_id'               => __( 'Order ID', 'shurloc-site-tools' ),
			'related_object_id'      => __( 'Related object ID', 'shurloc-site-tools' ),
			'source'                 => __( 'Source', 'shurloc-site-tools' ),
			default                  => null,
		};
	}

	/**
	 * Format one allowlisted field value for the WordPress export.
	 *
	 * @param string          $field Repository field.
	 * @param bool|int|string $value Repository value.
	 * @return string Export value.
	 */
	private function field_value( string $field, bool|int|string $value ): string {
		if ( is_bool( $value ) ) {
			return $value
				? __( 'Yes', 'shurloc-site-tools' )
				: __( 'No', 'shurloc-site-tools' );
		}

		if ( 'active_ms' === $field ) {
			return (string) $value . ' ms';
		}

		if ( 'event_type' === $field && is_string( $value ) ) {
			return $this->event_type_label( event_type: $value );
		}

		return (string) $value;
	}

	/**
	 * Return a readable v1 event label while preserving future stored types.
	 *
	 * @param string $event_type Stored event type.
	 * @return string Label.
	 */
	private function event_type_label( string $event_type ): string {
		return match ( $event_type ) {
			Journey_Event_Type::PAGE_VIEW       => __( 'Page viewed', 'shurloc-site-tools' ),
			Journey_Event_Type::PRODUCT_VIEW    => __( 'Product viewed', 'shurloc-site-tools' ),
			Journey_Event_Type::ADD_TO_CART     => __( 'Added to cart', 'shurloc-site-tools' ),
			Journey_Event_Type::REMOVE_FROM_CART => __( 'Removed from cart', 'shurloc-site-tools' ),
			Journey_Event_Type::CHECKOUT_STARTED => __( 'Checkout started', 'shurloc-site-tools' ),
			Journey_Event_Type::ORDER_CREATED   => __( 'Order created', 'shurloc-site-tools' ),
			default                             => $event_type,
		};
	}

	/**
	 * Build a WordPress exporter result.
	 *
	 * @param array $data Export items.
	 * @param bool  $done Whether export is complete.
	 * @return array WordPress exporter result.
	 * @phpstan-param list<ExportItem> $data
	 * @phpstan-return ExportResult
	 */
	private function result( array $data = array(), bool $done = false ): array {
		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Report an incomplete Journey export inside WordPress's data result.
	 *
	 * @return array WordPress exporter result.
	 * @phpstan-return ExportResult
	 */
	private function failure_result(): array {
		return $this->result(
			data: array(
				array(
					'group_id'    => self::GROUP_ID,
					'group_label' => __( 'Customer Journey', 'shurloc-site-tools' ),
					'item_id'     => 'journey-export-error',
					'data'        => array(
						$this->datum(
							name: __( 'Export status', 'shurloc-site-tools' ),
							value: __( 'Customer Journey data could not be exported.', 'shurloc-site-tools' )
						),
					),
				),
			),
			done: true,
		);
	}
}
