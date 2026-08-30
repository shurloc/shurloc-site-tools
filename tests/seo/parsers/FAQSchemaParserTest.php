<?php
/**
 * Tests for the FAQ schema parser.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\SEO\Parsers;

use PHPUnit\Framework\TestCase;

/**
 * Tests the FAQ schema parser.
 */
final class FAQSchemaParserTest extends TestCase {

	/**
	 * Parser under test.
	 *
	 * @var FAQ_Schema_Parser
	 */
	private FAQ_Schema_Parser $parser;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->parser = new FAQ_Schema_Parser();
	}

	/**
	 * Verify empty content returns no FAQ items.
	 *
	 * @return void
	 */
	public function test_parse_returns_empty_array_for_empty_content(): void {

		self::assertSame(
			array(),
			$this->parser->parse(
				content: '',
			)
		);
	}

	/**
	 * Verify whitespace-only content returns no FAQ items.
	 *
	 * @return void
	 */
	public function test_parse_returns_empty_array_for_whitespace_only_content(): void {

		self::assertSame(
			array(),
			$this->parser->parse(
				content: " \n\t ",
			)
		);
	}

	/**
	 * Verify content without H3 headings returns no FAQ items.
	 *
	 * @return void
	 */
	public function test_parse_returns_empty_array_without_h3_headings(): void {

		$content = '
			<h2>Frequently Asked Questions</h2>
			<p>This page has no FAQ questions.</p>
		';

		self::assertSame(
			array(),
			$this->parser->parse(
				content: $content,
			)
		);
	}

	/**
	 * Verify a valid FAQ item is parsed.
	 *
	 * @return void
	 */
	public function test_parse_returns_valid_faq_item(): void {

		$content = '
			<h3>What is Shur-loc mesh?</h3>
			<p>
				Shur-loc mesh is used for screening and filtration
				applications in many industries.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			array(
				array(
					'question' => 'What is Shur-loc mesh?',
					'answer'   =>
						'Shur-loc mesh is used for screening and filtration applications in many industries.',
				),
			),
			$result
		);
	}

	/**
	 * Verify H3 text nested inside a link is parsed as the question.
	 *
	 * @return void
	 */
	public function test_parse_handles_question_text_inside_link(): void {

		$content = '
			<h3>
				<a href="#mesh">
					What mesh sizes are available?
				</a>
			</h3>
			<p>
				A wide range of mesh sizes is available for different
				screening applications.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'What mesh sizes are available?',
			$result[0]['question']
		);
	}

	/**
	 * Verify H3 headings without a question mark are ignored.
	 *
	 * @return void
	 */
	public function test_parse_ignores_h3_without_question_mark(): void {

		$content = '
			<h3>Mesh Size Information</h3>
			<p>
				This paragraph is long enough to otherwise qualify as
				an FAQ answer.
			</p>
		';

		self::assertSame(
			array(),
			$this->parser->parse(
				content: $content,
			)
		);
	}

	/**
	 * Verify short answers are ignored.
	 *
	 * @return void
	 */
	public function test_parse_ignores_short_answers(): void {

		$content = '
			<h3>Is this available?</h3>
			<p>Yes, it is.</p>
		';

		self::assertSame(
			array(),
			$this->parser->parse(
				content: $content,
			)
		);
	}

	/**
	 * Verify multiple answer elements are combined.
	 *
	 * @return void
	 */
	public function test_parse_combines_multiple_answer_elements(): void {

		$content = '
			<h3>How should mesh be selected?</h3>
			<p>
				Start with the opening size needed for the application.
			</p>
			<p>
				Then consider wire diameter, material, and operating
				conditions.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'Start with the opening size needed for the application. Then consider wire diameter, material, and operating conditions.',
			$result[0]['answer']
		);
	}

	/**
	 * Verify answer parsing stops at the next H3 question.
	 *
	 * @return void
	 */
	public function test_parse_stops_answer_at_next_h3(): void {

		$content = '
			<h3>What is the first question?</h3>
			<p>
				This is the first answer and it is long enough to qualify.
			</p>

			<h3>What is the second question?</h3>
			<p>
				This is the second answer and it is also long enough
				to qualify.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertCount(
			2,
			$result
		);

		self::assertSame(
			'This is the first answer and it is long enough to qualify.',
			$result[0]['answer']
		);

		self::assertSame(
			'This is the second answer and it is also long enough to qualify.',
			$result[1]['answer']
		);
	}

	/**
	 * Verify answer parsing stops at any heading level.
	 *
	 * @return void
	 */
	public function test_parse_stops_answer_at_any_heading_level(): void {

		$content = '
			<h3>What does this section explain?</h3>
			<p>
				This paragraph belongs to the FAQ answer and is long enough.
			</p>

			<h4>Additional Information</h4>

			<p>
				This paragraph must not be included in the FAQ answer.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'This paragraph belongs to the FAQ answer and is long enough.',
			$result[0]['answer']
		);
	}

	/**
	 * Verify standalone images are ignored when building an answer.
	 *
	 * @return void
	 */
	public function test_parse_skips_standalone_images(): void {

		$content = '
			<h3>What does this image show?</h3>
			<img
				src="example.jpg"
				alt="Text that should not become the FAQ answer"
			/>
			<p>
				This paragraph contains the actual FAQ answer and is
				long enough to qualify.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'This paragraph contains the actual FAQ answer and is long enough to qualify.',
			$result[0]['answer']
		);

		self::assertStringNotContainsString(
			'Text that should not become',
			$result[0]['answer']
		);
	}

	/**
	 * Verify standalone iframes are ignored when building an answer.
	 *
	 * @return void
	 */
	public function test_parse_skips_standalone_iframes(): void {

		$content = '
			<h3>Where can I learn more?</h3>
			<iframe
				src="https://example.com"
				title="Embedded content"
			></iframe>
			<p>
				This paragraph contains the real answer and is long
				enough to qualify for FAQ schema.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'This paragraph contains the real answer and is long enough to qualify for FAQ schema.',
			$result[0]['answer']
		);
	}

	/**
	 * Verify HTML markup is stripped from answer text.
	 *
	 * @return void
	 */
	public function test_parse_strips_html_from_answers(): void {

		$content = '
			<h3>Can answer text contain formatting?</h3>
			<p>
				Yes. <strong>Formatted text</strong> and
				<a href="/example">links</a> should become plain text.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'Yes. Formatted text and links should become plain text.',
			$result[0]['answer']
		);
	}

	/**
	 * Verify question whitespace is normalized.
	 *
	 * @return void
	 */
	public function test_parse_normalizes_question_whitespace(): void {

		$content = '
			<h3>
				What     mesh
				size    should I use?
			</h3>
			<p>
				The correct mesh size depends on the material and the
				screening requirements of the application.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'What mesh size should I use?',
			$result[0]['question']
		);
	}

	/**
	 * Verify answer whitespace is normalized.
	 *
	 * @return void
	 */
	public function test_parse_normalizes_answer_whitespace(): void {

		$content = '
			<h3>How is answer whitespace handled?</h3>
			<p>
				Multiple      spaces
				and newlines should be reduced to single spaces.
			</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertSame(
			'Multiple spaces and newlines should be reduced to single spaces.',
			$result[0]['answer']
		);
	}

	/**
	 * Verify non-breaking spaces are normalized.
	 *
	 * @return void
	 */
	public function test_parse_normalizes_non_breaking_spaces(): void {

		$content = sprintf(
			'<h3>How are special spaces handled?</h3>
			<p>
				Non-breaking%sspaces should be normalized into ordinary
				spaces in the final answer.
			</p>',
			"\xc2\xa0"
		);

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertStringContainsString(
			'Non-breaking spaces should be normalized',
			$result[0]['answer']
		);
	}

	/**
	 * Verify valid and invalid headings can coexist.
	 *
	 * @return void
	 */
	public function test_parse_returns_only_valid_faq_items(): void {

		$content = '
			<h3>General Information</h3>
			<p>
				This section is not an FAQ item even though its content
				is long enough.
			</p>

			<h3>What is a valid FAQ question?</h3>
			<p>
				This answer is sufficiently long and should be included
				in the parsed FAQ data.
			</p>

			<h3>Is this answer too short?</h3>
			<p>No.</p>
		';

		$result = $this->parser->parse(
			content: $content,
		);

		self::assertCount(
			1,
			$result
		);

		self::assertSame(
			'What is a valid FAQ question?',
			$result[0]['question']
		);
	}
}
