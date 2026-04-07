<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleRSVP_Ajax {

	/** Valid response values. */
	const VALID_RESPONSES = array( 'yes', 'no', 'maybe' );

	/** UUID v4 regex. */
	const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

	/** Max submissions from one device per minute. */
	const RATE_LIMIT = 10;

	public static function register() {
		foreach ( array( 'wp_ajax_', 'wp_ajax_nopriv_' ) as $prefix ) {
			add_action( $prefix . 'simplersvp_submit',        array( __CLASS__, 'handle_submit' ) );
			add_action( $prefix . 'simplersvp_get_counts',    array( __CLASS__, 'handle_get_counts' ) );
			add_action( $prefix . 'simplersvp_get_responses', array( __CLASS__, 'handle_get_responses' ) );
		}
	}

	/**
	 * Handle RSVP submission (POST).
	 */
	public static function handle_submit() {
		check_ajax_referer( 'simplersvp_nonce', 'nonce' );

		$post_id   = isset( $_POST['post_id'] )   ? absint( $_POST['post_id'] )                   : 0;
		$device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( wp_unslash( $_POST['device_id'] ) ) : '';
		$name      = isset( $_POST['name'] )      ? sanitize_text_field( wp_unslash( $_POST['name'] ) )      : '';
		$response  = isset( $_POST['response'] )  ? sanitize_key( $_POST['response'] )              : '';

		// Basic validation.
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
			return;
		}

		if ( ! $device_id || ! preg_match( self::UUID_PATTERN, $device_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid device ID.' ), 400 );
			return;
		}

		if ( ! in_array( $response, self::VALID_RESPONSES, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid response value.' ), 400 );
			return;
		}

		// Confirm the post actually exists and is published.
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Post not found.' ), 404 );
			return;
		}

		// Rate limiting: max RATE_LIMIT requests per device per minute.
		$rate_key = 'srsvp_rate_' . md5( $device_id );
		$hits     = (int) get_transient( $rate_key );
		if ( $hits >= self::RATE_LIMIT ) {
			wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment.' ), 429 );
			return;
		}
		set_transient( $rate_key, $hits + 1, MINUTE_IN_SECONDS );

		SimpleRSVP_Database::save( $post_id, $device_id, $name, $response );
		$counts = SimpleRSVP_Database::get_counts( $post_id );

		wp_send_json_success( array( 'counts' => $counts ) );
	}

	/**
	 * Return current counts (and this device's existing response) for a post (GET).
	 */
	public static function handle_get_counts() {
		check_ajax_referer( 'simplersvp_nonce', 'nonce' );

		$post_id   = isset( $_GET['post_id'] )   ? absint( $_GET['post_id'] )                    : 0;
		$device_id = isset( $_GET['device_id'] ) ? sanitize_text_field( wp_unslash( $_GET['device_id'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
			return;
		}

		$counts   = SimpleRSVP_Database::get_counts( $post_id );
		$existing = '';
		$name     = '';

		if ( $device_id && preg_match( self::UUID_PATTERN, $device_id ) ) {
			$existing = (string) SimpleRSVP_Database::get_response( $post_id, $device_id );
			$name     = SimpleRSVP_Database::get_name( $post_id, $device_id );
		}

		wp_send_json_success( array(
			'counts'   => $counts,
			'response' => $existing,
			'name'     => $name,
		) );
	}

	/**
	 * Return the public list of names + responses for a post (GET).
	 *
	 * Device IDs are never exposed — only display name and response value.
	 */
	public static function handle_get_responses() {
		check_ajax_referer( 'simplersvp_nonce', 'nonce' );

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post.' ), 400 );
			return;
		}

		$rows      = SimpleRSVP_Database::get_all_for_post( $post_id );
		$responses = array_map( function ( $row ) {
			return array(
				'name'     => $row['name'],
				'response' => $row['response'],
			);
		}, $rows );

		wp_send_json_success( array( 'responses' => $responses ) );
	}
}
