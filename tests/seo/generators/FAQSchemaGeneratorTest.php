<?php
/**
 * Tests for the FAQ schema generator.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Generators;

use PHPUnit\Framework\TestCase;

/**
 * Tests the FAQ schema generator.
 */
final class FAQSchemaGeneratorTest extends TestCase {

	/**
	 * Generator under test.
	 *
	 * @var FAQ_Schema_Generator
	 */
	private FAQ_Schema_Generator $generator;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->generator = new FAQ_Schema_Generator();
	}

	/**
	 * Verify empty FAQ items return no schema.
	 *
	 * @return void
	 */
	public function test_generate_returns_empty_array_for_empty_items(): void {

		self::assertSame(
			array(),
			$this->generator->generate(
				faq_items: array(),
			)
		);
	}

	/**
	 * Verify a valid FAQ item generates FAQPage schema.
	 *
	 * @return void
	 */
	public function test_generate_returns_faq_page_schema(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => 'What is Shur-loc mesh?',
					'answer'   => 'Shur-loc mesh is used for screening and filtration applications.',
				),
			),
		);

		self::assertSame(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => array(
					array(
						'@type'          => 'Question',
						'name'           => 'What is Shur-loc mesh?',
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => 'Shur-loc mesh is used for screening and filtration applications.',
						),
					),
				),
			),
			$result
		);
	}

	/**
	 * Verify multiple FAQ items generate multiple schema questions.
	 *
	 * @return void
	 */
	public function test_generate_handles_multiple_faq_items(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => 'What is the first question?',
					'answer'   => 'This is the first answer.',
				),
				array(
					'question' => 'What is the second question?',
					'answer'   => 'This is the second answer.',
				),
			),
		);

		self::assertArrayHasKey(
			'mainEntity',
			$result
		);

		self::assertCount(
			2,
			$result['mainEntity']
		);

		self::assertSame(
			'What is the first question?',
			$result['mainEntity'][0]['name']
		);

		self::assertSame(
			'What is the second question?',
			$result['mainEntity'][1]['name']
		);
	}

	/**
	 * Verify surrounding whitespace is removed from FAQ values.
	 *
	 * @return void
	 */
	public function test_generate_trims_question_and_answer(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => '  What is Shur-loc mesh?  ',
					'answer'   => '  This is the answer.  ',
				),
			),
		);

		self::assertArrayHasKey(
			'mainEntity',
			$result
		);

		self::assertSame(
			'What is Shur-loc mesh?',
			$result['mainEntity'][0]['name']
		);

		self::assertSame(
			'This is the answer.',
			$result['mainEntity'][0]['acceptedAnswer']['text']
		);
	}

	/**
	 * Verify FAQ items with an empty question are ignored.
	 *
	 * @return void
	 */
	public function test_generate_ignores_items_with_empty_question(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => '',
					'answer'   => 'This answer should not be included.',
				),
			),
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify FAQ items with an empty answer are ignored.
	 *
	 * @return void
	 */
	public function test_generate_ignores_items_with_empty_answer(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => 'What is the question?',
					'answer'   => '',
				),
			),
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify whitespace-only questions and answers are ignored.
	 *
	 * @return void
	 */
	public function test_generate_ignores_whitespace_only_values(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => '   ',
					'answer'   => 'This answer should not be included.',
				),
				array(
					'question' => 'What is the question?',
					'answer'   => '   ',
				),
			),
		);

		self::assertSame(
			array(),
			$result
		);
	}

	/**
	 * Verify invalid FAQ items are skipped while valid items remain.
	 *
	 * @return void
	 */
	public function test_generate_skips_invalid_items_and_keeps_valid_items(): void {

		$result = $this->generator->generate(
			faq_items: array(
				array(
					'question' => '',
					'answer'   => 'Invalid answer.',
				),
				array(
					'question' => 'What is a valid question?',
					'answer'   => 'This is a valid answer.',
				),
				array(
					'question' => 'What is another invalid question?',
					'answer'   => '',
				),
			),
		);

		self::assertArrayHasKey(
			'mainEntity',
			$result
		);

		self::assertCount(
			1,
			$result['mainEntity']
		);

		self::assertSame(
			'What is a valid question?',
			$result['mainEntity'][0]['name']
		);

		self::assertSame(
			'This is a valid answer.',
			$result['mainEntity'][0]['acceptedAnswer']['text']
		);
	}
}
