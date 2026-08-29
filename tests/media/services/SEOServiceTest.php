<?php
/**
 * Tests for the media SEO service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media\Services;

use PHPUnit\Framework\TestCase;

/**
 * Tests the media SEO service.
 */
final class SEOServiceTest extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var SEO_Service
	 */
	private SEO_Service $service;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_post_meta'] = array();

		$this->service = new SEO_Service();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_post_meta'] = array();

		parent::tearDown();
	}

	/**
	 * Verify stored alt text can be retrieved.
	 *
	 * @return void
	 */
	public function test_get_alt_text_returns_stored_value(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = 'Shur-loc mesh product';

		self::assertSame(
			'Shur-loc mesh product',
			$this->service->get_alt_text(
				attachment_id: 100,
			)
		);
	}

	/**
	 * Verify surrounding whitespace is removed from alt text.
	 *
	 * @return void
	 */
	public function test_get_alt_text_trims_whitespace(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = '  Shur-loc mesh product  ';

		self::assertSame(
			'Shur-loc mesh product',
			$this->service->get_alt_text(
				attachment_id: 100,
			)
		);
	}

	/**
	 * Verify an attachment without alt text returns an empty string.
	 *
	 * @return void
	 */
	public function test_get_alt_text_returns_empty_string_when_unset(): void {

		self::assertSame(
			'',
			$this->service->get_alt_text(
				attachment_id: 100,
			)
		);
	}

	/**
	 * Verify a non-string stored value is treated as missing.
	 *
	 * @return void
	 */
	public function test_get_alt_text_returns_empty_string_for_non_string_value(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = array(
				'invalid',
			);

			self::assertSame(
				'',
				$this->service->get_alt_text(
					attachment_id: 100,
				)
			);
	}

	/**
	 * Verify an invalid attachment ID returns an empty string.
	 *
	 * @return void
	 */
	public function test_get_alt_text_returns_empty_string_for_invalid_attachment_id(): void {

		self::assertSame(
			'',
			$this->service->get_alt_text(
				attachment_id: 0,
			)
		);
	}

	/**
	 * Verify an attachment with alt text reports that it has alt text.
	 *
	 * @return void
	 */
	public function test_has_alt_text_returns_true_when_alt_text_exists(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = 'Shur-loc mesh product';

		self::assertTrue(
			$this->service->has_alt_text(
				attachment_id: 100,
			)
		);
	}

	/**
	 * Verify an attachment without alt text reports that alt text is missing.
	 *
	 * @return void
	 */
	public function test_has_alt_text_returns_false_when_alt_text_is_unset(): void {

		self::assertFalse(
			$this->service->has_alt_text(
				attachment_id: 100,
			)
		);
	}

	/**
	 * Verify whitespace-only alt text is treated as missing.
	 *
	 * @return void
	 */
	public function test_has_alt_text_returns_false_for_whitespace_only_alt_text(): void {

		$GLOBALS['shurloc_test_post_meta'][100]
			[ SEO_Service::ALT_TEXT_META_KEY ] = '   ';

		self::assertFalse(
			$this->service->has_alt_text(
				attachment_id: 100,
			)
		);
	}
}
