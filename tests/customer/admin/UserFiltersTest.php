<?php
/**
 * Tests for shared user admin filters.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests the shared user filters coordinator.
 */
final class UserFiltersTest extends TestCase {

	/**
	 * User filters coordinator under test.
	 *
	 * @var User_Filters
	 */
	private User_Filters $user_filters;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();

		$this->user_filters = new User_Filters();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {

		$GLOBALS['shurloc_test_actions']         = array();
		$GLOBALS['shurloc_test_action_metadata'] = array();

		parent::tearDown();
	}

	/**
	 * Verify the Users extra tablenav hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_manage_users_extra_tablenav_action(): void {

		$this->user_filters->register();

		self::assertContains(
			array(
				$this->user_filters,
				'render_filters',
			),
			$GLOBALS['shurloc_test_actions']['manage_users_extra_tablenav']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']
				['manage_users_extra_tablenav'][0]['priority']
		);

		self::assertSame(
			1,
			$GLOBALS['shurloc_test_action_metadata']
				['manage_users_extra_tablenav'][0]['accepted_args']
		);
	}

	/**
	 * Verify filters are rendered in the top controls.
	 *
	 * @return void
	 */
	public function test_filters_are_rendered_at_top(): void {

		ob_start();

		$this->user_filters->render_filters(
			which: 'top',
		);

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertStringContainsString(
			'<div class="alignleft actions">',
			$output
		);

		self::assertStringContainsString(
			'name="filter_action"',
			$output
		);

		self::assertStringContainsString(
			'value="Filter"',
			$output
		);
	}

	/**
	 * Verify filters are not rendered in the bottom controls.
	 *
	 * @return void
	 */
	public function test_filters_are_not_rendered_at_bottom(): void {

		ob_start();

		$this->user_filters->render_filters(
			which: 'bottom',
		);

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertSame(
			'',
			$output
		);
	}

	/**
	 * Verify the shared filter controls action is fired.
	 *
	 * @return void
	 */
	public function test_render_filters_fires_filter_controls_action(): void {

		$rendered = false;

		add_action(
			User_Filters::FILTER_CONTROLS_ACTION,
			static function () use ( &$rendered ): void {
				$rendered = true;
			}
		);

		ob_start();

		$this->user_filters->render_filters(
			which: 'top',
		);

		ob_end_clean();

		self::assertTrue(
			$rendered
		);
	}

	/**
	 * Verify feature-specific controls are rendered inside the filter bar.
	 *
	 * @return void
	 */
	public function test_feature_controls_are_rendered_inside_filter_bar(): void {

		add_action(
			User_Filters::FILTER_CONTROLS_ACTION,
			static function (): void {
				echo '<select name="test_filter"></select>';
			}
		);

		ob_start();

		$this->user_filters->render_filters(
			which: 'top',
		);

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertStringContainsString(
			'<select name="test_filter"></select>',
			$output
		);
	}

	/**
	 * Verify exactly one shared Filter button is rendered.
	 *
	 * @return void
	 */
	public function test_render_filters_outputs_one_filter_button(): void {

		add_action(
			User_Filters::FILTER_CONTROLS_ACTION,
			static function (): void {
				echo '<select name="test_filter"></select>';
			}
		);

		ob_start();

		$this->user_filters->render_filters(
			which: 'top',
		);

		$output = ob_get_clean();

		self::assertIsString( $output );

		self::assertSame(
			1,
			substr_count(
				$output,
				'name="filter_action"'
			)
		);
	}

	/**
	 * Verify the shared Filter button renders after feature controls.
	 *
	 * @return void
	 */
	public function test_filter_button_renders_after_feature_controls(): void {

		add_action(
			User_Filters::FILTER_CONTROLS_ACTION,
			static function (): void {
				echo '<select name="test_filter"></select>';
			}
		);

		ob_start();

		$this->user_filters->render_filters(
			which: 'top',
		);

		$output = ob_get_clean();

		self::assertIsString( $output );

		$control_position = strpos(
			$output,
			'name="test_filter"'
		);

		$button_position = strpos(
			$output,
			'name="filter_action"'
		);

		self::assertNotFalse(
			$control_position
		);

		self::assertNotFalse(
			$button_position
		);

		self::assertGreaterThan(
			$control_position,
			$button_position
		);
	}
}
