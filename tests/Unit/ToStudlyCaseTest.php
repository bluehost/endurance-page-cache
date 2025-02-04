<?php

use PHPUnit\Framework\TestCase;

class ToStudlyCaseTest extends TestCase {

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
	 * Test if a string is properly converted to studly case.
	 *
	 * @dataProvider optionProvider
	 *
	 * @param string $value
	 * @param bool   $expected
	 */
	public function test_studly( $value, $expected ) {

		$actual = $this->instance->to_studly_case( $value );

		$this->assertSame(
			$expected,
			$actual,
			sprintf(
				'Unexpected conversion of string (%s) to (%s) when converting to Pascal case.',
				$value,
				$actual
			)
		);
	}

	/**
	 * Returns a list of options where the key is the name and the value is whether the cache should be purged.
	 *
	 * @return array
	 */
	public function optionProvider() {
		return [
			[ 'wp_notification_bar_activated', 'WpNotificationBarActivated' ],
			[ 'wpseo_sitemap_1_cache_validator', 'WpseoSitemap1CacheValidator' ],
			[ 'wpseo-sitemap-1-cache-validator', 'WpseoSitemap1CacheValidator' ],
			[ 'wpseo_sitemap-1-cache_validator', 'WpseoSitemap1CacheValidator' ],
			[ 'wpseo sitemap 1 cache validator', 'WpseoSitemap1CacheValidator' ],
		];
	}
}
