<?php

/**
 * Class GetCurrentSinglePurgeUrlTest
 */
class GetPurgeRequestUrlTest extends WP_UnitTestCase {

	/**
	 * @var Endurance_Page_Cache
	 */
	protected $instance;

	/**
	 * Setup class instance
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->instance = new Endurance_Page_Cache();
	}

	/**
	 * Tests the get_purge_url() function.
	 *
	 * @dataProvider dataProvider
	 *
	 * @param string $host
	 * @param string $requestUri
	 */
	public function test_get_purge_request_url( $url, $expectedHTTP, $expectedHTTPS ) {

		add_filter( 'home_url', function () {
			return 'https://www.mysite.com';
		}, 10, 3 );

		$this->assertSame(
			$expectedHTTP,
			$this->instance->get_purge_request_url( $url, 'http' )
		);

		$this->assertSame(
			$expectedHTTPS,
			$this->instance->get_purge_request_url( $url, 'https' )
		);

	}

	/**
	 * Tests the get_purge_url() function in the case where a site is installed in a subdirectory.
	 *
	 * @dataProvider dataProvider
	 *
	 * @param string $host
	 * @param string $requestUri
	 */
	public function test_get_purge_request_url_subdirectory( $url, $expectedHTTP, $expectedHTTPS ) {

		add_filter( 'home_url', function () {
			return 'https://www.mysite.com/subdirectory';
		}, 10, 3 );

		$this->assertSame(
			$expectedHTTP,
			$this->instance->get_purge_request_url( $url, 'http' )
		);

		$this->assertSame(
			$expectedHTTPS,
			$this->instance->get_purge_request_url( $url, 'https' )
		);

	}


	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function dataProvider() {
		return [
			[
				'https://www.mysite.com/subdirectory/2018/10/24/hello-world/',
				'http://127.0.0.1:8080/subdirectory/2018/10/24/hello-world/',
				'https://127.0.0.1:8443/subdirectory/2018/10/24/hello-world/',
			],
			[
				'/subdirectory/2018/10/24/hello-world/',
				'http://127.0.0.1:8080/subdirectory/2018/10/24/hello-world/',
				'https://127.0.0.1:8443/subdirectory/2018/10/24/hello-world/',
			],
		];
	}

}
