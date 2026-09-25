<?php
/**
 * Tests for Customer Journey user-agent classification.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests bounded inspection values and normalized client metadata.
 */
final class JourneyUserAgentClassifierTest extends TestCase {
	/**
	 * Classifier under test.
	 *
	 * @var Journey_User_Agent_Classifier
	 */
	private Journey_User_Agent_Classifier $classifier;

	/**
	 * Reset filters before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();
		$this->classifier                        = new Journey_User_Agent_Classifier();
	}

	/**
	 * Restore shared filters after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['shurloc_test_filters']         = array();
		$GLOBALS['shurloc_test_filter_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Missing and empty headers produce an explicit unknown classification.
	 *
	 * @return void
	 */
	public function test_empty_headers_are_classified_as_unknown(): void {
		$expected = array(
			'user_agent'             => null,
			'client_type'            => 'unknown',
			'client_name'            => null,
			'device_type'            => 'unknown',
			'classification_version' => Journey_User_Agent_Classifier::CLASSIFICATION_VERSION,
		);

		self::assertSame( $expected, $this->classifier->classify( null ) );
		self::assertSame( $expected, $this->classifier->classify( " \t\r\n\x00\x7F " ) );
	}

	/**
	 * Stored inspection values exclude control characters and fit the schema.
	 *
	 * @return void
	 */
	public function test_raw_header_is_safely_bounded_for_inspection(): void {
		$result = $this->classifier->classify( "\x00  Custom\tAgent/1.0\x7F  " );

		self::assertSame( 'CustomAgent/1.0', $result['user_agent'] );
		self::assertSame( 'unknown', $result['client_type'] );

		$long_result = $this->classifier->classify( str_repeat( 'A', 1100 ) );
		self::assertIsString( $long_result['user_agent'] );
		self::assertSame( 1024, strlen( $long_result['user_agent'] ) );
	}

	/**
	 * Common browser compatibility tokens resolve in the required order.
	 *
	 * @return void
	 */
	public function test_common_browsers_and_devices_are_normalized(): void {
		$cases = array(
			array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36',
				'Chrome',
				'desktop',
			),
			array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0',
				'Edge',
				'desktop',
			),
			array(
				'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
				'Safari',
				'mobile',
			),
			array(
				'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
				'Safari',
				'tablet',
			),
			array(
				'Mozilla/5.0 (Linux; Android 14; Pixel Tablet) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36',
				'Chrome',
				'tablet',
			),
		);

		foreach ( $cases as $case ) {
			$result = $this->classifier->classify( $case[0] );

			self::assertSame( 'browser', $result['client_type'] );
			self::assertSame( $case[1], $result['client_name'] );
			self::assertSame( $case[2], $result['device_type'] );
		}
	}

	/**
	 * Crawlers win over browser tokens included for compatibility.
	 *
	 * @return void
	 */
	public function test_common_and_generic_crawlers_are_normalized(): void {
		$cases = array(
			array( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot' ),
			array( 'Mozilla/5.0 AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36 bingbot/2.0', 'Bingbot' ),
			array( 'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'DuckDuckBot' ),
			array( 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Facebook crawler' ),
			array( 'Mozilla/5.0 HeadlessChrome/140.0.0.0 Safari/537.36', 'Headless Chrome' ),
			array( 'ExampleSpider/1.0', 'Crawler' ),
		);

		foreach ( $cases as $case ) {
			$result = $this->classifier->classify( $case[0] );

			self::assertSame( 'crawler', $result['client_type'] );
			self::assertSame( $case[1], $result['client_name'] );
			self::assertSame( 'unknown', $result['device_type'] );
		}
	}

	/**
	 * Command-line clients remain distinct from search crawlers.
	 *
	 * @return void
	 */
	public function test_http_clients_are_normalized(): void {
		$cases = array(
			array( 'curl/8.15.0', 'curl' ),
			array( 'Wget/1.25.0', 'Wget' ),
			array( 'python-requests/2.32.5', 'Python Requests' ),
			array( 'Go-http-client/2.0', 'Go HTTP Client' ),
			array( 'okhttp/5.1.0', 'OkHttp' ),
		);

		foreach ( $cases as $case ) {
			$result = $this->classifier->classify( $case[0] );

			self::assertSame( 'http_client', $result['client_type'] );
			self::assertSame( $case[1], $result['client_name'] );
			self::assertSame( 'unknown', $result['device_type'] );
		}
	}

	/**
	 * Unknown clients retain their bounded header for later inspection.
	 *
	 * @return void
	 */
	public function test_unknown_client_retains_header(): void {
		$result = $this->classifier->classify( 'AcmeReader/3.2' );

		self::assertSame( 'AcmeReader/3.2', $result['user_agent'] );
		self::assertSame( 'unknown', $result['client_type'] );
		self::assertNull( $result['client_name'] );
		self::assertSame( 'unknown', $result['device_type'] );
	}

	/**
	 * Sites can normalize a client that the built-in rules do not know.
	 *
	 * @return void
	 */
	public function test_filter_can_supply_a_valid_custom_classification(): void {
		add_filter(
			Journey_User_Agent_Classifier::CLASSIFICATION_FILTER,
			static fn (): array => array(
				'client_type'            => 'monitor',
				'client_name'            => 'Acme Monitor',
				'device_type'            => 'server',
				'classification_version' => 21,
			)
		);

		self::assertSame(
			array(
				'user_agent'             => 'AcmeMonitor/1.0',
				'client_type'            => 'monitor',
				'client_name'            => 'Acme Monitor',
				'device_type'            => 'server',
				'classification_version' => 21,
			),
			$this->classifier->classify( 'AcmeMonitor/1.0' )
		);
	}

	/**
	 * Malformed filter responses cannot introduce invalid session values.
	 *
	 * @return void
	 */
	public function test_invalid_filter_response_falls_back_to_builtin_classification(): void {
		$header   = 'curl/8.15.0';
		$expected = $this->classifier->classify( $header );

		$invalid_values = array(
			'not-an-array',
			array(
				'client_type'            => 'HTTP Client',
				'client_name'            => 'curl',
				'device_type'            => 'unknown',
				'classification_version' => 1,
			),
			array(
				'client_type'            => 'http_client',
				'client_name'            => str_repeat( 'A', 65 ),
				'device_type'            => 'unknown',
				'classification_version' => 1,
			),
			array(
				'client_type'            => 'http_client',
				'client_name'            => 'curl',
				'device_type'            => 'unknown',
				'classification_version' => 0,
			),
		);

		foreach ( $invalid_values as $invalid_value ) {
			$GLOBALS['shurloc_test_filters'] = array();
			add_filter(
				Journey_User_Agent_Classifier::CLASSIFICATION_FILTER,
				static fn () => $invalid_value
			);

			self::assertSame( $expected, $this->classifier->classify( $header ) );
		}
	}
}
