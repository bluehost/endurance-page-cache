<?php
/**
 * Plugin Name: Endurance Page Cache
 * Description: This cache plugin is primarily for cache purging of the additional layers of cache that may be available on your hosting account.
 * Version: 2.0.5
 * Author: Mike Hansen
 * Author URI: https://www.mikehansen.me/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package EndurancePageCache
 */

/**
 * Endurance Page Cache is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * Endurance Page Cache is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with Endurance Page Cache; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @license GPL-v2-or-later
 * @link https://github.com/bluehost/endurance-page-cache/LICENSE
 * (If this plugin was installed as a single file, a copy of the license is available in the distribution repository in the link above)
 */

// Do not access file directly!
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'EPC_VERSION', '2.0.5' );

if ( ! class_exists( 'Endurance_Page_Cache' ) ) {

	/**
	 * Class Endurance_Page_Cache
	 */
	class Endurance_Page_Cache {

		/**
		 * The directory where cached files are stored.
		 *
		 * @var string
		 */
		public $cache_dir;

		/**
		 * A collection of tokens which, if contained in a URI, will prevent caching.
		 *
		 * @var array
		 */
		public $cache_exempt = array( '@', '%', '&', '=', ':', ';', '.', 'checkout', 'cart', 'wp-admin' );

		/**
		 * Cache level.
		 *
		 * @var int
		 */
		public $cache_level = 2;

		/**
		 * Cloudflare enabled
		 *
		 * @var bool
		 */
		public $cloudflare_enabled = false;

		/**
		 * Cloudflare tier
		 *
		 * @var string
		 */
		public $cloudflare_tier = 'basic';

		/**
		 * Brands supporting cloudflare (from mm_brand option).
		 *
		 * @var array
		 */
		public $cloudflare_support = array( 'BlueHost' );

		/**
		 * Whether or not to force a purge.
		 *
		 * @var bool
		 */
		public $force_purge = false;

		/**
		 * A collection of throttled items grouped by type where the key is a hash of the URI and the value is the expiration timestamp.
		 *
		 * @var array
		 */
		public $throttled = array();

		/**
		 * Whether or not to update list of throttled items (transient: epc_throttled).
		 *
		 * @var bool
		 */
		public $should_update_throttled_items = false;

		/**
		 * Record keeping for which triggers have fired
		 *
		 * @var array
		 */
		public $triggers = array();

		/**
		 * UDEV Purge Buffer
		 *
		 * This parameter determines whether to hit the UDEV Cache Purge API.
		 *
		 * Set to false, no request is made.
		 * Set to true or an empty array all cached resources are purged.
		 * Set to array of relative paths to purge specified resources only.
		 *
		 * @var boolean|array
		 */
		protected $udev_purge_buffer = false;

		/**
		 * UDEV Cache Purge API Root URL.
		 *
		 * @var string
		 */
		protected static $udev_api_root = 'https://cachepurge.bluehost.com';

		/**
		 * UDEV Cache Purge API version string. First tag v0.
		 *
		 * @var string
		 */
		protected static $udev_api_version = 'v0';

		/**
		 * UDEV Cache Purge API endpoint
		 *
		 * @var string
		 */
		protected static $udev_api_endpoint = 'purge';

		/**
		 * UDEV Cache Purge API services.
		 *
		 * PARAMETERS:
		 * 'cf'  => 1|0 (default 1)
		 * 'epc' => 1|0 (default 0)
		 *
		 * @var array
		 */
		public $udev_api_services = array(
			'cf'  => 1,
			'epc' => 0,
		);

		/**
		 * Endurance_Page_Cache constructor.
		 */
		public function __construct() {

			if ( isset( $_GET['doing_wp_cron'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}

			$this->throttled = array_filter( (array) get_transient( 'epc_throttled' ) );

			$this->cache_level = get_option( 'endurance_cache_level', 2 );
			$this->cache_dir   = WP_CONTENT_DIR . '/endurance-page-cache';

			$cloudflare_state = get_option( 'endurance_cloudflare_enabled', false );

			$this->cloudflare_enabled      = (bool) $cloudflare_state;
			$this->cloudflare_tier         = ( is_numeric( $cloudflare_state ) && $cloudflare_state ) ? 'basic' : $cloudflare_state;
			$this->udev_api_services['cf'] = $this->cloudflare_tier;

			array_push( $this->cache_exempt, rest_get_url_prefix() );

			$this->hooks();
		}

		/**
		 * Retrieves the cache level from the database
		 *
		 * If cache level is set higher than 3, then it will reset it down to level 3
		 *
		 * @return int
		 */
		public function get_cache_level() {
			$level = absint( get_option( 'endurance_cache_level', 2 ) );

			if ( $level > 3 ) {
				$level = 3;
				update_option( 'endurance_cache_level', $level );
			}

			return $level;
		}

		/**
		 * Setup all WordPress actions and filters.
		 */
		public function hooks() {
			if ( $this->is_enabled( 'page' ) ) {
				add_action( 'init', array( $this, 'start' ) );
				add_action( 'shutdown', array( $this, 'finish' ) );
				add_action( 'shutdown', array( $this, 'shutdown' ) );

				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
				add_action( 'generate_rewrite_rules', array( $this, 'config_nginx' ) );
			}
			if ( $this->is_enabled( 'browser' ) ) {
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			}

			add_action( 'admin_init', array( $this, 'register_cache_settings' ) );
			add_action( 'transition_post_status', array( $this, 'save_post' ), 10, 3 );
			add_action( 'edit_terms', array( $this, 'edit_terms' ) );

			add_action( 'comment_post', array( $this, 'comment' ) );

			add_action( 'updated_option', array( $this, 'option_handler' ), 10, 3 );

			add_action( 'epc_purge', array( $this, 'purge_all' ) );
			add_action( 'epc_purge_request', array( $this, 'purge_request' ) );

			add_action( 'wp_update_nav_menu', array( $this, 'purge_all' ) );

			add_action( 'admin_bar_menu', array( $this, 'admin_toolbar' ), 99 );

			add_action( 'init', array( $this, 'do_purge' ) );

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'status_link' ) );

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update' ) );

			add_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10, 2 );
			add_filter( 'pre_update_option_endurance_cache_level', array( $this, 'cache_level_change' ), 10, 2 );

			add_filter( 'got_rewrite', array( $this, 'force_rewrite' ) );

			add_action( 'shutdown', array( $this, 'udev_cache_purge_via_buffer' ) );
		}

		/**
		 * Customize the WP Admin Bar.
		 *
		 * @param \WP_Admin_Bar $wp_admin_bar Instance of the admin bar.
		 */
		public function admin_toolbar( $wp_admin_bar ) {
			if ( current_user_can( 'manage_options' ) && $this->is_enabled() ) {
				$args = array(
					'id'    => 'epc_purge_menu',
					'title' => 'Caching',
				);
				$wp_admin_bar->add_node( $args );

				$args = array(
					'id'     => 'epc_purge_menu-purge_all',
					'title'  => 'Purge All',
					'parent' => 'epc_purge_menu',
					'href'   => add_query_arg( array( 'epc_purge_all' => true ) ),
				);
				$wp_admin_bar->add_node( $args );

				if ( ! is_admin() ) {
					$args = array(
						'id'     => 'epc_purge_menu-purge_single',
						'title'  => 'Purge This Page',
						'parent' => 'epc_purge_menu',
						'href'   => add_query_arg( array( 'epc_purge_single' => true ) ),
					);
					$wp_admin_bar->add_node( $args );
				}

				$args = array(
					'id'     => 'epc_purge_menu-cache_settings',
					'title'  => 'Cache Settings',
					'parent' => 'epc_purge_menu',
					'href'   => admin_url( 'options-general.php#epc_settings' ),
				);
				$wp_admin_bar->add_node( $args );
			}
		}

		/**
		 * Register fields for cache settings.
		 */
		public function register_cache_settings() {
			$section_name = 'epc_settings_section';
			add_settings_section(
				$section_name,
				'<span id="epc_settings">Endurance Cache</span>',
				'__return_false',
				'general'
			);
			add_settings_field(
				'endurance_cache_level',
				'Cache Level',
				array( $this, 'output_cache_settings' ),
				'general',
				$section_name,
				array( 'field' => 'endurance_cache_level' )
			);
			register_setting( 'general', 'endurance_cache_level' );
		}

		/**
		 * Output the cache options.
		 *
		 * @param array $args Settings
		 */
		public function output_cache_settings( $args ) {
			$cache_level = absint( get_option( $args['field'], 2 ) );
			echo '<select name="' . esc_attr( $args['field'] ) . '">';
			$cache_levels = array(
				0 => 'Off',
				1 => 'Assets Only',
				2 => 'Normal',
				3 => 'Advanced',
			);
			foreach ( $cache_levels as $i => $label ) {
				if ( $i !== $cache_level ) {
					echo '<option value="' . absint( $i ) . '"">';
				} else {
					echo '<option value="' . absint( $i ) . '" selected="selected">';
				}

				echo esc_html( $label ) . ' (Level ' . absint( $i ) . ')';
				echo '</option>';
			}
			echo '</select>';
		}

		/**
		 * Convert a string to studly case.
		 *
		 * @param string $value String to be converted.
		 *
		 * @return string
		 */
		public function to_studly_case( $value ) {
			return str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $value ) ) );
		}

		/**
		 * Convert a string to snake case.
		 *
		 * @param string $value String to be converted.
		 * @param string $delimiter Delimiter (can be a dash for conversion to kebab case).
		 *
		 * @return string
		 */
		public function to_snake_case( $value, $delimiter = '_' ) {
			if ( ! ctype_lower( $value ) ) {
				$value = preg_replace( '/(\s+)/u', '', ucwords( $value ) );
				$value = trim( mb_strtolower( preg_replace( '/([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)/u', '$1' . $delimiter, $value ), 'UTF-8' ), $delimiter );
			}

			return $value;
		}

		/**
		 * Checks if this environment caches requests on the current filesystem
		 *
		 * @return bool True if uses file system to cache
		 */
		public function use_file_cache() {
			return false === strpos( dirname( __FILE__ ), 'public_html' );
		}

		/**
		 * Handlers that listens for changes to options and checks to see, based on the option name, if the cache should
		 * be purged.
		 *
		 * @param string $option Option name
		 * @param mixed  $old_value Old option value
		 * @param mixed  $new_value New option value
		 *
		 * @return bool
		 */
		public function option_handler( $option, $old_value, $new_value ) {

			// No need to process if nothing was updated
			if ( $old_value === $new_value ) {
				return false;
			}

			$exempt_if_equals = array(
				'active_plugins'    => true,
				'html_type'         => true,
				'fs_accounts'       => true,
				'rewrite_rules'     => true,
				'uninstall_plugins' => true,
				'wp_user_roles'     => true,
			);

			// If we have an exact match, we can just stop here.
			if ( array_key_exists( $option, $exempt_if_equals ) ) {
				return false;
			}

			$force_if_contains = array(
				'html',
				'css',
				'style',
				'query',
				'queries',
			);

			$exempt_if_contains = array(
				'_active',
				'_activated',
				'_activation',
				'_attempts',
				'_available',
				'_blacklist',
				'_cache_validator',
				'_check_',
				'_checksum',
				'_config',
				'_count',
				'_dectivated',
				'_disable',
				'_enable',
				'_errors',
				'_hash',
				'_inactive',
				'_installed',
				'_key',
				'_last_',
				'_license',
				'_log_',
				'_mode',
				'_options',
				'_pageviews',
				'_redirects',
				'_rules',
				'_schedule',
				'_session',
				'_settings',
				'_shown',
				'_stats',
				'_status',
				'_statistics',
				'_supports',
				'_sync',
				'_task',
				'_time',
				'_token',
				'_traffic',
				'_transient',
				'_url_',
				'_version',
				'_views',
				'_visits',
				'_whitelist',
				'404s',
				'cron',
				'limit_login_',
				'nonce',
				'user_roles',
			);

			$force_purge = false;

			if ( ctype_upper( str_replace( array( '-', '_' ), '', $option ) ) ) {
				$option = strtolower( $option );
			}
			$option_name = '_' . $this->to_snake_case( $this->to_studly_case( $option ) ) . '_';

			foreach ( $force_if_contains as $slug ) {
				if ( false !== strpos( $option_name, $slug ) ) {
					$force_purge = true;
					break;
				}
			}

			if ( ! $force_purge ) {
				foreach ( $exempt_if_contains as $slug ) {
					if ( false !== strpos( $option_name, $slug ) ) {
						return false;
					}
				}
			}

			$this->add_trigger( 'option_update_' . $option );
			$this->purge_all();

			return true;
		}

		/**
		 * Purge single post when a comment is updated.
		 *
		 * @param int $comment_id ID of the comment.
		 */
		public function comment( $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment && property_exists( $comment, 'comment_post_ID' ) ) {
				$post_url = get_permalink( $comment->comment_post_ID );
				$this->purge_single( $post_url );
			}
		}

		/**
		 * Purge appropriate caches when post when post is updated.
		 *
		 * @param string  $old_status The previous post status
		 * @param string  $new_status The new post status
		 * @param WP_Post $post The post object of the edited or created post
		 */
		public function save_post( $old_status, $new_status, $post ) {

			// Skip purging for non-public post types
			if ( ! get_post_type_object( $post->post_type )->public ) {
				return;
			}

			// Skip purging if the post wasn't public before and isn't now
			if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
				return;
			}

			// Purge post URL when post is updated.
			$permalink = get_permalink( $post );
			if ( $permalink ) {
				$this->purge_single( $permalink );
			}

			// Purge taxonomy term URLs for related terms.
			$taxonomies = get_post_taxonomies( $post );
			foreach ( $taxonomies as $taxonomy ) {
				if ( $this->is_public_taxonomy( $taxonomy ) ) {
					$terms = get_the_terms( $post, $taxonomy );
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$term_link = get_term_link( $term );
							$this->purge_single( $term_link );
						}
					}
				}
			}

			// Purge post type archive URL when post is updated.
			$post_type_archive = get_post_type_archive_link( $post->post_type );
			if ( $post_type_archive ) {
				$this->purge_single( $post_type_archive );
			}

			// Purge date archive URL when post is updated.
			$year_archive      = get_year_link( (int) get_the_date( 'y', $post ) );
			$year_archive_path = str_replace( get_site_url(), '', $year_archive );
			$this->purge_dir( $year_archive_path );

		}

		/**
		 * Checks if a post is public.
		 *
		 * @param int $post_id The post ID.
		 *
		 * @return boolean
		 */
		public function is_public_post( $post_id ) {
			$public = false;
			if ( false === wp_is_post_autosave( $post_id ) ) {
				$post_type = get_post_type( $post_id );
				if ( $post_type ) {
					$post_type_object = get_post_type_object( $post_type );
					if ( $post_type_object && isset( $post_type_object->public ) ) {
						$public = $post_type_object->public;
					}
				}
			}

			return $public;
		}

		/**
		 * Checks if a taxonomy is public.
		 *
		 * @param string $taxonomy Taxonomy name.
		 *
		 * @return boolean
		 */
		public function is_public_taxonomy( $taxonomy ) {
			$public          = false;
			$taxonomy_object = get_taxonomy( $taxonomy );
			if ( $taxonomy_object && isset( $taxonomy_object->public ) ) {
				$public = $taxonomy_object->public;
			}

			return $public;
		}

		/**
		 * Purge taxonomy term URL when a term is updated.
		 *
		 * @param int $term_id Term ID
		 */
		public function edit_terms( $term_id ) {
			$url = get_term_link( $term_id );
			if ( ! is_wp_error( $url ) ) {
				$this->purge_single( $url );
			}
		}

		/**
		 * Write page content to cache.
		 *
		 * @param string $page Page content to be cached.
		 *
		 * @return string
		 */
		public function write( $page ) {
			$base = wp_parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );

			if ( false === strpos( $page, 'nonce' ) && ! empty( $page ) ) {
				$path = WP_CONTENT_DIR . '/endurance-page-cache' . str_replace( get_option( 'home' ), '', esc_url( $_SERVER['REQUEST_URI'] ) );
				$path = str_replace( '/endurance-page-cache' . $base, '/endurance-page-cache/', $path );
				$path = str_replace( '//', '/', $path );

				if ( file_exists( $path . '_index.html' ) && filemtime( $path . '_index.html' ) > time() - HOUR_IN_SECONDS ) {
					return $page;
				}

				if ( false !== strpos( $page, '</html>' ) ) {
					$page .= "\n<!--Generated by Endurance Page Cache-->";
				}

				if ( $this->use_file_cache() ) {
					if ( ! is_dir( $path ) ) {
						mkdir( $path, 0755, true );
					}
					file_put_contents( $path . '_index.html', $page, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			} else {
				nocache_headers();
			}

			return $page;
		}

		/**
		 * Make a request to purge the entire Sitelock CDN
		 */
		public function purge_cdn() {

			if ( ! $this->force_purge && true === $this->should_throttle( 'sitelock_cdn', __METHOD__ ) ) {
				return;
			}

			if ( true === $this->cloudflare_enabled ) {
				return;
			}

			if ( 'BlueHost' === get_option( 'mm_brand' ) ) {
				$endpoint      = 'https://my.bluehost.com/cgi/wpapi/cdn_purge';
				$domain        = wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
				$query         = add_query_arg( array( 'domain' => $domain ), $endpoint );
				$refresh_token = get_option( '_mm_refresh_token' );
				if ( false === $refresh_token ) {
					return;
				}
				$path = ABSPATH;
				$path = explode( 'public_html/', $path );
				if ( 2 === count( $path ) ) {
					$path = '/public_html/' . $path[1];
				} else {
					return;
				}

				$path_hash = bin2hex( $path );
				$headers   = array(
					'x-api-refresh-token' => $refresh_token,
					'x-api-path'          => $path_hash,
				);
				$args      = array(
					'timeout'  => 1,
					'blocking' => false,
					'headers'  => $headers,
				);
				wp_remote_get( $query, $args );
			}
		}

		/**
		 * Purge CDN based on pattern.
		 *
		 * A purge pattern is any string of literal characters, and will be searched for within filenames. For example,
		 * a pattern of "ndex" will match "index.html" and "spandex.php". For more fine-grained control, it is possible
		 * to specify the standard PCRE anchor characters "^" and "$" at the beginning and/or end, respectively, of a
		 * pattern, in order to anchor to that portion of the string. For example, "html$" will match "index.html" but
		 * not "learn_html.php".
		 *
		 * @param string $pattern (Optional) Pattern used to match assets that should be purged.
		 */
		public function purge_cdn_single( $pattern = '' ) {

			if ( ! $this->force_purge && true === $this->should_throttle( $pattern, __METHOD__ ) ) {
				return;
			}

			if ( 'BlueHost' === get_option( 'mm_brand' ) ) {
				$pattern = rawurlencode( $pattern );
				$domain  = wp_parse_url( home_url(), PHP_URL_HOST );
				wp_remote_request(
					"https://my.bluehost.com/api/domains/{$domain}/caches/sitelock/{$pattern}",
					array(
						'method'   => 'PUT',
						'blocking' => false,
						'headers'  => array(
							'X-MOJO-TOKEN' => get_option( '_mm_refresh_token' ),
						),
					)
				);
			}
		}

		/**
		 * Ensure that a URI isn't purged more than once per minute.
		 *
		 * @param string $uri URI being purged
		 * @param string $type The type of throttling
		 *
		 * @return bool True if additional purges should be avoided, false otherwise.
		 */
		public function should_throttle( $uri, $type ) {

			$should_throttle = false;

			$this->should_update_throttled_items = true;

			$hash = md5( $uri );

			if ( isset( $this->throttled[ $type ], $this->throttled[ $type ][ $hash ] ) ) {
				if ( $this->is_timestamp_valid( $this->throttled[ $type ][ $hash ] ) ) {
					$should_throttle = true;
				}
			}

			if ( ! $should_throttle ) {
				$this->throttled[ $type ][ $hash ] = time() + MINUTE_IN_SECONDS;
			}

			return $should_throttle;
		}

		/**
		 * Actions that should take place when the page is done loading.
		 */
		public function shutdown() {
			if ( $this->should_update_throttled_items ) {
				$throttled = array();
				foreach ( $this->throttled as $type => $group ) {
					$throttled[ $type ] = array_filter( $group, array( $this, 'is_timestamp_valid' ) );
				}
				set_transient( 'epc_throttled', $throttled, 60 );
			}
		}

		/**
		 * Returns true when a timestamp is in the future, or false when it is in the past (expired).
		 *
		 * @param int $timestamp Timestamp
		 *
		 * @return bool
		 */
		public function is_timestamp_valid( $timestamp ) {
			return $timestamp > time();
		}

		/**
		 * Send a cache purge request.
		 *
		 * @param string $uri URI to be purged.
		 */
		public function purge_request( $uri ) {

			global $wp_version;

			if ( ! $this->force_purge && true === $this->should_throttle( $uri, __METHOD__ ) ) {
				return;
			}

			$domain = wp_parse_url( home_url(), PHP_URL_HOST );

			if ( empty( $this->triggers ) ) {
				$this->add_trigger( current_action() );
			}

			$args = array(
				'method'     => 'PURGE',
				'timeout'    => '5',
				'blocking'   => false,
				'sslverify'  => false,
				'headers'    => array(
					'host' => $domain,
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url() . '; EPC/v' . EPC_VERSION . '/' . $this->get_trigger(),
			);
			wp_remote_request( $this->get_purge_request_url( $uri, 'http' ), $args );
			wp_remote_request( $this->get_purge_request_url( $uri, 'https' ), $args );

			$this->udev_cache_populate_buffer( $uri );

			if ( preg_match( '/\.\*$/', $uri ) ) {
				$this->purge_cdn();
			}
		}

		/**
		 * Get URL to be used for purge requests.
		 *
		 * @param string $uri The original URI
		 * @param string $scheme The scheme to be used
		 *
		 * @return string
		 */
		public function get_purge_request_url( $uri, $scheme = 'http' ) {

			// Default scheme to http; only allow two values
			if ( 'http' !== $scheme && 'https' !== $scheme ) {
				$scheme = 'http';
			}

			$base = ( 'http' === $scheme ) ? 'http://127.0.0.1:8080' : 'https://127.0.0.1:8443';

			if ( 0 === strpos( $uri, '/' ) ) {
				return $base . $uri;
			}

			return str_replace( str_replace( wp_parse_url( home_url(), PHP_URL_PATH ), '', home_url() ), $base, $uri );
		}

		/**
		 * Purge everything in a specific directory.
		 *
		 * @param string|null $dir Directory to be purged
		 */
		public function purge_dir( $dir = null ) {

			if ( ! $this->force_purge && true === $this->should_throttle( $dir, __METHOD__ ) ) {
				return;
			}

			if ( $this->use_file_cache() ) {
				if ( is_null( $dir ) || ! is_dir( $dir ) ) {
					$dir = WP_CONTENT_DIR . '/endurance-page-cache';
				}
				$dir = str_replace( '_index.html', '', $dir );
				if ( is_dir( $dir ) ) {
					$files = scandir( $dir );
					if ( is_array( $files ) ) {
						$files = array_diff( $files, array( '.', '..' ) );
					}

					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							if ( is_dir( $dir . '/' . $file ) ) {
								$this->purge_dir( $dir . '/' . $file );
							} elseif ( file_exists( $dir . '/' . $file ) ) {
								unlink( $dir . '/' . $file );
							}
						}
						if ( 2 === count( scandir( $dir ) ) ) {
							rmdir( $dir );
						}
					}
				}
			} else {
				$this->purge_request( get_option( 'siteurl' ) . $dir . '/.*' );
			}
		}

		/**
		 * Purge the cache for entire site
		 */
		public function purge_all() {

			if ( ! $this->force_purge && true === $this->should_throttle( 'all', __METHOD__ ) ) {
				return;
			}

			if ( $this->use_file_cache() ) {
				$this->purge_dir();
			} else {
				$this->udev_purge_buffer = array();
				$this->purge_request( get_option( 'siteurl' ) . '/.*' );
			}
		}

		/**
		 * Purge a single URI.
		 *
		 * @param string $uri URI to be purged.
		 */
		public function purge_single( $uri ) {

			if ( ! $this->force_purge && true === $this->should_throttle( $uri, __METHOD__ ) ) {
				return;
			}

			$this->purge_request( $uri );
			$this->purge_request( home_url() );
			$cache_file = $this->uri_to_cache( $uri );

			// Purge CDN
			$path = wp_parse_url( $uri, PHP_URL_PATH );
			$this->purge_cdn_single( $path . '$' );

			// Purge Image Assets from CDN
			if ( file_exists( $cache_file ) ) {
				$content = file_get_contents( $cache_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( ! empty( $content ) ) {
					$image_urls = $this->extract_image_urls( $content );
					foreach ( $image_urls as $image_url ) {
						$this->purge_cdn_single( wp_parse_url( $image_url, PHP_URL_PATH ) . '$' );
						if ( ! empty( $this->udev_purge_buffer ) ) {
							$this->udev_purge_buffer[] = wp_parse_url( $image_url, PHP_URL_PATH );
						}
					}
				}
			}

			// Purge requested file
			if ( file_exists( $cache_file ) ) {
				unlink( $cache_file );
			}

			// Purge front page file
			if ( file_exists( $this->cache_dir . '/_index.html' ) ) {
				unlink( $this->cache_dir . '/_index.html' );
			}

		}

		/**
		 * Extract image URLs from post content.
		 *
		 * @param string $content The post content
		 *
		 * @return array
		 */
		public function extract_image_urls( $content ) {
			$urls = array();
			preg_match_all( '#<img src="(.*?)"#', $content, $matches );
			if ( isset( $matches, $matches[1] ) ) {
				$urls = $matches[1];
			}

			return $urls;
		}

		/**
		 * Get the URI to cache.
		 *
		 * @param string $uri URI
		 *
		 * @return string
		 */
		public function uri_to_cache( $uri ) {
			$path = str_replace( get_site_url(), '', $uri );

			return $this->cache_dir . $path . '_index.html';
		}

		/**
		 * Check if current request is cachable.
		 *
		 * @return bool
		 */
		public function is_cachable() {
			global $wp_query;

			$return = true;

			if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE === true ) {
				$return = false;
			} elseif ( defined( 'DOING_AJAX' ) ) {
				$return = false;
			} elseif ( 'private' === get_post_status() ) {
				$return = false;
			} elseif ( isset( $wp_query ) && is_404() ) {
				$return = false;
			} elseif ( is_admin() ) {
				$return = false;
			} elseif ( false === get_option( 'permalink_structure' ) ) {
				$return = false;
			} elseif ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
				$return = false;
			} elseif ( isset( $_GET ) && ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$return = false;
			} elseif ( isset( $_POST ) && ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$return = false;
			} elseif ( isset( $wp_query ) && is_feed() ) {
				$return = false;
			}

			if ( empty( $_SERVER['REQUEST_URI'] ) ) {
				$return = false;
			} else {
				$cache_exempt = apply_filters( 'epc_exempt_uri_contains', $this->cache_exempt );
				foreach ( $cache_exempt as $exclude ) {
					if ( false !== strpos( $_SERVER['REQUEST_URI'], $exclude ) ) {
						$return = false;
					}
				}
			}

			return (bool) apply_filters( 'epc_is_cachable', $return );
		}

		/**
		 * Start output buffering for cachable requests.
		 */
		public function start() {
			if ( $this->is_cachable() ) {
				ob_start( array( $this, 'write' ) );
			} else {
				nocache_headers();
			}
		}

		/**
		 * End output buffering for cachable requests.
		 */
		public function finish() {
			if ( $this->is_cachable() ) {
				if ( ob_get_contents() ) {
					ob_end_clean();
				}
			}
		}

		/**
		 * Modify the .htaccess file with custom rewrite rules based on caching level.
		 *
		 * @param string $rules .htaccess content
		 *
		 * @return string
		 */
		public function htaccess_contents_rewrites( $rules ) {
			if ( false === is_numeric( $this->cache_level ) ) {
				$this->cache_level = 2;
			}

			if ( $this->cache_level > 3 ) {
				$this->cache_level = 3;
			}

			$base      = wp_parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
			$cache_url = $base . str_replace( get_option( 'home' ), '', WP_CONTENT_URL . '/endurance-page-cache' );
			$cache_url = str_replace( '//', '/', $cache_url );
			$additions = "<ifModule mod_headers.c>\n" . 'Header set X-Endurance-Cache-Level "' . $this->cache_level . '"' . "\n</ifModule>\n";

			if ( $this->use_file_cache() ) {
				$additions .= 'Options -Indexes ' . "\n" . '
				<IfModule mod_rewrite.c>
					RewriteEngine On
					RewriteBase ' . $base . '
					RewriteRule ^' . $cache_url . '/ - [L]
					RewriteCond %{REQUEST_METHOD} !POST
					RewriteCond %{QUERY_STRING} !.*=.*
					RewriteCond %{HTTP_COOKIE} !(wordpress_test_cookie|comment_author|wp\-postpass|wordpress_logged_in|wptouch_switch_toggle|wp_woocommerce_session_) [NC]
					RewriteCond %{DOCUMENT_ROOT}' . $cache_url . '/$1/_index.html -f
					RewriteRule ^(.*)$ ' . $cache_url . '/$1/_index.html [L]
				</IfModule>' . "\n";
			}

			return $additions . $rules;
		}

		/**
		 * Modify the .htaccess file with custom expiration rules based on caching level.
		 *
		 * @param string $rules .htaccess content
		 *
		 * @return string
		 */
		public function htaccess_contents_expirations( $rules ) {
			$default_files = array(
				'image/jpg'       => '1 year',
				'image/jpeg'      => '1 year',
				'image/gif'       => '1 year',
				'image/png'       => '1 year',
				'text/css'        => '1 month',
				'application/pdf' => '1 month',
				'text/javascript' => '1 month',
				'text/html'       => '5 minutes',
			);

			$file_types = wp_parse_args( get_option( 'epc_filetype_expirations', array() ), $default_files );

			$additions = "<IfModule mod_expires.c>\n\tExpiresActive On\n\t";
			foreach ( $file_types as $file_type => $expires ) {
				if ( 'default' !== $file_type ) {
					$additions .= 'ExpiresByType ' . $file_type . ' "access plus ' . $expires . '"' . "\n\t";
				}
			}

			$additions .= "ExpiresByType image/x-icon \"access plus 1 year\"\n\t";
			if ( isset( $file_types['default'] ) ) {
				$additions .= 'ExpiresDefault "access plus ' . $file_types['default'] . "\"\n";
			} else {
				$additions .= "ExpiresDefault \"access plus 6 hours\"\n";
			}
			$additions .= "</IfModule>\n";

			return $additions . $rules;
		}

		/**
		 * Check if a specific caching type is enabled.
		 *
		 * @param string $type Caching type.
		 *
		 * @return bool
		 */
		public function is_enabled( $type = 'page' ) {

			$plugins = get_option( 'active_plugins', array() );
			if ( ! empty( $plugins ) ) {
				$plugins = implode( ' ', $plugins );
				if ( strpos( $plugins, 'cach' ) || strpos( $plugins, 'wp-rocket' ) ) {
					return false;
				}
			}

			$active_theme = array(
				'stylesheet' => get_option( 'stylesheet' ),
				'template'   => get_option( 'template' ),
			);

			$active_theme = implode( ' ', $active_theme );

			$incompatible_themes = array( 'headway', 'prophoto' );

			foreach ( $incompatible_themes as $theme ) {
				if ( false !== strpos( $active_theme, $theme ) ) {
					return false;
				}
			}

			$cache_settings = get_option( 'mm_cache_settings' );
			if ( 'page' === $type ) {
				if ( isset( $_GET['epc_toggle'] ) && is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
					$valid_values = array( 'enabled', 'disabled' );
					if ( in_array( $_GET['epc_toggle'], $valid_values, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
						$cache_settings['page'] = $_GET['epc_toggle']; // phpcs:ignore WordPress.Security.NonceVerification
						update_option( 'mm_cache_settings', $cache_settings );
						header( 'Location: ' . admin_url( 'plugins.php?plugin_status=mustuse' ) );
					}
				}
				if ( isset( $cache_settings['page'] ) && 'disabled' === $cache_settings['page'] ) {
					return false;
				} else {
					return true;
				}
			}

			if ( 'browser' === $type ) {
				if ( isset( $_GET['epc_toggle'] ) && is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
					$valid_values = array( 'enabled', 'disabled' );
					if ( in_array( $_GET['epc_toggle'], $valid_values, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
						$cache_settings['browser'] = $_GET['epc_toggle']; // phpcs:ignore WordPress.Security.NonceVerification
						update_option( 'mm_cache_settings', $cache_settings );
						header( 'Location: ' . admin_url( 'plugins.php?plugin_status=mustuse' ) );
					}
				}
				if ( isset( $cache_settings['browser'] ) && 'disabled' === $cache_settings['browser'] ) {
					return false;
				} else {
					return true;
				}
			}

			return false;
		}

		/**
		 * Add plugin action links.
		 *
		 * @param array $links Action links
		 *
		 * @return array
		 */
		public function status_link( $links ) {
			if ( $this->is_enabled() ) {
				$links[] = '<a href="' . add_query_arg( array( 'epc_toggle' => 'disabled' ) ) . '">Disable</a>';
			} else {
				$links[] = '<a href="' . add_query_arg( array( 'epc_toggle' => 'enabled' ) ) . '">Enable</a>';
			}
			$links[] = '<a href="' . add_query_arg( array( 'epc_purge_all' => 'true' ) ) . '">Purge Cache</a>';

			return $links;
		}

		/**
		 * Listens for purge actions and handles based on type.
		 */
		public function do_purge() {
			if ( ( isset( $_GET['epc_purge_all'] ) || isset( $_GET['epc_purge_single'] ) ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->force_purge = true;
				if ( isset( $_GET['epc_purge_all'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$this->add_trigger( 'toolbar_manual_all' );
					$this->purge_all();
				} else {
					$this->add_trigger( 'toolbar_manual_single' );
					$this->purge_single( $this->get_current_single_purge_url() );
				}
				header( 'Location: ' . remove_query_arg( array( 'epc_purge_single', 'epc_purge_all' ) ) );
			}
		}

		/**
		 * Get the current URI for a single purge request.
		 *
		 * @return string
		 */
		public function get_current_single_purge_url() {
			$host = str_replace( wp_parse_url( home_url(), PHP_URL_PATH ), '', home_url() );
			$path = remove_query_arg( array( 'epc_purge_single', 'epc_purge_all' ) );

			return $host . $path;
		}

		/**
		 * Update the appropriate option when cache settings are changed.
		 *
		 * @param array $new_cache_settings New cache settings
		 * @param array $old_cache_settings Old Cache settings
		 *
		 * @return array
		 */
		public function cache_type_change( $new_cache_settings, $old_cache_settings ) {
			$new_page_cache_value = 0;
			if ( is_array( $new_cache_settings ) && isset( $new_cache_settings['page'] ) ) {
				$new_page_cache_value = ( 'enabled' === $new_cache_settings['page'] ) ? 1 : 0;
			}
			if ( false === get_option( 'endurance_cache_level' ) ) {
				if ( 1 === $new_page_cache_value ) {
					update_option( 'endurance_cache_level', 2 );
				} else {
					update_option( 'endurance_cache_level', 0 );
				}
			}

			return $new_cache_settings;
		}

		/**
		 * Handle cache level change.
		 *
		 * @param int $new_cache_level New cache level
		 * @param int $old_cache_level Old cache level
		 *
		 * @return int
		 */
		public function cache_level_change( $new_cache_level, $old_cache_level ) {
			$cache_settings = get_option( 'mm_cache_settings' );
			if ( 0 === $new_cache_level ) {
				$cache_settings['page']    = 'disabled';
				$cache_settings['browser'] = 'disabled';
			} else {
				$cache_settings['page']    = 'enabled';
				$cache_settings['browser'] = 'enabled';
			}
			remove_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10 );
			update_option( 'mm_cache_settings', $cache_settings );
			add_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10, 2 );
			$this->cache_level = $new_cache_level;
			$this->toggle_nginx( $new_cache_level );
			$this->update_level_expirations( $new_cache_level );

			return (int) $new_cache_level;
		}

		/**
		 * Update cache expirations rules in .htaccess based on cache level.
		 *
		 * @param int $level Cache level
		 */
		public function update_level_expirations( $level ) {
			$level                = (int) $level;
			$original_expirations = get_option( 'epc_filetype_expirations', array() );
			switch ( $level ) {
				case 3:
					$new_expirations = array(
						'image/jpg'       => '1 week',
						'image/jpeg'      => '1 week',
						'image/gif'       => '1 week',
						'image/png'       => '1 week',
						'text/css'        => '1 week',
						'application/pdf' => '1 week',
						'text/javascript' => '1 month',
						'text/html'       => '4 hours',
						'default'         => '1 week',
					);
					break;

				case 2:
					$new_expirations = array(
						'image/jpg'       => '24 hours',
						'image/jpeg'      => '24 hours',
						'image/gif'       => '24 hours',
						'image/png'       => '24 hours',
						'text/css'        => '24 hours',
						'application/pdf' => '1 week',
						'text/javascript' => '24 hours',
						'text/html'       => '5 minutes',
						'default'         => '24 hours',
					);
					break;

				case 1:
					$new_expirations = array(
						'image/jpg'       => '1 hour',
						'image/jpeg'      => '1 hour',
						'image/gif'       => '1 hour',
						'image/png'       => '1 hour',
						'text/css'        => '1 hour',
						'application/pdf' => '6 hours',
						'text/javascript' => '1 hour',
						'text/html'       => '0 seconds',
						'default'         => '5 minutes',
					);
					break;

				default:
					$new_expirations = array();
					break;
			}
			$expirations = wp_parse_args( $new_expirations, $original_expirations );

			if ( 0 === $level ) {
				delete_option( 'epc_filetype_expirations' );
				remove_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
				remove_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			} else {
				update_option( 'epc_filetype_expirations', $expirations );
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			}
			save_mod_rewrite_rules();
		}

		/**
		 * Configure caching in nginx.
		 */
		public function config_nginx() {
			$this->toggle_nginx( $this->cache_level );
		}

		/**
		 * Toggle nginx caching.
		 *
		 * @param int $new_value Cache level
		 */
		public function toggle_nginx( $new_value = 0 ) {
			if ( ! $this->use_file_cache() ) {
				$domain = wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
				$domain = str_replace( 'www.', '', $domain );
				$path   = explode( 'public_html', dirname( __FILE__ ) );
				if ( 2 !== count( $path ) ) {
					return;
				}
				$user = basename( $path[0] );
				$path = $path[0];
				if ( ! is_dir( $path . '.cpanel/proxy_conf' ) ) {
					mkdir( $path . '.cpanel/proxy_conf' );
				}

				if ( true === $this->cloudflare_enabled ) {
					$new_value = '-1';
				}
				@file_put_contents( $path . '.cpanel/proxy_conf/' . $domain, 'cache_level=' . $new_value ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
				@touch( '/etc/proxy_notify/' . $user ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		/**
		 * Handle checking for plugin updates.
		 *
		 * @param \stdClass $checked_data Plugin update data.
		 *
		 * @return \stdClass
		 */
		public function update( $checked_data ) {

			$muplugins_details = get_transient( 'mojo_plugin_assets' );

			if ( ! $muplugins_details ) {
				$muplugins_details = wp_remote_get( 'https://api.mojomarketplace.com/mojo-plugin-assets/json/mu-plugins.json' );
				if ( ! is_wp_error( $muplugins_details ) ) {
					set_transient( 'mojo_plugin_assets', $muplugins_details, 6 * HOUR_IN_SECONDS );
				}
			}

			if ( is_wp_error( $muplugins_details ) || ! isset( $muplugins_details['body'] ) ) {
				return $checked_data;
			}

			$mu_plugin = json_decode( $muplugins_details['body'], true );

			if ( ! is_null( $mu_plugin ) ) {
				foreach ( $mu_plugin as $slug => $info ) {
					if ( isset( $info['constant'] ) && defined( $info['constant'] ) ) {
						if ( version_compare( $info['version'], constant( $info['constant'] ), '>' ) ) {
							$file = wp_remote_get( $info['source'] );
							if ( ! is_wp_error( $file ) && isset( $file['body'] ) && strpos( $file['body'], $info['constant'] ) && is_writable( WP_CONTENT_DIR . $info['destination'] ) ) {
								file_put_contents( WP_CONTENT_DIR . $info['destination'], $file['body'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
							}
						}
					}
				}
			}

			return $checked_data;
		}

		/**
		 * Filter to force got_mod_rewrite() to true
		 *
		 * On CLI requests, mod_rewrite is unavailable, so it fails to update
		 * the .htaccess file when save_mod_rewrite_rules() is called. This
		 * forces that to be true so updates from WP CLI work.
		 *
		 * @param bool $got_rewrite Value of apache_mod_loaded('mod_rewrite')
		 *
		 * @return bool true for WP CLI requests
		 */
		public function force_rewrite( $got_rewrite ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return true;
			}

			return $got_rewrite;
		}

		/**
		 * Add trigger for record keeping.
		 *
		 * @param string $trigger Typically an action but can be manually set in the event of a force purge.
		 *
		 * @return void
		 */
		protected function add_trigger( $trigger ) {
			$this->triggers[] = $trigger;
		}

		/**
		 * Retrieves the most recent trigger
		 *
		 * @return string of the most recent trigger from the collection
		 */
		protected function get_trigger() {
			return end( $this->triggers );
		}

		/**
		 * Retrieves all the triggers to send with bundled requests
		 *
		 * @param string $include_duplicates Determines if the array should be unique
		 *
		 * @return array
		 */
		protected function get_triggers( $include_duplicates = false ) {
			if ( ! $include_duplicates ) {
				return array_values( array_unique( $this->triggers ) );
			} else {
				return $this->triggers;
			}
		}

		/**
		 * Primary function for the UDEV Purge Cache API. Makes non-blocking request for current install cache purges.
		 *
		 * Calling this method with *no* parameters triggers a full cache wipe for the domain.
		 * Calling this method with relative paths to resources will purge just those resources.
		 *
		 * @param array $resources (Site paths, image assets, scripts, styles, files, etc)
		 * @param array $override_services (see defaults on self::$udev_api_services)
		 * @return void
		 */
		protected function udev_cache_purge( $resources = array(), $override_services = array() ) {
			global $wp_version;

			$brand = get_option( 'mm_brand' );

			if ( $this->use_file_cache()
				|| ! in_array( $brand, $this->cloudflare_support, true )
				|| false === $this->cloudflare_enabled
			) {
				return;
			}

			$throttle_key = md5( wp_json_encode( $resources ) );

			if ( ! $this->force_purge && true === $this->should_throttle( $throttle_key, __METHOD__ ) ) {
				return;
			}

			$hosts    = array( wp_parse_url( home_url(), PHP_URL_HOST ) );
			$services = ! empty( $override_services ) ? $override_services : self::$udev_api_services;

			if ( $services['cf'] && $this->cloudflare_enabled ) {
				$services['cf'] = $this->cloudflare_enabled;
			}

			wp_remote_post(
				$this->udev_cache_api_uri( $services ),
				array(
					'blocking'   => false,
					'body'       => $this->udev_create_request_body( $hosts, $resources ),
					'compress'   => true,
					'headers'    => array(
						'X-EPC-PLUGIN-PURGE' => 1,
						'content-type'       => 'application/json',
					),
					'sslverify'  => false,
					'user-agent' => 'WordPress/' . $wp_version . '; ' . wp_parse_url( home_url(), PHP_URL_HOST ) . '; EPC/v' . EPC_VERSION,
				)
			);
		}

		/**
		 * Build request URL and params for UDEV Purge Cache API.
		 *
		 * @param array $services List of services
		 * @return string URI to use for the udev cache API
		 */
		protected function udev_cache_api_uri( $services ) {
			return trailingslashit( static::$udev_api_root )
					. trailingslashit( static::$udev_api_version )
					. static::$udev_api_endpoint
					. '?'
					. http_build_query( $services );
		}

		/**
		 * Take hosts (and perhaps specific resources) to purge and encode JSON for request body.
		 *
		 * @param array $hosts List of hosts
		 * @param array $resources List of resources
		 * @return string|false
		 */
		protected function udev_create_request_body( $hosts, $resources ) {
			$request = array( 'hosts' => $hosts );

			if ( ! empty( $resources ) ) {
				$request['assets'] = array_values( array_unique( array_filter( $resources ) ) );
			}

			$request['triggers'] = $this->triggers;

			return wp_json_encode( $request );
		}

		/**
		 * Takes full URI and adds to $this->udev_purge_buffer. Typically fires in $this->purge_request().
		 * Note that $this->purge_all() presets an empty array, which denotes a full domain purge.
		 *
		 * @param string $uri URI to add to udev purge buffer
		 * @return void
		 */
		protected function udev_cache_populate_buffer( $uri ) {
			if ( is_array( $this->udev_purge_buffer ) && ! empty( $this->udev_purge_buffer ) ) {
				$this->udev_purge_buffer[] = wp_parse_url( $uri, PHP_URL_PATH );
			} elseif ( false === $this->udev_purge_buffer ) {
				$this->udev_purge_buffer = array( wp_parse_url( $uri, PHP_URL_PATH ) );
			}
		}

		/**
		 * Takes all specified resources in $this->udev_purge_buffer (or empty array denoting full purge)
		 * and makes cache purge request to UDEV Cache API.
		 *
		 * @return void
		 */
		public function udev_cache_purge_via_buffer() {
			if ( ! empty( $this->udev_purge_buffer ) || is_array( $this->udev_purge_buffer ) ) {
				$this->udev_cache_purge( $this->udev_purge_buffer );
			}
		}
	}

	$epc = new Endurance_Page_Cache();
}
