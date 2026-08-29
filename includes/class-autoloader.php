<?php
/**
 * Plugin autoloader.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

use UnexpectedValueException;

/**
 * Plugin autoloader.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 */
	private const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Base includes directory.
	 *
	 * @var string
	 */
	private string $base_directory;

	/**
	 * Constructor.
	 *
	 * @param string $base_directory Base includes directory.
	 * @throws UnexpectedValueException When the base directory does not exist.
	 */
	public function __construct(
		string $base_directory
	) {
		if ( ! is_dir( $base_directory ) ) {
			throw new UnexpectedValueException(
				'Autoloader directory does not exist.'
			);
		}

		$this->base_directory = rtrim(
			$base_directory,
			'/\\'
		);
	}

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public function register(): void {
		spl_autoload_register(
			array(
				$this,
				'load',
			)
		);
	}

	/**
	 * Load a class, interface, or trait.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public function load(
		string $class_name
	): void {
		if ( ! str_starts_with( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_name = substr(
			$class_name,
			strlen( self::NAMESPACE_PREFIX )
		);

		$parts      = explode( '\\', $relative_name );
		$short_name = array_pop( $parts );

		$directory = $this->base_directory;

		if ( array() !== $parts ) {
			$directory .= '/' . strtolower(
				implode( '/', $parts )
			);
		}

		$file = $directory . '/' . $this->class_to_filename(
			class_name: $short_name,
		);

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Convert a class name to a WordPress-style filename.
	 *
	 * Examples:
	 *
	 * Product_Service
	 * -> class-product-service.php
	 *
	 * Product_Service_Interface
	 * -> interface-product-service.php
	 *
	 * Product_Trait
	 * -> trait-product.php
	 *
	 * @param string $class_name Short class name.
	 * @return string Filename.
	 */
	private function class_to_filename(
		string $class_name
	): string {
		$lower = strtolower(
			str_replace(
				'_',
				'-',
				$class_name
			)
		);

		if ( str_ends_with( $lower, '-interface' ) ) {
			return 'interface-' . substr( $lower, 0, -10 ) . '.php';
		}

		if ( str_ends_with( $lower, '-trait' ) ) {
			return 'trait-' . substr( $lower, 0, -6 ) . '.php';
		}

		return 'class-' . $lower . '.php';
	}
}
