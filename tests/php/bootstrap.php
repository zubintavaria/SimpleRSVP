<?php
/**
 * PHPUnit bootstrap — sets up the WordPress stubs, then loads the plugin
 * classes under test.
 */

require_once __DIR__ . '/wp-stubs.php';

// Instantiate the global $wpdb stub so plugin classes can `global $wpdb`.
$GLOBALS['wpdb'] = new WpdbStub();

// Load the plugin classes (they only define classes; no side-effects at
// include time thanks to the ABSPATH guard at the top of each file).
require_once __DIR__ . '/../../simplersvp/includes/class-database.php';
require_once __DIR__ . '/../../simplersvp/includes/class-ajax.php';
require_once __DIR__ . '/../../simplersvp/includes/class-shortcode.php';
