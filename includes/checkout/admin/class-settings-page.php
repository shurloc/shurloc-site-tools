<?php
/**
 * Checkout Tools settings page.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Admin;

use Shurloc\SiteTools\Checkout\Settings\Settings;

/**
 * Registers and renders the Checkout Tools settings page.
 */
final class Settings_Page {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'shurloc-site-tools-checkout';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'shurloc_checkout_tools';

	/**
	 * Tariff settings section ID.
	 *
	 * @var string
	 */
	private const TARIFF_SECTION_ID = 'shurloc_checkout_tools_tariffs';

	/**
	 * Checkout Tools settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the settings page.
	 *
	 * @param Settings $settings Checkout Tools settings.
	 */
	public function __construct(
		Settings $settings
	) {
		$this->settings = $settings;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'register_settings' )
		);
	}

	/**
	 * Registers settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->settings->get_defaults(),
			)
		);

		add_settings_section(
			self::TARIFF_SECTION_ID,
			'Tariff Fees',
			array( $this, 'render_tariff_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'mesh_tariff_enabled',
			'Raw Material Import Tariff',
			array( $this, 'render_mesh_enabled_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);

		add_settings_field(
			'mesh_tariff_rate',
			'Raw Material Tariff Rate',
			array( $this, 'render_mesh_rate_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);

		add_settings_field(
			'mesh_tariff_message',
			'Raw Material Tariff Message',
			array( $this, 'render_mesh_message_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);

		add_settings_field(
			'sefar_tariff_enabled',
			'Sefar Mesh Tariff',
			array( $this, 'render_sefar_enabled_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);

		add_settings_field(
			'sefar_tariff_rate',
			'Sefar Tariff Rate',
			array( $this, 'render_sefar_rate_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);

		add_settings_field(
			'sefar_tariff_message',
			'Sefar Tariff Message',
			array( $this, 'render_sefar_message_field' ),
			self::PAGE_SLUG,
			self::TARIFF_SECTION_ID
		);
	}

	/**
	 * Sanitizes submitted Checkout Tools settings.
	 *
	 * Rates are submitted and stored as percentages.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings(
		mixed $input
	): array {
		$defaults = $this->settings->get_defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$tariffs = isset( $input['tariffs'] ) && is_array( $input['tariffs'] )
			? $input['tariffs']
			: array();

		$mesh = isset( $tariffs['mesh'] ) && is_array( $tariffs['mesh'] )
			? $tariffs['mesh']
			: array();

		$sefar = isset( $tariffs['sefar'] ) && is_array( $tariffs['sefar'] )
			? $tariffs['sefar']
			: array();

		return array(
			'tariffs' => array(
				'mesh'  => array(
					'enabled' => ! empty( $mesh['enabled'] ),
					'rate'    => $this->sanitize_rate(
						value: $mesh['rate'] ?? null,
						default_rate: $defaults['tariffs']['mesh']['rate']
					),
					'message' => $this->sanitize_message(
						value: $mesh['message'] ?? null,
						default_message: $defaults['tariffs']['mesh']['message']
					),
				),
				'sefar' => array(
					'enabled' => ! empty( $sefar['enabled'] ),
					'rate'    => $this->sanitize_rate(
						value: $sefar['rate'] ?? null,
						default_rate: $defaults['tariffs']['sefar']['rate']
					),
					'message' => $this->sanitize_message(
						value: $sefar['message'] ?? null,
						default_message: $defaults['tariffs']['sefar']['message']
					),
				),
			),
		);
	}

	/**
	 * Renders the Checkout Tools settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Checkout Tools</h1>

			<?php $this->render_tariff_fees_tab(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the tariff fees tab content.
	 *
	 * @return void
	 */
	public function render_tariff_fees_tab(): void {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( self::SETTINGS_GROUP );
			do_settings_sections( self::PAGE_SLUG );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Renders the tariff settings section description.
	 *
	 * @return void
	 */
	public function render_tariff_section(): void {
		?>
		<p>
			Configure the tariff fees and customer-facing messages shown in the cart and checkout.
		</p>
		<?php
	}

	/**
	 * Renders the mesh tariff enabled field.
	 *
	 * @return void
	 */
	public function render_mesh_enabled_field(): void {
		?>
		<label>
			<input
				type="hidden"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][mesh][enabled]"
				value="0"
			>
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][mesh][enabled]"
				value="1"
				<?php checked( $this->settings->is_mesh_tariff_enabled() ); ?>
			>
			Enable the raw material import tariff
		</label>
		<?php
	}

	/**
	 * Renders the mesh tariff rate field.
	 *
	 * @return void
	 */
	public function render_mesh_rate_field(): void {
		$settings = $this->settings->get_settings();

		$this->render_rate_field(
			tariff_type: 'mesh',
			rate: $settings['tariffs']['mesh']['rate']
		);
	}

	/**
	 * Renders the mesh tariff message field.
	 *
	 * @return void
	 */
	public function render_mesh_message_field(): void {
		$this->render_message_field(
			tariff_type: 'mesh',
			message: $this->settings->get_mesh_tariff_message()
		);
	}

	/**
	 * Renders the Sefar tariff enabled field.
	 *
	 * @return void
	 */
	public function render_sefar_enabled_field(): void {
		?>
		<label>
			<input
				type="hidden"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][sefar][enabled]"
				value="0"
			>
			<input
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][sefar][enabled]"
				value="1"
				<?php checked( $this->settings->is_sefar_tariff_enabled() ); ?>
			>
			Enable the Sefar mesh tariff
		</label>
		<?php
	}

	/**
	 * Renders the Sefar tariff rate field.
	 *
	 * @return void
	 */
	public function render_sefar_rate_field(): void {
		$settings = $this->settings->get_settings();

		$this->render_rate_field(
			tariff_type: 'sefar',
			rate: $settings['tariffs']['sefar']['rate']
		);
	}

	/**
	 * Renders the Sefar tariff message field.
	 *
	 * @return void
	 */
	public function render_sefar_message_field(): void {
		$this->render_message_field(
			tariff_type: 'sefar',
			message: $this->settings->get_sefar_tariff_message()
		);
	}

	/**
	 * Renders a tariff rate field.
	 *
	 * @param string $tariff_type Tariff type.
	 * @param float  $rate        Tariff percentage.
	 * @return void
	 */
	private function render_rate_field(
		string $tariff_type,
		float $rate
	): void {
		?>
		<input
			type="number"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][<?php echo esc_attr( $tariff_type ); ?>][rate]"
			value="<?php echo esc_attr( number_format( $rate, 2, '.', '' ) ); ?>"
			min="0"
			max="100"
			step="0.01"
			class="small-text"
		>
		%
		<?php
	}

	/**
	 * Renders a tariff message field.
	 *
	 * @param string $tariff_type Tariff type.
	 * @param string $message     Tariff message.
	 * @return void
	 */
	private function render_message_field(
		string $tariff_type,
		string $message
	): void {
		?>
		<textarea
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[tariffs][<?php echo esc_attr( $tariff_type ); ?>][message]"
			rows="6"
			class="large-text"
		><?php echo esc_textarea( $message ); ?></textarea>
		<?php
	}

	/**
	 * Sanitizes a submitted tariff rate.
	 *
	 * Submitted values are percentages between 0 and 100.
	 *
	 * @param mixed $value        Submitted percentage.
	 * @param float $default_rate Default percentage.
	 * @return float Sanitized tariff percentage.
	 */
	private function sanitize_rate(
		mixed $value,
		float $default_rate
	): float {
		if ( ! is_numeric( $value ) ) {
			return $default_rate;
		}

		$percentage = (float) $value;

		if ( 0 > $percentage || 100 < $percentage ) {
			return $default_rate;
		}

		return $percentage;
	}

	/**
	 * Sanitizes a submitted tariff message.
	 *
	 * @param mixed  $value           Submitted message.
	 * @param string $default_message Default message.
	 * @return string Sanitized tariff message.
	 */
	private function sanitize_message(
		mixed $value,
		string $default_message
	): string {
		if ( ! is_string( $value ) ) {
			return $default_message;
		}

		return sanitize_textarea_field( $value );
	}
}
