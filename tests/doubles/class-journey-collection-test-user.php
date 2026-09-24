<?php
/**
 * Journey collection policy user test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use WP_User;

/**
 * Test user with the WordPress role list used by the policy.
 */
final class Journey_Collection_Test_User extends WP_User {
	/**
	 * WordPress role slugs for this test user.
	 *
	 * @var array<int,string>
	 */
	public $roles = array();
}
