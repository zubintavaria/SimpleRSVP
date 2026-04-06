<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleRSVP_Database {

	const TABLE_SUFFIX = 'simplersvp';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the RSVP table. Called on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id     BIGINT UNSIGNED NOT NULL,
			device_id   VARCHAR(64)     NOT NULL,
			name        VARCHAR(100)    NOT NULL DEFAULT '',
			response    VARCHAR(10)     NOT NULL,
			created_at  DATETIME        NOT NULL,
			updated_at  DATETIME        NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY   unique_rsvp (post_id, device_id),
			KEY          idx_post_id  (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert or update an RSVP entry.
	 *
	 * @param int    $post_id
	 * @param string $device_id  UUID v4 from client localStorage.
	 * @param string $name       Optional display name (may be empty).
	 * @param string $response   'yes' | 'no' | 'maybe'
	 */
	public static function save( $post_id, $device_id, $name, $response ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE post_id = %d AND device_id = %s",
			$post_id,
			$device_id
		) );

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				array(
					'response'   => $response,
					'name'       => $name,
					'updated_at' => $now,
				),
				array(
					'post_id'   => $post_id,
					'device_id' => $device_id,
				),
				array( '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'post_id'    => $post_id,
					'device_id'  => $device_id,
					'name'       => $name,
					'response'   => $response,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Return counts grouped by response for a post.
	 *
	 * @param  int   $post_id
	 * @return array { yes: int, no: int, maybe: int }
	 */
	public static function get_counts( $post_id ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT response, COUNT(*) AS count FROM {$table} WHERE post_id = %d GROUP BY response",
				$post_id
			),
			ARRAY_A
		);

		$counts = array( 'yes' => 0, 'no' => 0, 'maybe' => 0 );
		foreach ( $rows as $row ) {
			if ( array_key_exists( $row['response'], $counts ) ) {
				$counts[ $row['response'] ] = (int) $row['count'];
			}
		}
		return $counts;
	}

	/**
	 * Return the existing response for a device on a post, or null.
	 *
	 * @param  int    $post_id
	 * @param  string $device_id
	 * @return string|null
	 */
	public static function get_response( $post_id, $device_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT response FROM {$table} WHERE post_id = %d AND device_id = %s",
			$post_id,
			$device_id
		) );
	}

	/**
	 * Return the stored name for a device on a post, or empty string.
	 *
	 * @param  int    $post_id
	 * @param  string $device_id
	 * @return string
	 */
	public static function get_name( $post_id, $device_id ) {
		global $wpdb;
		$table = self::table();
		return (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$table} WHERE post_id = %d AND device_id = %s",
			$post_id,
			$device_id
		) );
	}

	/**
	 * Return all responses for a post, ordered by latest first.
	 *
	 * @param  int   $post_id
	 * @return array[]
	 */
	public static function get_all_for_post( $post_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, response, updated_at FROM {$table} WHERE post_id = %d ORDER BY updated_at DESC",
				$post_id
			),
			ARRAY_A
		);
	}

	/**
	 * Delete all RSVP responses for a given post (reset to zero).
	 *
	 * @param int $post_id
	 */
	public static function delete_for_post( $post_id ) {
		global $wpdb;
		$table = self::table();
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Return all posts that have at least one RSVP, with total counts.
	 *
	 * @return array[]
	 */
	public static function get_posts_with_rsvps() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			"SELECT post_id, COUNT(*) AS total FROM {$table} GROUP BY post_id ORDER BY total DESC",
			ARRAY_A
		);
	}
}
