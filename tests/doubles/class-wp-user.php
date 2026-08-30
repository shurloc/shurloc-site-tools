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
	}
}
