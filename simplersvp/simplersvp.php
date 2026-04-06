<?php
/**
 * Plugin Name: SimpleRSVP
 * Plugin URI:  https://github.com/zubintavaria/simplersvp
 * Description: Embed a simple RSVP widget on any post or page using [simplersvp].
 * Version:     1.1.0
 * Author:      SimpleRSVP
 * License:     GPL-2.0-or-later
 * Text Domain: simplersvp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLERSVP_VERSION', '1.1.0' );
define( 'SIMPLERSVP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLERSVP_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLERSVP_DIR . 'includes/class-database.php';
require_once SIMPLERSVP_DIR . 'includes/class-shortcode.php';
require_once SIMPLERSVP_DIR . 'includes/class-ajax.php';
require_once SIMPLERSVP_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'SimpleRSVP_Database', 'create_table' ) );

add_action( 'init', array( 'SimpleRSVP_Shortcode', 'register' ) );
add_action( 'wp_enqueue_scripts', 'simplersvp_enqueue_assets' );
add_action( 'admin_menu', array( 'SimpleRSVP_Admin', 'register_menu' ) );

SimpleRSVP_Ajax::register();

function simplersvp_enqueue_assets() {
	wp_register_style(
		'simplersvp',
		SIMPLERSVP_URL . 'assets/css/simplersvp.css',
		array(),
		SIMPLERSVP_VERSION
	);
	wp_register_script(
		'simplersvp',
		SIMPLERSVP_URL . 'assets/js/simplersvp.js',
		array(),
		SIMPLERSVP_VERSION,
		true
	);
	wp_localize_script( 'simplersvp', 'SimpleRSVP', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'simplersvp_nonce' ),
	) );
}
