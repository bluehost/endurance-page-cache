<?php

/**
 * Class ShouldThrottleTest
 */
class ShouldThrottleTest extends WP_UnitTestCase {

	/**
	 * @var Endurance_Page_Cache
	 */
	protected $instance;

	/**
	 * Setup class instance
	 */
	public function setUp() {
		$this->instance = new Endurance_Page_Cache();
	}

	/**
	 * Tests the should_throttle() method.
	 */
	public function test_should_throttle() {

		$uri = 'https://www.google.com';

		$this->assertEquals( false, $this->instance->should_throttle( $uri ), 'Failed asserting that should_throttle() returned false.' );
		$this->assertEquals( true, $this->instance->should_throttle( $uri ), 'Failed asserting that should_throttle() returned true.' );

	}

}
