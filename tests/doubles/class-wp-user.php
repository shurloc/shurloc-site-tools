<?php
/**
 * WordPress user test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_User' ) ) {

	/**
	 * WordPress user test double.
	 */
	class WP_User {

		/**
		 * User ID.
		 *
		 * @var int
		 */
		public int $ID = 0;

		/**
		 * User display name.
		 *
		 * @var string
		 */
		public string $display_name = '';

		/**
		 * User email address.
		 *
		 * @var string
		 */
		public string $user_email = '';

		/**
		 * Create a WordPress user test double.
		 *
		 * @param int $user_id User ID.
		 */
		public function __construct( int $user_id = 0 ) {

			$this->ID = $user_id;

			$user_data = $GLOBALS['shurloc_test_user_data'][ $user_id ] ?? array();

			if ( ! is_array( $user_data ) ) {
				return;
			}

			if ( isset( $user_data['display_name'] ) && is_string( $user_data['display_name'] ) ) {
				$this->display_name = $user_data['display_name'];
			}

			if ( isset( $user_data['user_email'] ) && is_string( $user_data['user_email'] ) ) {
				$this->user_email = $user_data['user_email'];
			}
		}
	}
}
