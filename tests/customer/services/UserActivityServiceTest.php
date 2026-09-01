<?php
/**
 * Tests for the user activity service.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

use PHPUnit\Framework\TestCase;
use WP_User;

/**
 * Tests the user activity service.
 */
final class UserActivityServiceTest extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var User_Activity_Service
	 */
	private User_Activity_Service $service;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		parent::setUp();

		$GLOBALS['shurloc_test_actions']           = array();
		$GLOBALS['shurloc_test_action_metadata']   = array();
		$GLOBALS['shurloc_test_current_user_id']   = 0;
		$GLOBALS['shurloc_test_is_user_logged_in'] = false;
		$GLOBALS['shurloc_test_user_meta']         = array();
		$GLOBALS['shurloc_test_time']              = 1_000_000;

		$this->service = new User_Activity_Service();
	}

	/**
	 * Verify the frontend activity hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_frontend_activity_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'track_frontend_activity',
			),
			$GLOBALS['shurloc_test_actions']['wp']
		);
	}

	/**
	 * Verify the admin activity hook is registered.
	 *
	 * @return void
	 */
	public function test_register_adds_admin_activity_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'track_admin_activity',
			),
			$GLOBALS['shurloc_test_actions']['admin_init']
		);
	}

	/**
	 * Verify the login hook is registered with two accepted arguments.
	 *
	 * @return void
	 */
	public function test_register_adds_login_hook(): void {

		$this->service->register();

		self::assertContains(
			array(
				$this->service,
				'track_login',
			),
			$GLOBALS['shurloc_test_actions']['wp_login']
		);

		self::assertSame(
			10,
			$GLOBALS['shurloc_test_action_metadata']['wp_login'][0]['priority']
		);

		self::assertSame(
			2,
			$GLOBALS['shurloc_test_action_metadata']['wp_login'][0]['accepted_args']
		);
	}

	/**
	 * Verify frontend activity is ignored for logged-out users.
	 *
	 * @return void
	 */
	public function test_frontend_activity_ignores_logged_out_user(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = false;
		$GLOBALS['shurloc_test_current_user_id']   = 101;

		$this->service->track_frontend_activity();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify frontend activity is ignored when no current user ID exists.
	 *
	 * @return void
	 */
	public function test_frontend_activity_ignores_zero_user_id(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = 0;

		$this->service->track_frontend_activity();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify frontend activity updates the last activity timestamp.
	 *
	 * @return void
	 */
	public function test_frontend_activity_updates_last_activity(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = 101;
		$GLOBALS['shurloc_test_time']              = 1_000_000;

		$this->service->track_frontend_activity();

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify admin activity updates the last activity timestamp.
	 *
	 * @return void
	 */
	public function test_admin_activity_updates_last_activity(): void {

		$GLOBALS['shurloc_test_current_user_id'] = 101;
		$GLOBALS['shurloc_test_time']            = 1_000_000;

		$this->service->track_admin_activity();

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify admin activity is ignored when no current user exists.
	 *
	 * @return void
	 */
	public function test_admin_activity_ignores_zero_user_id(): void {

		$GLOBALS['shurloc_test_current_user_id'] = 0;

		$this->service->track_admin_activity();

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}

	/**
	 * Verify activity is not updated before the throttle interval expires.
	 *
	 * @return void
	 */
	public function test_activity_is_throttled_before_interval_expires(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = 101;
		$GLOBALS['shurloc_test_time']              = 1_000_299;

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Activity_Service::LAST_ACTIVITY_META_KEY ] =
				1_000_000;

		$this->service->track_frontend_activity();

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify activity updates when the throttle interval has elapsed.
	 *
	 * @return void
	 */
	public function test_activity_updates_when_interval_has_elapsed(): void {

		$GLOBALS['shurloc_test_is_user_logged_in'] = true;
		$GLOBALS['shurloc_test_current_user_id']   = 101;
		$GLOBALS['shurloc_test_time']              = 1_000_300;

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Activity_Service::LAST_ACTIVITY_META_KEY ] =
				1_000_000;

		$this->service->track_frontend_activity();

		self::assertSame(
			1_000_300,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify a successful login updates both login and activity timestamps.
	 *
	 * @return void
	 */
	public function test_login_updates_login_and_activity_timestamps(): void {

		$user     = new WP_User();
		$user->ID = 101;

		$GLOBALS['shurloc_test_time'] = 1_000_000;

		$this->service->track_login(
			user_login: 'test-user',
			user: $user,
		);

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_LOGIN_META_KEY ]
		);

		self::assertSame(
			1_000_000,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify login bypasses the activity throttle.
	 *
	 * @return void
	 */
	public function test_login_bypasses_activity_throttle(): void {

		$user     = new WP_User();
		$user->ID = 101;

		$GLOBALS['shurloc_test_user_meta'][101]
			[ User_Activity_Service::LAST_ACTIVITY_META_KEY ] =
				1_000_000;

		$GLOBALS['shurloc_test_time'] = 1_000_001;

		$this->service->track_login(
			user_login: 'test-user',
			user: $user,
		);

		self::assertSame(
			1_000_001,
			$GLOBALS['shurloc_test_user_meta'][101]
				[ User_Activity_Service::LAST_ACTIVITY_META_KEY ]
		);
	}

	/**
	 * Verify login is ignored for a user with an invalid ID.
	 *
	 * @return void
	 */
	public function test_login_ignores_zero_user_id(): void {

		$user     = new WP_User();
		$user->ID = 0;

		$this->service->track_login(
			user_login: 'test-user',
			user: $user,
		);

		self::assertSame(
			array(),
			$GLOBALS['shurloc_test_user_meta']
		);
	}
}
