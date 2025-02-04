<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting bootstrap process...\n";

// Load test configuration
echo "Loading test configuration...\n";
require_once __DIR__ . '/wp-tests-config.php';

// Load Composer dependencies
echo "Loading Composer dependencies...\n";
try {
    require dirname(__DIR__) . '/vendor/autoload.php';
    echo "Composer dependencies loaded.\n";
} catch (Throwable $e) {
    echo "Error loading Composer dependencies: " . $e->getMessage() . "\n";
    die();
}

// Load WordPress core
echo "Loading WordPress core...\n";
$wp_tests_dir = getenv('WP_PHPUNIT__DIR');
if (!$wp_tests_dir) {
    $wp_tests_dir = '/var/www/html/wp-content/plugins/endurance-page-cache/vendor/wp-phpunit/wp-phpunit';
}

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    die("WordPress test functions file not found in {$wp_tests_dir}\n");
}

require_once $wp_tests_dir . '/includes/functions.php';

function _manually_load_plugin()
{
    echo "Loading plugin from test environment...\n";
    $plugin_file = dirname(__DIR__) . '/endurance-page-cache.php';
    if (!file_exists($plugin_file)) {
        die("Plugin file not found at: {$plugin_file}\n");
    }
    require $plugin_file;
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Load WordPress test bootstrap
require $wp_tests_dir . '/includes/bootstrap.php';

echo "Bootstrap process completed.\n";
