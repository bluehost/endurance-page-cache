<?php

/**
 * Class GetCurrentSinglePurgeUrlTest
 */
class GetCurrentSinglePurgeUrlTest extends WP_UnitTestCase {

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
	 * Tests the get_current_single_purge_url() function.
	 *
	 * @dataProvider dataProvider
	 *
	 * @param string $host
	 * @param string $requestUri
	 */
	public function testGetCurrentSinglePurgeUrl( $host, $requestUri ) {

		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['REQUEST_URI'] = $requestUri;

		add_filter( 'home_url', function ( $url, $path ) use ( $host ) {
			return 'http://' . $host . $path;
		}, 10, 3 );

		$this->assertSame(
			'http://' . $host . $requestUri,
			$this->instance->get_current_single_purge_url(),
			'Single purge URL invalid.'
		);
	}

	/**
	 * Tests the get_current_single_purge_url() function when WordPress is installed in a subdirectory.
	 *
	 * @dataProvider dataProvider
	 *
	 * @param string $host
	 * @param string $requestUri
	 */
	public function testGetCurrentSinglePurgeUrlOnSubdirectoryInstall( $host, $requestUri ) {

		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['REQUEST_URI'] = $requestUri;

		add_filter( 'home_url', function ( $url, $path ) use ( $host, $requestUri ) {
			$path_segments = array_filter( explode( '/', $requestUri ) );

			return 'http://' . $host . '/' . array_shift( $path_segments ) . $path;
		}, 10, 2 );

		$this->assertSame(
			'http://' . $host . $requestUri,
			$this->instance->get_current_single_purge_url(),
			'Single purge URL invalid on sites installed in a subdirectory.'
		);
	}


	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function dataProvider() {
		return [
			[ 'mysite.com', '/subdirectory/2018/10/24/hello-world/' ],
		];
	}

}
