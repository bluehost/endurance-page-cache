<?php

use PHPUnit\Framework\TestCase;

class OptionHandlerTest extends TestCase {

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
	 * Test if an option results in the expected return value from the option_handler() method.
	 *
	 * @dataProvider optionProvider
	 *
	 * @param string $option
	 * @param bool $expected
	 */
	public function testOption( $option, $expected ) {

		$actual = $this->instance->option_handler( $option, 0, 1 );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Returns a list of options where the key is the name and the value is whether the cache should be purged.
	 *
	 * @return array
	 */
	public function optionProvider() {
		return [
			[ '_amn_mi-lite_last_checked', false ],
			[ '_transient_wc_count_comments', false ],
			[ 'active_plugins', false ],
			[ 'akismet_spam_count', false ],
			[ 'bwp_minify_detector_log', false ],
			[ 'charitable_upgrade_log', false ],
			[ 'comet_cache_options', false ],
			[ 'cp_cfte_last_verified', false ],
			[ 'cron', false ],
			[ 'crontrol_schedules', false ],
			[ 'current_theme_supports_woocommerce', false ],
			[ 'db_version', false ],
			[ 'eps_redirects_404s', false ],
			[ 'ffwd_autoupdate_time', false ],
			[ 'fs_accounts', false ],
			[ 'fusion_dynamic_css_ids', true ],
			[ 'fusion_dynamic_css_posts', true ],
			[ 'icwp_wpsf_plugin_options', false ],
			[ 'jetpack_available_modules', false ],
			[ 'jetpack_sync_settings_post_meta_whitelist', false ],
			[ 'jetpack_sync_settings_disable', false ],
			[ 'jetpack_sync_settings_meta_blacklist', false ],
			[ 'jpsq_sync_checkout', false ],
			[ 'limit_login_retries', false ],
			[ 'mtsnb_stats', false ],
			[ 'mwp_backup_tasks', false ],
			[ 'mwp_maintenace_mode', false ],
			[ 'mwp_worker_configuration', false ],
			[ 'option_tree_settings', false ],
			[ 'pmpro_views', false ],
			[ 'printful_incoming_api_request_log', false ],
			[ 'pum_total_open_count', false ],
			[ 'relpoststh_default_image', true ],
			[ 'rg_gforms_key', false ],
			[ 'sbp_page_time', false ],
			[ 'schema_wp_is_installed', false ],
			[ 'seo_ultimate_module_404s', false ],
			[ 'slimstat_options', false ],
			[ 'stm_custom_style', true ],
			[ 'suffusion_generated_css', true ],
			[ 'SWPA_PLUGIN_ENTRIES_LIVE_TRAFFIC', false ],
			[ 'td_011_log', false ],
			[ 'td_011_remote_cache', true ],
			[ 'tesseract_advertisement_banner', true ],
			[ 'ub-route-cache', true ],
			[ 'uninstall_plugins', false ],
			[ 'vstrsnln_options', false ],
			[ 'wp_notification_bar_activated', false ],
			[ 'wp_user_roles', false ],
			[ 'WpFastestCacheHTML', true ],
			[ 'wphb_scripts_collection', true ],
			[ 'wphb_styles_collection', true ],
			[ 'wplnst_crawler_timestamp', false ],
			[ 'wpsc_feed_list', true ],
			[ 'wpseo_sitemap_1_cache_validator', false ],
			[ 'wpsupercache_count', false ],
			[ 'ws_plugin__optimizemember_cache', true ],
			[ 'ws_plugin__s2member_cache', true ],
		];
	}
}
