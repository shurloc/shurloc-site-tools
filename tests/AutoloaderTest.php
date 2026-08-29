<?php
/**
 * Tests for the plugin autoloader.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Tests for Autoloader.
 */
final class AutoloaderTest extends TestCase {

	/**
	 * Temporary base directory used by the test autoloader.
	 *
	 * @var string
	 */
	private string $base_directory;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->base_directory = sys_get_temp_dir() . '/shurloc-site-tools-autoloader-' . uniqid();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $this->base_directory, 0777, true );
	}

	/**
	 * Clean up the test fixture.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->delete_directory(
			directory: $this->base_directory,
		);

		parent::tearDown();
	}

	/**
	 * Test that the constructor rejects a missing base directory.
	 *
	 * @return void
	 */
	public function test_constructor_rejects_missing_base_directory(): void {
		$missing_directory = $this->base_directory . '/missing';

		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Autoloader directory does not exist.' );

		new Autoloader(
			base_directory: $missing_directory,
		);
	}

	/**
	 * Test that a root namespace class is loaded.
	 *
	 * @return void
	 */
	public function test_loads_root_namespace_class(): void {
		$this->write_php_file(
			path: $this->base_directory . '/class-autoloader-root-test.php',
			contents: <<<'PHP'
<?php
namespace Shurloc\SiteTools;
final class Autoloader_Root_Test {}
PHP,
		);

		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'Shurloc\\SiteTools\\Autoloader_Root_Test',
		);

		$this->assertTrue(
			class_exists( 'Shurloc\\SiteTools\\Autoloader_Root_Test', false )
		);
	}

	/**
	 * Test that namespace segments map to nested directories.
	 *
	 * @return void
	 */
	public function test_loads_class_from_nested_namespace_directory(): void {
		$this->write_php_file(
			path: $this->base_directory . '/media/services/class-autoloader-nested-test.php',
			contents: <<<'PHP'
<?php
namespace Shurloc\SiteTools\Media\Services;
final class Autoloader_Nested_Test {}
PHP,
		);

		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'Shurloc\\SiteTools\\Media\\Services\\Autoloader_Nested_Test',
		);

		$this->assertTrue(
			class_exists(
				'Shurloc\\SiteTools\\Media\\Services\\Autoloader_Nested_Test',
				false
			)
		);
	}

	/**
	 * Test that interface names map to interface filenames.
	 *
	 * @return void
	 */
	public function test_loads_interface(): void {
		$this->write_php_file(
			path: $this->base_directory . '/shared/interfaces/interface-autoloader-test.php',
			contents: <<<'PHP'
<?php
namespace Shurloc\SiteTools\Shared\Interfaces;
interface Autoloader_Test_Interface {}
PHP,
		);

		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'Shurloc\\SiteTools\\Shared\\Interfaces\\Autoloader_Test_Interface',
		);

		$this->assertTrue(
			interface_exists(
				'Shurloc\\SiteTools\\Shared\\Interfaces\\Autoloader_Test_Interface',
				false
			)
		);
	}

	/**
	 * Test that trait names map to trait filenames.
	 *
	 * @return void
	 */
	public function test_loads_trait(): void {
		$this->write_php_file(
			path: $this->base_directory . '/shared/traits/trait-autoloader-test.php',
			contents: <<<'PHP'
<?php
namespace Shurloc\SiteTools\Shared\Traits;
trait Autoloader_Test_Trait {}
PHP,
		);

		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'Shurloc\\SiteTools\\Shared\\Traits\\Autoloader_Test_Trait',
		);

		$this->assertTrue(
			trait_exists(
				'Shurloc\\SiteTools\\Shared\\Traits\\Autoloader_Test_Trait',
				false
			)
		);
	}

	/**
	 * Test that classes outside the plugin namespace are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_classes_outside_plugin_namespace(): void {
		$this->write_php_file(
			path: $this->base_directory . '/class-autoloader-foreign-test.php',
			contents: <<<'PHP'
<?php
namespace OtherVendor;
final class Autoloader_Foreign_Test {}
PHP,
		);

		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'OtherVendor\\Autoloader_Foreign_Test',
		);

		$this->assertFalse(
			class_exists( 'OtherVendor\\Autoloader_Foreign_Test', false )
		);
	}

	/**
	 * Test that a missing class file is ignored without error.
	 *
	 * @return void
	 */
	public function test_ignores_missing_class_file(): void {
		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->load(
			class_name: 'Shurloc\\SiteTools\\Missing_Class',
		);

		$this->assertFalse(
			class_exists( 'Shurloc\\SiteTools\\Missing_Class', false )
		);
	}

	/**
	 * Test that register adds the load method to the SPL autoload stack.
	 *
	 * @return void
	 */
	public function test_register_adds_autoloader(): void {
		$autoloader = new Autoloader(
			base_directory: $this->base_directory,
		);

		$autoloader->register();

		$autoloaders = spl_autoload_functions();

		$this->assertContains(
			array(
				$autoloader,
				'load',
			),
			$autoloaders
		);

		spl_autoload_unregister(
			array(
				$autoloader,
				'load',
			)
		);
	}

	/**
	 * Write a PHP fixture file, creating its directory when needed.
	 *
	 * @param string $path     File path.
	 * @param string $contents File contents.
	 * @return void
	 */
	private function write_php_file(
		string $path,
		string $contents
	): void {
		$directory = dirname( $path );

		if ( ! is_dir( $directory ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			mkdir( $directory, 0777, true );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $contents );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $directory Directory to delete.
	 * @return void
	 */
	private function delete_directory(
		string $directory
	): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );

		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . '/' . $item;

			if ( is_dir( $path ) ) {
				$this->delete_directory(
					directory: $path,
				);

				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $path );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $directory );
	}
}
