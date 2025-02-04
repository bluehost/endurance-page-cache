<?php

use PHPUnit\Framework\TestCase;

class ToSnakeCaseTest extends TestCase {

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
	 * Test if a string is properly converted to snake case.
	 *
	 * @dataProvider optionProvider
	 *
	 * @param string $option
	 * @param bool   $expected
	 */
	public function test_to_snake_case( $string, $expected ) {

		$actual = $this->instance->to_snake_case( $string );

		$this->assertSame(
			$expected,
			$actual,
			sprintf(
				'Unexpected conversion (%s) from camel case (%s) to snake case (%s)',
				$actual,
				$string,
				$expected
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
			[ 'simpleTest', 'simple_test' ],
			[ 'easy', 'easy' ],
			[ 'HTML', 'html' ],
			[ 'simpleXML', 'simple_xml' ],
			[ 'PDFLoad', 'pdf_load' ],
			[ 'startMIDDLELast', 'start_middle_last' ],
			[ 'AString', 'a_string' ],
			[ 'Some4Numbers234', 'some4_numbers234' ],
			[ 'TEST123String', 'test123_string' ],
		];
	}
}
