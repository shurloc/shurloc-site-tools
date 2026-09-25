<?php
/**
 * Tests for the Customer Journey collection policy.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Customer\Journey;

use PHPUnit\Framework\TestCase;

/**
 * Tests request, client, consent, role, and capability collection rules.
 */
final class JourneyCollectionPolicyTest extends TestCase {
	/**
	 * Policy under test.
	 *
	 * @var Journey_Collection_Policy
	 */
	private Journey_Collection_Policy $policy;

	/**
	 * Original server request context.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_server = array();

	/**
	 * Reset request and filter state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_server = $_SERVER;
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$GLOBALS['shurloc_test_filters']                 = array();
		$GLOBALS['shurloc_test_filter_metadata']         = array();
		$GLOBALS['shurloc_test_is_admin']                = false;
		$GLOBALS['shurloc_test_doing_ajax']              = false;
		$GLOBALS['shurloc_test_user_capabilities_by_id'] = array();
		$GLOBALS['shurloc_journey_test_current_user']    = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_test_doing_cron']      = false;

		$this->policy = new Journey_Collection_Policy();
	}

	/**
	 * Restore shared test globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->original_server;

		$GLOBALS['shurloc_test_filters']                 = array();
		$GLOBALS['shurloc_test_filter_metadata']         = array();
		$GLOBALS['shurloc_test_is_admin']                = true;
		$GLOBALS['shurloc_test_doing_ajax']              = false;
		$GLOBALS['shurloc_test_user_capabilities_by_id'] = array();
		$GLOBALS['shurloc_journey_test_current_user']    = new Journey_Collection_Test_User();
		$GLOBALS['shurloc_journey_test_doing_cron']      = false;

		parent::tearDown();
	}

	/**
	 * Anonymous frontend requests are eligible with the default policy.
	 *
	 * @return void
	 */
	public function test_anonymous_frontend_request_is_allowed_by_default(): void {
		self::assertTrue( $this->policy->allows_collection() );
	}

	/**
	 * Standard browser requests remain eligible for collection.
	 *
	 * @return void
	 */
	public function test_browser_user_agents_are_allowed(): void {
		foreach (
			array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
				'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
			) as $user_agent
		) {
			$_SERVER['HTTP_USER_AGENT'] = $user_agent;
			self::assertTrue( $this->policy->allows_collection(), $user_agent );
		}
	}

	/**
	 * Known crawlers and command-line clients are not customer journeys.
	 *
	 * @return void
	 */
	public function test_automated_user_agents_are_excluded(): void {
		foreach (
			array(
				'Mozilla/5.0 (compatible; Googlebot/2.1; +https://www.google.com/bot.html)',
				'Mozilla/5.0 (compatible; bingbot/2.0; +https://www.bing.com/bingbot.htm)',
				'DuckDuckBot/1.1',
				'Mozilla/5.0 (compatible; Googlebot-Image/1.0)',
				'facebookexternalhit/1.1',
				'Mozilla/5.0 HeadlessChrome/120.0',
				'curl/8.4.0',
				'Wget/1.25.0',
				'python-requests/2.31.0',
				'Go-http-client/2.0',
			) as $user_agent
		) {
			$_SERVER['HTTP_USER_AGENT'] = $user_agent;
			self::assertFalse( $this->policy->allows_collection(), $user_agent );
		}
	}

	/**
	 * Collection uses centrally filtered normalized client classification.
	 *
	 * @return void
	 */
	public function test_filtered_client_classification_controls_eligibility(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'AcmeMonitor/1.0';

		add_filter(
			Journey_User_Agent_Classifier::CLASSIFICATION_FILTER,
			static fn (): array => array(
				'client_type'            => 'http_client',
				'client_name'            => 'Acme Monitor',
				'device_type'            => 'server',
				'classification_version' => 21,
			)
		);

		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * Missing headers remain eligible; malformed header values fail closed.
	 *
	 * @return void
	 */
	public function test_missing_and_malformed_user_agents(): void {
		self::assertTrue( $this->policy->allows_collection() );

		$_SERVER['HTTP_USER_AGENT'] = array( 'Googlebot' );
		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * Normal WordPress admin requests are excluded before consent is checked.
	 *
	 * @return void
	 */
	public function test_admin_page_is_excluded(): void {
		$GLOBALS['shurloc_test_is_admin'] = true;

		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * Admin AJAX remains eligible for a later Journey ingestion endpoint.
	 *
	 * @return void
	 */
	public function test_admin_ajax_can_be_eligible(): void {
		$GLOBALS['shurloc_test_is_admin']   = true;
		$GLOBALS['shurloc_test_doing_ajax'] = true;

		self::assertTrue( $this->policy->allows_collection() );
	}

	/**
	 * Cron requests remain excluded even if WordPress reports AJAX context.
	 *
	 * @return void
	 */
	public function test_cron_request_is_excluded(): void {
		$GLOBALS['shurloc_test_is_admin']           = true;
		$GLOBALS['shurloc_test_doing_ajax']         = true;
		$GLOBALS['shurloc_journey_test_doing_cron'] = true;

		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * The same policy denies collection when site consent is absent.
	 *
	 * @return void
	 */
	public function test_consent_filter_can_deny_anonymous_and_logged_in_users(): void {
		add_filter(
			Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER,
			static fn (): bool => false
		);

		self::assertFalse( $this->policy->allows_collection() );
		self::assertFalse(
			$this->policy->allows_collection( user: new Journey_Collection_Test_User( 12 ) )
		);
	}

	/**
	 * Ambiguous consent responses do not accidentally enable collection.
	 *
	 * @return void
	 */
	public function test_non_boolean_consent_response_is_denied(): void {
		add_filter(
			Journey_Collection_Policy::COLLECTION_ALLOWED_FILTER,
			static fn (): string => 'yes'
		);

		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * No role or capability is excluded without a site filter.
	 *
	 * @return void
	 */
	public function test_no_role_is_hard_coded_as_excluded(): void {
		$user        = new Journey_Collection_Test_User( 12 );
		$user->roles = array( 'administrator' );

		$GLOBALS['shurloc_test_user_capabilities_by_id'][12]['manage_options'] = true;

		self::assertTrue( $this->policy->allows_collection( user: $user ) );
	}

	/**
	 * A filtered role list excludes matching authenticated users.
	 *
	 * @return void
	 */
	public function test_role_filter_excludes_only_matching_role(): void {
		add_filter(
			Journey_Collection_Policy::EXCLUDED_ROLES_FILTER,
			static fn (): array => array( 'shop_manager' )
		);

		$excluded        = new Journey_Collection_Test_User( 12 );
		$excluded->roles = array( 'shop_manager' );
		$allowed         = new Journey_Collection_Test_User( 13 );
		$allowed->roles  = array( 'customer' );

		self::assertFalse( $this->policy->allows_collection( user: $excluded ) );
		self::assertTrue( $this->policy->allows_collection( user: $allowed ) );
	}

	/**
	 * A filtered capability list excludes users granted that capability.
	 *
	 * @return void
	 */
	public function test_capability_filter_excludes_matching_user(): void {
		add_filter(
			Journey_Collection_Policy::EXCLUDED_CAPABILITIES_FILTER,
			static fn (): array => array( 'edit_shop_orders' )
		);

		$GLOBALS['shurloc_test_user_capabilities_by_id'][12]['edit_shop_orders'] = true;

		self::assertFalse(
			$this->policy->allows_collection( user: new Journey_Collection_Test_User( 12 ) )
		);
		self::assertTrue(
			$this->policy->allows_collection( user: new Journey_Collection_Test_User( 13 ) )
		);
	}

	/**
	 * The default user is read from the current request, not a stored identity.
	 *
	 * @return void
	 */
	public function test_current_user_is_checked_when_no_user_is_passed(): void {
		$user        = new Journey_Collection_Test_User( 22 );
		$user->roles = array( 'editor' );
		$GLOBALS['shurloc_journey_test_current_user'] = $user;

		add_filter(
			Journey_Collection_Policy::EXCLUDED_ROLES_FILTER,
			static fn (): array => array( 'editor' )
		);

		self::assertFalse( $this->policy->allows_collection() );
	}

	/**
	 * Malformed exclusion lists fail closed for authenticated users.
	 *
	 * @return void
	 */
	public function test_invalid_exclusion_filter_responses_are_denied(): void {
		$user = new Journey_Collection_Test_User( 12 );

		add_filter(
			Journey_Collection_Policy::EXCLUDED_ROLES_FILTER,
			static fn (): string => 'administrator'
		);
		self::assertFalse( $this->policy->allows_collection( user: $user ) );

		$GLOBALS['shurloc_test_filters'] = array();
		add_filter(
			Journey_Collection_Policy::EXCLUDED_CAPABILITIES_FILTER,
			static fn (): array => array( '' )
		);
		self::assertFalse( $this->policy->allows_collection( user: $user ) );
	}
}
