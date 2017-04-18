<?php
/*
Plugin Name: Endurance Page Cache
Description: This cache plugin is primarily for cache purging of the additional layers of cache that may be available on a Bluehost account.
Version: 0.6
Author: Mike Hansen
Author URI: https://www.mikehansen.me/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Do not access file directly!
if ( ! defined( 'WPINC' ) ) { die; }

define( 'EPC_VERSION', 0.6 );

if ( ! class_exists( 'Endurance_Page_Cache' ) ) {
	class Endurance_Page_Cache {
		function __construct() {
			if ( defined( 'DOING_AJAX' ) ) {return;}
			$this->hooks();
			$this->purged = array();
			$this->cache_level = get_option( 'endurance_cache_level', 2 );
			$this->cache_dir = WP_CONTENT_DIR . '/endurance-page-cache';
			$this->cache_exempt = array( 'wp-admin', '.', 'checkout', 'cart', 'wp-json', '%', '=', '@', '&', ':', ';' );
			if ( ! wp_next_scheduled( 'epc_purge' ) ) {
				wp_schedule_event( time() + ( HOUR_IN_SECONDS * 2 ), 'epc_weekly', 'epc_purge' );
			}
		}

		function hooks() {
			if ( $this->is_enabled( 'page' ) ) {
				add_action( 'init', array( $this, 'start' ) );
				add_action( 'shutdown', array( $this, 'finish' ) );

				add_filter( 'style_loader_src', array( $this, 'remove_wp_ver_css_js' ), 9999 );
				add_filter( 'script_loader_src', array( $this, 'remove_wp_ver_css_js' ), 9999 );

				add_action( 'admin_init', array( $this, 'register_cache_settings' ) );

				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
			}
			if ( $this->is_enabled( 'browser' ) ) {
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			}
			add_action( 'save_post', array( $this, 'save_post' ) );
			add_action( 'edit_terms', array( $this, 'edit_terms' ), 10, 2 );

			add_action( 'comment_post', array( $this, 'purge_all' ) );

			add_action( 'updated_option', array( $this, 'option_handler' ), 10, 3 );

			add_action( 'epc_purge', array( $this, 'purge_all' ) );
			add_action( 'epc_purge_request', array( $this, 'purge_request' ) );

			add_action( 'wp_update_nav_menu', array( $this, 'purge_all' ) );

			add_action( 'admin_init', array( $this, 'do_purge_all' ) );

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'status_link' ) );

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update' ) );

			add_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10, 2 );
			add_filter( 'pre_update_option_endurance_cache_level', array( $this, 'cache_level_change' ), 10, 2 );
		}

		function register_cache_settings() {
			$section_name = 'epc_settings_section';
			add_settings_section(
				$section_name,
				'Endurance Cache',
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

		function output_cache_settings( $args ) {
			$cache_level = get_option( $args['field'], 2 );
			echo "<select name='" . $args['field'] . "'>";
			for ( $i = 0; $i < 5; $i++ ) {
				if ( $i != $cache_level ) {
					echo "<option value='" . $i . "'>";
				} else {
					echo "<option value='" . $i . "' selected='selected'>";
				}

				echo $i;
				echo '</option>';
			}
			echo '</select>';
		}

		function purge_cron( $schedules ) {
			$schedules['epc_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => esc_html__( 'Weekly' ),
			);
			return $schedules;
		}

		function option_handler( $option, $old_value, $new_value ) {
			if ( false !== strpos( $option, '_transient' ) ) {
				return;
			}
			if ( $old_value !== $new_value ) {
				$this->purge_all();
			}
		}

		function save_post( $post_id ) {
			$url = get_permalink( $post_id );
			$this->purge_single( $url );

			$taxonomies = get_post_taxonomies( $post_id );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_link = get_term_link( $term );
						$this->purge_single( $term_link );
					}
				}
			}

			if ( $post_type_archive = get_post_type_archive_link( get_post_type( $post_id ) ) ) {
				$this->purge_single( $post_type_archive );
			}

			$post_date = (array) json_decode( get_the_date( '{"\y":"Y","\m":"m","\d":"d"}', $post_id ) );
			if ( ! empty( $post_date ) ) {
				$this->purge_all( $this->uri_to_cache( get_year_link( $post_date['y'] ) ) );
			}
		}

		function edit_terms( $term_id, $taxonomy ) {
			$url = get_term_link( $term_id );
			$this->purge_single( $url );
		}

		function write( $page ) {
			$base = parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );

			if ( false === strpos( $page, 'nonce' ) && ! empty( $page ) ) {
				$this->path = WP_CONTENT_DIR . '/endurance-page-cache' . str_replace( get_option( 'home' ), '', esc_url( $_SERVER['REQUEST_URI'] ) );
				$this->path = str_replace( '/endurance-page-cache' . $base, '/endurance-page-cache/', $this->path );
				$this->path = str_replace( '//', '/', $this->path );

				if ( file_exists( $this->path . '_index.html' ) && filemtime( $this->path . '_index.html' ) > time() - HOUR_IN_SECONDS ) {
					return $page;
				}

				if ( ! is_dir( $this->path ) ) {
					mkdir( $this->path, 0755, true );
				}

				if ( false !== strpos( $page, '</html>' ) ) {
					$page .= "\n<!--Generated by Endurance Page Cache-->";
				}
				if ( 'BlueHost' !== get_option( 'mm_brand' ) ) {
					file_put_contents( $this->path . '_index.html', $page, LOCK_EX );
				}
			} else {
				nocache_headers();
			}
			return $page;
		}

		function purge_cdn() {
			if ( 'BlueHost' === get_option( 'mm_brand' ) ) {
				$endpoint = 'https://my.bluehost.com/cgi/wpapi/cdn_purge';
				$domain = parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
				$query = add_query_arg( array( 'domain' => $domain ), $endpoint );
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
				$headers = array(
					'x-api-refresh-token' => $refresh_token,
					'x-api-path' => $path_hash,
				);
				$args = array(
					'timeout'     => 1,
					'blocking'    => false,
					'headers'     => $headers,
				);
				$response = wp_remote_get( $query, $args );
			}
		}

		function purge_throttle( $value ) {
			$purged = get_transient( 'epc_purged_' . md5( $value ) );
			if ( true == $purged || in_array( md5( $value ), $this->purged ) ) {
				wp_schedule_single_event( time() + 180, 'epc_purge_request', array( $value ) );
				return true;
			}
			set_transient( 'epc_purged_' . md5( $value ), time(), 60 );
			$this->purged[] = md5( $value );
			return false;
		}

		function purge_request( $uri ) {
			if ( true === $this->purge_throttle( $uri ) ) {
				return;
			}
			$siteurl = get_option( 'siteurl' );
			$uri = str_replace( $siteurl, 'http://127.0.0.1:8080', $uri );
			$args = array(
				'method' => 'PURGE',
				'headers' => array(
					'host'   => str_replace( array( 'http://', 'https://' ), '', $siteurl ),
				),
			);
			wp_remote_request( $uri, $args );
			if ( 'http://127.0.0.1:8080/.*' == $uri ) {
				$this->purge_cdn();
			}
		}

		function purge_all( $dir = null, $purge_request = true ) {

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
							$this->purge_all( $dir . '/' . $file, false );
						} elseif ( file_exists( $dir . '/' . $file ) ) {
							unlink( $dir . '/' . $file );
						}
					}
					if ( 2 === count( scandir( $dir ) ) ) {
						rmdir( $dir );
					}
				}
			}
			if ( true === $purge_request ) {
				$this->purge_request( get_option( 'siteurl' ) . '/.*' );
			}
		}

		function purge_single( $uri ) {
			$this->purge_request( $uri );
			$this->purge_request( get_option( 'siteurl' ) );
			$cache_file = $this->uri_to_cache( $uri );
			if ( file_exists( $cache_file ) ) {
				unlink( $cache_file );
			}
			if ( file_exists( $this->cache_dir . '/_index.html' ) ) {
				unlink( $this->cache_dir . '/_index.html' );
			}
		}

		function minify( $content ) {
			$content = str_replace( "\r", '', $content );
			$content = str_replace( "\n", '', $content );
			$content = str_replace( "\t", '', $content );
			$content = str_replace( '  ', ' ', $content );
			$content = trim( $content );
			return $content;
		}

		function uri_to_cache( $uri ) {
			$path = str_replace( get_site_url(), '', $uri );
			return $this->cache_dir . $path . '_index.html';
		}

		function is_cachable() {

			$return = true;

			if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE == true ) {
				$return = false;
			} elseif ( 'private' == get_post_status() ) {
				$return = false;
			} elseif ( is_404() ) {
				$return = false;
			} elseif ( is_admin() ) {
				$return = false;
			} elseif ( false === get_option( 'permalink_structure' ) ) {
				$return = false;
			} elseif ( is_user_logged_in() ) {
				$return = false;
			} elseif ( isset( $_GET ) && ! empty( $_GET ) ) {
				$return = false;
			} elseif ( isset( $_POST ) && ! empty( $_POST ) ) {
				$return = false;
			} elseif ( is_feed() ) {
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

			return apply_filters( 'epc_is_cachable', $return );
		}

		function start() {
			if ( $this->is_cachable() ) {
				ob_start( array( $this, 'write' ) );
			} else {
				nocache_headers();
			}
		}

		function finish() {
			if ( $this->is_cachable() ) {
				if ( ob_get_contents() ) {
					ob_end_clean();
				}
			}
		}

		function remove_wp_ver_css_js( $src ) {
			if ( strpos( $src, 'ver=' ) ) {
				$src = remove_query_arg( 'ver', $src );
			}
			return $src;
		}

		function htaccess_contents_rewrites( $rules ) {
			$base = parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
			$cache_url = $base . str_replace( get_option( 'home' ), '', WP_CONTENT_URL . '/endurance-page-cache' );
			$cache_url = str_replace( '//', '/', $cache_url );
			$additions = 'Options -Indexes
Header set X-Endurance-Cache-Level "' . $this->cache_level . '"
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
			return $additions . $rules;
		}

		function htaccess_contents_expirations( $rules ) {
			$default_files = array(
				'image/jpg'       => '1 year',
				'image/jpeg'      => '1 year',
				'image/gif'       => '1 year',
				'image/png'       => '1 year',
				'text/css'        => '1 month',
				'application/pdf' => '1 month',
				'text/javascript' => '1 month',
				'text/html'       => '2 hours',
			);

			$file_types = wp_parse_args( get_option( 'ebc_filetype_expirations', array() ), $default_files );

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

		function is_enabled( $type = 'page' ) {

			$plugins = implode( ' ', get_option( 'active_plugins', array() ) );
			if ( strpos( $plugins, 'cach' ) || strpos( $plugins, 'wp-rocket' ) ) {
				return false;
			}

			$active_theme = array(
				'stylesheet' => get_option( 'stylesheet' ),
				'template' => get_option( 'template' ),
			);

			$active_theme = implode( ' ', $active_theme );

			$incompatible_themes = array( 'headway', 'prophoto' );

			foreach ( $incompatible_themes as $theme ) {
				if ( false !== strpos( $active_theme, $theme ) ) {
					return false;
				}
			}

			$cache_settings = get_option( 'mm_cache_settings' );
			if ( 'page' == $type ) {
				if ( isset( $_GET['epc_toggle'] ) && is_admin() ) {
					$valid_values = array( 'enabled', 'disabled' );
					if ( in_array( $_GET['epc_toggle'], $valid_values ) ) {
						$cache_settings['page'] = $_GET['epc_toggle'];
						update_option( 'mm_cache_settings', $cache_settings );
						header( 'Location: ' . admin_url( 'plugins.php?plugin_status=mustuse' ) );
					}
				}
				if ( isset( $cache_settings['page'] ) && 'disabled' == $cache_settings['page'] ) {
					return false;
				} else {
					return true;
				}
			}

			if ( 'browser' == $type ) {
				if ( isset( $_GET['ebc_toggle'] ) && is_admin() ) {
					$valid_values = array( 'enabled', 'disabled' );
					if ( in_array( $_GET['ebc_toggle'], $valid_values ) ) {
						$cache_settings['browser'] = $_GET['ebc_toggle'];
						update_option( 'mm_cache_settings', $cache_settings );
						header( 'Location: ' . admin_url( 'plugins.php?plugin_status=mustuse' ) );
					}
				}
				if ( isset( $cache_settings['browser'] ) && 'disabled' == $cache_settings['browser'] ) {
					return false;
				} else {
					return true;
				}
			}
		}

		function status_link( $links ) {
			if ( $this->is_enabled() ) {
				$links[] = '<a href="' . add_query_arg( array( 'epc_toggle' => 'disabled' ) ) . '">Disable</a>';
			} else {
				$links[] = '<a href="' . add_query_arg( array( 'epc_toggle' => 'enabled' ) ) . '">Enable</a>';
			}
			$links[] = '<a href="' . add_query_arg( array( 'epc_purge_all' => 'true' ) ) . '">Purge Cache</a>';
			return $links;
		}

		function do_purge_all() {
			if ( isset( $_GET['epc_purge_all'] ) ) {
				$this->purge_all();
				header( 'Location: ' . admin_url( 'plugins.php?plugin_status=mustuse' ) );
			}
		}

		function cache_type_change( $new_cache_settings, $old_cache_settings ) {
			if ( is_array( $new_cache_settings ) && isset( $new_cache_settings['page'] ) ) {
				$new_page_cache_value = ( 'enabled' == $new_cache_settings['page'] ) ? 1 : 0;
			}
			if ( false === get_option( 'endurance_cache_level' ) ) {
				if ( 1 == $new_page_cache_value ) {
					update_option( 'endurance_cache_level', 2 );
				} else {
					update_option( 'endurance_cache_level', 0 );
				}
			}
			return $new_cache_settings;
		}

		function cache_level_change( $new_cache_level, $old_cache_level ) {
			$cache_settings = get_option( 'mm_cache_settings' );
			if ( 0 == $new_cache_level ) {
				$cache_settings['page'] = 'disabled';
				$cache_settings['browser'] = 'disabled';
			} else {
				$cache_settings['page'] = 'enabled';
				$cache_settings['browser'] = 'enabled';
			}
			remove_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10, 2 );
			update_option( 'mm_cache_settings', $cache_settings );
			add_filter( 'pre_update_option_mm_cache_settings', array( $this, 'cache_type_change' ), 10, 2 );
			$this->cache_level = $new_cache_level;
			$this->toggle_nginx( $new_cache_level );
			$this->update_level_expirations( $new_cache_level );
			return $new_cache_level;
		}

		function update_level_expirations( $level ) {
			$level = (int) $level;
			$original_expirations = get_option( 'ebc_filetype_expirations', array() );
			switch ( $level ) {
				case 4:
					$new_expirations = array(
						'image/jpg'       => '1 year',
						'image/jpeg'      => '1 year',
						'image/gif'       => '1 year',
						'image/png'       => '1 year',
						'application/pdf' => '1 month',
						'text/css'        => '1 year',
						'text/javascript' => '1 year',
						'text/html'       => '24 hours',
						'default'         => '1 week',
					);
					break;

				case 3:
					$new_expirations = array(
						'image/jpg'       => '1 week',
						'image/jpeg'      => '1 week',
						'image/gif'       => '1 week',
						'image/png'       => '1 week',
						'text/css'        => '1 week',
						'application/pdf' => '1 week',
						'text/javascript' => '1 month',
						'text/html'       => '3 hours',
						'default'         => '1 week',
					);
					break;

				case 2:
					$new_expirations = array(
						'image/jpg'       => '6 hours',
						'image/jpeg'      => '6 hours',
						'image/gif'       => '6 hours',
						'image/png'       => '6 hours',
						'text/css'        => '6 hours',
						'application/pdf' => '1 week',
						'text/javascript' => '6 hours',
						'text/html'       => '10 minutes',
						'default'         => '3 hours',
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
						'default'         => '10 minutes',
					);
					break;
			}
			$expirations = wp_parse_args( $new_expirations, $original_expirations );

			if ( 0 == $level ) {
				delete_option( 'ebc_filetype_expirations' );
				remove_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
				remove_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			} else {
				update_option( 'ebc_filetype_expirations', $expirations );
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_rewrites' ), 77 );
				add_filter( 'mod_rewrite_rules', array( $this, 'htaccess_contents_expirations' ), 88 );
			}
			save_mod_rewrite_rules();
		}

		function toggle_nginx( $new_value = 0 ) {
			if ( 'BlueHost' === get_option( 'mm_brand' ) ) {
				$domain = parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
				$domain = str_replace( 'www.', '', $domain );
				$path = explode( 'public_html', __DIR__ );
				if ( 2 !== count( $path ) ) {
					return;
				}
				$user = basename( $path[0] );
				$path = $path[0];
				if ( ! is_dir( $path . '.cpanel/proxy_conf' ) ) {
					mkdir( $path . '.cpanel/proxy_conf' );
				}
				@file_put_contents( $path . '.cpanel/proxy_conf/' . $domain, 'cache_level=' . $new_value );
				@touch( '/etc/proxy_notify/' . $user );
			}
		}

		function update( $checked_data ) {

			$muplugins_details = wp_remote_get( 'https://api.mojomarketplace.com/mojo-plugin-assets/json/mu-plugins.json' );

			if ( is_wp_error( $muplugins_details ) || ! isset( $muplugins_details['body'] ) ) {
				return;
			}

			$mu_plugin = json_decode( $muplugins_details['body'], true );

			if ( ! is_null( $mu_plugin ) ) {
				foreach ( $mu_plugin as $slug => $info ) {
					if ( isset( $info['constant'] ) && defined( $info['constant'] ) ) {
						if ( (float) $info['version'] > (float) constant( $info['constant'] ) ) {
							$file = wp_remote_get( $info['source'] );
							if ( ! is_wp_error( $file ) && isset( $file['body'] ) && strpos( $file['body'], $info['constant'] ) ) {
								file_put_contents( WP_CONTENT_DIR . $info['destination'], $file['body'] );
							}
						}
					}
				}
			}
			return $checked_data;
		}
	}
	$epc = new Endurance_Page_Cache;
}
