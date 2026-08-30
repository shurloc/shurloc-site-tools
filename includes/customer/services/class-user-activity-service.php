<?php
/**
 * User activity service.
 *
 * Tracks user login and recent activity timestamps across the WordPress
 * frontend and administration area.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Services;

defined( 'ABSPATH' ) || exit;

use WP_User;

/**
 * Tracks customer login and activity timestamps.
 */
final class User_Activity_Service {

	/**
	 * Last login user meta key.
	 *
	 * @var string
	 */
	public const LAST_LOGIN_META_KEY = 'last_login';

	/**
	 * Last activity user meta key.
	 *
	 * @var string
	 */
	public const LAST_ACTIVITY_META_KEY = 'last_activity';

	/**
	 * Minimum interval between activity updates.
	 *
	 * @var int
	 */
	private const ACTIVITY_UPDATE_INTERVAL = 300;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp',
			array(
				$this,
				'track_frontend_activity',
			)
		);

		add_action(
			'admin_init',
			array(
				$this,
				'track_admin_activity',
			)
		);

		add_action(
			'wp_login',
			array(
				$this,
				'track_login',
			),
			10,
			2
		);
	}

	/**
	 * Track activity for a logged-in frontend user.
	 *
	 * @return void
	 */
	public function track_frontend_activity(): void {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$this->update_activity(
			user_id: $user_id,
			timestamp: time(),
		);
	}

	/**
	 * Track activity for a logged-in administrator-area user.
	 *
	 * @return void
	 */
	public function track_admin_activity(): void {

		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$this->update_activity(
			user_id: $user_id,
			timestamp: time(),
		);
	}

	/**
	 * Track a successful user login.
	 *
	 * Login always updates both timestamps immediately. The activity throttle
	 * is intentionally bypassed because a successful login is a meaningful
	 * customer event.
	 *
	 * @param string  $user_login User login name.
	 * @param WP_User $user       Logged-in user.
	 * @return void
	 */
	public function track_login(
		string $user_login,
		WP_User $user
	): void {

		unset( $user_login );

		$user_id = (int) $user->ID;

		if ( 0 === $user_id ) {
			return;
		}

		$timestamp = time();

		update_user_meta(
			$user_id,
			self::LAST_LOGIN_META_KEY,
			$timestamp
		);

		update_user_meta(
			$user_id,
			self::LAST_ACTIVITY_META_KEY,
			$timestamp
		);
	}

	/**
	 * Update a user's last activity timestamp when the throttle has elapsed.
	 *
	 * @param int $user_id   User ID.
	 * @param int $timestamp Current timestamp.
	 * @return void
	 */
	private function update_activity(
		int $user_id,
		int $timestamp
	): void {

		$last_activity = (int) get_user_meta(
			$user_id,
			self::LAST_ACTIVITY_META_KEY,
			true
		);

		if (
			self::ACTIVITY_UPDATE_INTERVAL >
			$timestamp - $last_activity
		) {
			return;
		}

		update_user_meta(
			$user_id,
			self::LAST_ACTIVITY_META_KEY,
			$timestamp
		);
	}
}
