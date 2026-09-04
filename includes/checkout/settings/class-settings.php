<?php
/**
 * Checkout Tools settings.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Checkout\Settings;

/**
 * Provides Checkout Tools configuration.
 */
final class Settings {

	/**
	 * WordPress option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'shurloc_checkout_tools_settings';

	/**
	 * Default mesh tariff percentage.
	 *
	 * @var float
	 */
	private const DEFAULT_MESH_TARIFF_RATE = 3.0;

	/**
	 * Default Sefar tariff percentage.
	 *
	 * @var float
	 */
	private const DEFAULT_SEFAR_TARIFF_RATE = 9.0;

	/**
	 * Default mesh tariff message.
	 *
	 * @var string
	 */
	private const DEFAULT_MESH_TARIFF_MESSAGE = 'Due to a 6% tariff from our suppliers, all mesh orders will include a 3% tariff fee as a separate line item on invoices. We\'re sharing this cost to minimize impact and will adjust if tariff conditions change. Thank you for your understanding.';

	/**
	 * Default Sefar tariff message.
	 *
	 * @var string
	 */
	private const DEFAULT_SEFAR_TARIFF_MESSAGE = 'Due to a 12% mesh tariff from Sefar, mesh orders will include a 9% tariff fee as a separate line item on invoices. Shur-loc pays 3% of this tariff based on paying half of 6% for both Murakami and Saati sharing this cost to minimize industry impact and Shur-loc will adjust if tariff conditions change. Thank you for your understanding.';

	/**
	 * Gets whether the mesh tariff is enabled.
	 *
	 * @return bool Whether the mesh tariff is enabled.
	 */
	public function is_mesh_tariff_enabled(): bool {
		$settings = $this->get_settings();

		return $settings['tariffs']['mesh']['enabled'];
	}

	/**
	 * Gets the mesh tariff rate.
	 *
	 * @return float Mesh tariff rate as a decimal.
	 */
	public function get_mesh_tariff_rate(): float {
		$settings = $this->get_settings();

		return $settings['tariffs']['mesh']['rate'] / 100;
	}

	/**
	 * Gets the mesh tariff message.
	 *
	 * @return string Mesh tariff message.
	 */
	public function get_mesh_tariff_message(): string {
		$settings = $this->get_settings();

		return $settings['tariffs']['mesh']['message'];
	}

	/**
	 * Gets whether the Sefar tariff is enabled.
	 *
	 * @return bool Whether the Sefar tariff is enabled.
	 */
	public function is_sefar_tariff_enabled(): bool {
		$settings = $this->get_settings();

		return $settings['tariffs']['sefar']['enabled'];
	}

	/**
	 * Gets the Sefar tariff rate.
	 *
	 * @return float Sefar tariff rate as a decimal.
	 */
	public function get_sefar_tariff_rate(): float {
		$settings = $this->get_settings();

		return $settings['tariffs']['sefar']['rate'] / 100;
	}

	/**
	 * Gets the Sefar tariff message.
	 *
	 * @return string Sefar tariff message.
	 */
	public function get_sefar_tariff_message(): string {
		$settings = $this->get_settings();

		return $settings['tariffs']['sefar']['message'];
	}

	/**
	 * Gets normalized Checkout Tools settings.
	 *
	 * Tariff rates are stored as percentages.
	 *
	 * @return array{
	 *     tariffs: array{
	 *         mesh: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         },
	 *         sefar: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         }
	 *     }
	 * } Normalized settings.
	 */
	public function get_settings(): array {
		$settings = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $this->normalize_settings(
			settings: $settings
		);
	}

	/**
	 * Gets the default Checkout Tools settings.
	 *
	 * Tariff rates are stored as percentages.
	 *
	 * @return array{
	 *     tariffs: array{
	 *         mesh: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         },
	 *         sefar: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         }
	 *     }
	 * } Default settings.
	 */
	public function get_defaults(): array {
		return array(
			'tariffs' => array(
				'mesh'  => array(
					'enabled' => true,
					'rate'    => self::DEFAULT_MESH_TARIFF_RATE,
					'message' => self::DEFAULT_MESH_TARIFF_MESSAGE,
				),
				'sefar' => array(
					'enabled' => true,
					'rate'    => self::DEFAULT_SEFAR_TARIFF_RATE,
					'message' => self::DEFAULT_SEFAR_TARIFF_MESSAGE,
				),
			),
		);
	}

	/**
	 * Normalizes stored settings against the defaults.
	 *
	 * Tariff rates are stored as percentages.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array{
	 *     tariffs: array{
	 *         mesh: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         },
	 *         sefar: array{
	 *             enabled: bool,
	 *             rate: float,
	 *             message: string
	 *         }
	 *     }
	 * } Normalized settings.
	 */
	private function normalize_settings(
		array $settings
	): array {
		$defaults = $this->get_defaults();

		$mesh_settings = $this->get_tariff_settings(
			settings: $settings,
			tariff_type: 'mesh'
		);

		$sefar_settings = $this->get_tariff_settings(
			settings: $settings,
			tariff_type: 'sefar'
		);

		return array(
			'tariffs' => array(
				'mesh'  => array(
					'enabled' => isset( $mesh_settings['enabled'] )
						? (bool) $mesh_settings['enabled']
						: $defaults['tariffs']['mesh']['enabled'],
					'rate'    => isset( $mesh_settings['rate'] ) && is_numeric( $mesh_settings['rate'] )
						? (float) $mesh_settings['rate']
						: $defaults['tariffs']['mesh']['rate'],
					'message' => isset( $mesh_settings['message'] ) && is_string( $mesh_settings['message'] )
						? $mesh_settings['message']
						: $defaults['tariffs']['mesh']['message'],
				),
				'sefar' => array(
					'enabled' => isset( $sefar_settings['enabled'] )
						? (bool) $sefar_settings['enabled']
						: $defaults['tariffs']['sefar']['enabled'],
					'rate'    => isset( $sefar_settings['rate'] ) && is_numeric( $sefar_settings['rate'] )
						? (float) $sefar_settings['rate']
						: $defaults['tariffs']['sefar']['rate'],
					'message' => isset( $sefar_settings['message'] ) && is_string( $sefar_settings['message'] )
						? $sefar_settings['message']
						: $defaults['tariffs']['sefar']['message'],
				),
			),
		);
	}

	/**
	 * Gets stored settings for a tariff type.
	 *
	 * @param array<string, mixed> $settings    Stored settings.
	 * @param string               $tariff_type Tariff type.
	 * @return array<string, mixed> Stored tariff settings.
	 */
	private function get_tariff_settings(
		array $settings,
		string $tariff_type
	): array {
		if (
			! isset( $settings['tariffs'] ) ||
			! is_array( $settings['tariffs'] ) ||
			! isset( $settings['tariffs'][ $tariff_type ] ) ||
			! is_array( $settings['tariffs'][ $tariff_type ] )
		) {
			return array();
		}

		return $settings['tariffs'][ $tariff_type ];
	}
}
