<?php

/**
 * Class ExtractImageUrlsTest
 */
class ExtractImageUrlsTest extends WP_UnitTestCase
{

	/**
	 * @var \Endurance_Page_Cache
	 */
	protected $instance;

	public function setUp(): void
	{
		parent::setUp();
		$this->instance = new Endurance_Page_Cache();
	}

	/**
	 * Tests the extract_image_urls() function to validate that all image src URLs are extracted from the WordPress content.
	 *
	 * @dataProvider dataProvider
	 *
	 * @param string $content
	 * @param array  $expected
	 */
	public function testThis($content, $expected)
	{
		$actual = $this->instance->extract_image_urls($content);
		$this->assertSame($expected, $actual);
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function dataProvider()
	{
		return [

			// Empty content should return an empty array
			['', []],

			// A WordPress gallery block with two items should return the two items
			[
				'<ul class="wp-block-gallery columns-2 is-cropped"><li class="blocks-gallery-item"><figure><img src="http://dev.local/wp-content/uploads/2018/09/corporate3-sl3-1024x683.jpg" alt="" data-id="27" data-link="http://dev.local/corporate3-sl3/" class="wp-image-27" srcset="http://dev.local/wp-content/uploads/2018/09/corporate3-sl3-1024x683.jpg 1024w, http://dev.local/wp-content/uploads/2018/09/corporate3-sl3-300x200.jpg 300w, http://dev.local/wp-content/uploads/2018/09/corporate3-sl3-768x512.jpg 768w" sizes="(max-width: 1024px) 100vw, 1024px"></figure></li><li class="blocks-gallery-item"><figure><img src="http://dev.local/wp-content/uploads/2018/09/corporate3-sl1-1024x683.jpg" alt="" data-id="25" data-link="http://dev.local/corporate3-sl1/" class="wp-image-25" srcset="http://dev.local/wp-content/uploads/2018/09/corporate3-sl1-1024x683.jpg 1024w, http://dev.local/wp-content/uploads/2018/09/corporate3-sl1-300x200.jpg 300w, http://dev.local/wp-content/uploads/2018/09/corporate3-sl1-768x512.jpg 768w" sizes="(max-width: 1024px) 100vw, 1024px"></figure></li></ul>',
				[
					'http://dev.local/wp-content/uploads/2018/09/corporate3-sl3-1024x683.jpg',
					'http://dev.local/wp-content/uploads/2018/09/corporate3-sl1-1024x683.jpg',
				]
			],

			// A WordPress post with two image blocks: 1) An image from the media library, and 2) an image from an external URL.
			[
				'<figure class="wp-block-image"><img src="http://dev.local/wp-content/uploads/2018/08/cake-1024x640.jpg" alt="" class="wp-image-5" srcset="http://dev.local/wp-content/uploads/2018/08/cake-1024x640.jpg 1024w, http://dev.local/wp-content/uploads/2018/08/cake-300x188.jpg 300w, http://dev.local/wp-content/uploads/2018/08/cake-768x480.jpg 768w, http://dev.local/wp-content/uploads/2018/08/cake.jpg 1600w" sizes="(max-width: 1024px) 100vw, 1024px"><figcaption>Cake!</figcaption></figure><figure class="wp-block-image"><img src="http://www.tpalmerlandscapingco.com/images/background/bg01.jpg" alt=""></figure>',
				[
					'http://dev.local/wp-content/uploads/2018/08/cake-1024x640.jpg',
					'http://www.tpalmerlandscapingco.com/images/background/bg01.jpg',
				]
			],

		];
	}
}
