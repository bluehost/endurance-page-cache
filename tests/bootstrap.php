<?php

// Load up Composer dependencies
require dirname( __DIR__ ) . '/vendor/autoload.php';

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

// Load up test config file from WP-PHPUnit package
require $wp_phpunit_dir . '/wp-tests-config.php';

// Bootstrap tests
require $wp_phpunit_dir . '/includes/bootstrap.php';

// Load our plugin
require dirname( __DIR__ ) . '/endurance-page-cache.php';
