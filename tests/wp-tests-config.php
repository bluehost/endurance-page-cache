<?php

// Test with a blog in standard subdirectory setup, and no multisite.
define('ABSPATH', '/var/www/html/');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');

define('WP_TESTS_DOMAIN', 'localhost:8871');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');

define('WP_TESTS_CONFIG_FILE_PATH', __FILE__);
define('WP_ENV_TESTS_PORT', 63887);

// Test suite configuration
define('DB_NAME', 'wordpress');
define('DB_USER', 'root');
define('DB_PASSWORD', 'password');
define('DB_HOST', 'mysql');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

// Test suite configuration for multisite
define('WP_TESTS_MULTISITE', false);

// Force known bugs to be run.
define('WP_TESTS_FORCE_KNOWN_BUGS', false);

// Test suite bootstrap
$wp_tests_dir = getenv('WP_PHPUNIT__DIR');
if (!$wp_tests_dir) {
    $wp_tests_dir = '/var/www/html/wp-content/plugins/endurance-page-cache/vendor/wp-phpunit/wp-phpunit';
}

require_once $wp_tests_dir . '/includes/functions.php';
