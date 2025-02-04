<?php

/**
 * Class CloudflareTierTest
 */
class CloudflareTierTest extends WP_UnitTestCase {

	/**
	 * Tests the legacy value for enabled method true with number.
	 */
	public function test_is_enabled_legacy_number_one() {

		update_option( 'endurance_cloudflare_enabled', 1 );
		
		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled true with a number 1' );
		$this->assertEquals( 'basic', $epc->cloudflare_tier, 'Failed asserting that legacy value of 1 is basic tier' );
		$this->assertEquals( 'basic', $epc->udev_api_services['cf'], 'Failed asserting that udev_api_services cf is correct tier' );
	}

	/**
	 * Tests the legacy value for enabled method true with string.
	 */
	public function test_is_enabled_legacy_string_one() {

		update_option( 'endurance_cloudflare_enabled', '1' );
		
		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled true' );
		$this->assertEquals( 'basic', $epc->cloudflare_tier, 'Failed asserting that legacy value of 1 is basic tier' );
		$this->assertEquals( 'basic', $epc->udev_api_services['cf'], 'Failed asserting that udev_api_services cf is correct tier' );
	}

	/**
	 * Tests that 0 is disabled.
	 */
	public function test_is_disabled() {

		update_option( 'endurance_cloudflare_enabled', 0 );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( false, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( false, $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 0, $epc->udev_api_services['cf'], 'Failed asserting that udev_api_services cf is disabled' );
	}

	/**
	 * Tests that no value is disabled.
	 */
	public function test_no_value_is_disabled() {

		delete_option( 'endurance_cloudflare_enabled' );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( false, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( false, $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 0, $epc->udev_api_services['cf'], 'Failed asserting that udev_api_services cf is disabled' );
	}

	/**
	 * Tests that india is a valid tier.
	 */
	public function test_is_india() {

		update_option( 'endurance_cloudflare_enabled', 'india' );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( 'india', $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 'india', $epc->udev_api_services['cf'], 'Failed asserting that legacy value of 0 is false tier' );
	}

	/**
	 * Tests basic tier.
	 */
	public function test_is_basic() {

		update_option( 'endurance_cloudflare_enabled', 'basic' );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( 'basic', $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 'basic', $epc->udev_api_services['cf'], 'Failed asserting that legacy value of 0 is false tier' );
	}

	/**
	 * Tests premium tier.
	 */
	public function test_is_premium() {

		update_option( 'endurance_cloudflare_enabled', 'premium' );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( 'premium', $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 'premium', $epc->udev_api_services['cf'], 'Failed asserting that legacy value of 0 is false tier' );
	}

	/**
	 * Tests that any value is passed through to udev to accomidate future tiers.
	 */
	public function test_passthru_value() {

		update_option( 'endurance_cloudflare_enabled', 'passthru' );

		$epc = new Endurance_Page_Cache();

		$this->assertEquals( true, $epc->cloudflare_enabled, 'Failed asserting that cloudflare_enabled false' );
		$this->assertEquals( 'passthru', $epc->cloudflare_tier, 'Failed asserting that legacy value of 0 is false tier' );
		$this->assertEquals( 'passthru', $epc->udev_api_services['cf'], 'Failed asserting that legacy value of 0 is false tier' );
	}

}
