<?php
/**
 * Tests for SimpleRSVP_Database.
 *
 * All database I/O goes through the WpdbStub (set in bootstrap.php as
 * $GLOBALS['wpdb']).  Each test seeds the stub's queues with the values
 * the real MySQL would return, then asserts on what the Database class
 * does with those values.
 */

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

    private WpdbStub $wpdb;

    protected function setUp(): void {
        parent::setUp();
        // Reset the stub between tests.
        $GLOBALS['wpdb']          = new WpdbStub();
        $this->wpdb               = $GLOBALS['wpdb'];
        $GLOBALS['_srsvp_posts']  = [];
    }

    // ── table() helper ────────────────────────────────────────────────────

    public function test_table_name_uses_wp_prefix(): void {
        $table = SimpleRSVP_Database::table();
        $this->assertStringStartsWith( 'wp_', $table );
        $this->assertStringEndsWith( 'simplersvp', $table );
    }

    // ── save(): insert path ───────────────────────────────────────────────

    public function test_save_inserts_when_no_existing_row(): void {
        // get_var returns null → no existing row.
        $this->wpdb->__get_var_queue = [ null ];

        SimpleRSVP_Database::save( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Bob', 'yes' );

        $insertCall = $this->findCall( 'insert' );
        $this->assertNotNull( $insertCall, 'Expected an INSERT call.' );
        $this->assertSame( 10,    $insertCall['data']['post_id'] );
        $this->assertSame( 'Bob', $insertCall['data']['name'] );
        $this->assertSame( 'yes', $insertCall['data']['response'] );
        $this->assertArrayHasKey( 'created_at', $insertCall['data'] );
        $this->assertArrayHasKey( 'updated_at', $insertCall['data'] );
    }

    public function test_save_insert_stores_empty_name_when_none_given(): void {
        $this->wpdb->__get_var_queue = [ null ];

        SimpleRSVP_Database::save( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '', 'no' );

        $insertCall = $this->findCall( 'insert' );
        $this->assertSame( '', $insertCall['data']['name'] );
    }

    // ── save(): update path ───────────────────────────────────────────────

    public function test_save_updates_when_existing_row_found(): void {
        // get_var returns an id → existing row.
        $this->wpdb->__get_var_queue = [ '42' ];

        SimpleRSVP_Database::save( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Alice', 'maybe' );

        $updateCall = $this->findCall( 'update' );
        $this->assertNotNull( $updateCall, 'Expected an UPDATE call.' );
        $this->assertSame( 'maybe', $updateCall['data']['response'] );
        $this->assertSame( 'Alice', $updateCall['data']['name'] );
        $this->assertArrayHasKey( 'updated_at', $updateCall['data'] );
        // created_at must NOT be in the update data.
        $this->assertArrayNotHasKey( 'created_at', $updateCall['data'] );
    }

    public function test_save_update_where_clause_uses_post_id_and_device_id(): void {
        $this->wpdb->__get_var_queue = [ '5' ];

        SimpleRSVP_Database::save( 7, 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '', 'no' );

        $updateCall = $this->findCall( 'update' );
        $this->assertSame( 7,                                       $updateCall['where']['post_id'] );
        $this->assertSame( 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', $updateCall['where']['device_id'] );
    }

    // ── get_counts() ──────────────────────────────────────────────────────

    public function test_get_counts_aggregates_rows_correctly(): void {
        $this->wpdb->__get_results_queue = [
            [
                [ 'response' => 'yes',   'count' => '5' ],
                [ 'response' => 'no',    'count' => '2' ],
                [ 'response' => 'maybe', 'count' => '1' ],
            ],
        ];

        $counts = SimpleRSVP_Database::get_counts( 10 );

        $this->assertSame( 5, $counts['yes'] );
        $this->assertSame( 2, $counts['no'] );
        $this->assertSame( 1, $counts['maybe'] );
    }

    public function test_get_counts_returns_zero_for_missing_responses(): void {
        // Only 'yes' responses exist.
        $this->wpdb->__get_results_queue = [
            [ [ 'response' => 'yes', 'count' => '3' ] ],
        ];

        $counts = SimpleRSVP_Database::get_counts( 10 );

        $this->assertSame( 3, $counts['yes'] );
        $this->assertSame( 0, $counts['no'] );
        $this->assertSame( 0, $counts['maybe'] );
    }

    public function test_get_counts_returns_all_zeros_when_no_responses(): void {
        $this->wpdb->__get_results_queue = [ [] ];

        $counts = SimpleRSVP_Database::get_counts( 99 );

        $this->assertSame( [ 'yes' => 0, 'no' => 0, 'maybe' => 0 ], $counts );
    }

    public function test_get_counts_ignores_unknown_response_values(): void {
        $this->wpdb->__get_results_queue = [
            [
                [ 'response' => 'yes',     'count' => '2' ],
                [ 'response' => 'unknown', 'count' => '99' ],
            ],
        ];

        $counts = SimpleRSVP_Database::get_counts( 10 );

        $this->assertSame( 2,  $counts['yes'] );
        $this->assertSame( 0,  $counts['no'] );
        $this->assertArrayNotHasKey( 'unknown', $counts );
    }

    public function test_get_counts_returns_integer_types(): void {
        $this->wpdb->__get_results_queue = [
            [ [ 'response' => 'yes', 'count' => '7' ] ],
        ];

        $counts = SimpleRSVP_Database::get_counts( 1 );

        $this->assertIsInt( $counts['yes'] );
        $this->assertIsInt( $counts['no'] );
        $this->assertIsInt( $counts['maybe'] );
    }

    // ── get_response() ────────────────────────────────────────────────────

    public function test_get_response_returns_stored_value(): void {
        $this->wpdb->__get_var_queue = [ 'no' ];

        $result = SimpleRSVP_Database::get_response( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );

        $this->assertSame( 'no', $result );
    }

    public function test_get_response_returns_null_when_not_found(): void {
        $this->wpdb->__get_var_queue = [ null ];

        $result = SimpleRSVP_Database::get_response( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );

        $this->assertNull( $result );
    }

    // ── get_name() ────────────────────────────────────────────────────────

    public function test_get_name_returns_stored_name(): void {
        $this->wpdb->__get_var_queue = [ 'Charlie' ];

        $result = SimpleRSVP_Database::get_name( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );

        $this->assertSame( 'Charlie', $result );
    }

    public function test_get_name_returns_empty_string_when_null(): void {
        $this->wpdb->__get_var_queue = [ null ];

        $result = SimpleRSVP_Database::get_name( 10, 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );

        $this->assertSame( '', $result );
    }

    // ── get_all_for_post() ────────────────────────────────────────────────

    public function test_get_all_for_post_returns_rows_as_arrays(): void {
        $rows = [
            [ 'id' => '1', 'name' => 'Dave', 'response' => 'yes',   'updated_at' => '2025-06-01 10:00:00' ],
            [ 'id' => '2', 'name' => '',     'response' => 'maybe', 'updated_at' => '2025-06-01 09:00:00' ],
        ];
        $this->wpdb->__get_results_queue = [ $rows ];

        $result = SimpleRSVP_Database::get_all_for_post( 10 );

        $this->assertCount( 2, $result );
        $this->assertSame( 'Dave', $result[0]['name'] );
        $this->assertSame( 'yes',  $result[0]['response'] );
    }

    public function test_get_all_for_post_includes_id_column(): void {
        $rows = [
            [ 'id' => '42', 'name' => 'Eve', 'response' => 'no', 'updated_at' => '2025-06-01 10:00:00' ],
        ];
        $this->wpdb->__get_results_queue = [ $rows ];

        $result = SimpleRSVP_Database::get_all_for_post( 10 );

        $this->assertArrayHasKey( 'id', $result[0] );
        $this->assertSame( '42', $result[0]['id'] );
    }

    public function test_get_all_for_post_returns_empty_array_when_no_rows(): void {
        $this->wpdb->__get_results_queue = [ [] ];

        $result = SimpleRSVP_Database::get_all_for_post( 10 );

        $this->assertSame( [], $result );
    }

    // ── get_posts_with_rsvps() ────────────────────────────────────────────

    public function test_get_posts_with_rsvps_returns_rows(): void {
        $rows = [
            [ 'post_id' => '1', 'total' => '10' ],
            [ 'post_id' => '2', 'total' => '3'  ],
        ];
        $this->wpdb->__get_results_queue = [ $rows ];

        $result = SimpleRSVP_Database::get_posts_with_rsvps();

        $this->assertCount( 2, $result );
    }

    // ── delete_by_id() ───────────────────────────────────────────────────

    public function test_delete_by_id_issues_delete_query(): void {
        SimpleRSVP_Database::delete_by_id( 7 );

        $this->assertNotNull( $this->findCall( 'delete' ), 'Expected a DELETE call.' );
    }

    public function test_delete_by_id_targets_correct_id(): void {
        SimpleRSVP_Database::delete_by_id( 99 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertSame( 99, $deleteCall['where']['id'] );
    }

    public function test_delete_by_id_targets_correct_table(): void {
        SimpleRSVP_Database::delete_by_id( 1 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertStringEndsWith( 'simplersvp', $deleteCall['table'] );
    }

    public function test_delete_by_id_uses_id_not_post_id(): void {
        SimpleRSVP_Database::delete_by_id( 5 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertArrayHasKey( 'id',       $deleteCall['where'] );
        $this->assertArrayNotHasKey( 'post_id', $deleteCall['where'] );
    }

    // ── delete_for_post() ─────────────────────────────────────────────────

    public function test_delete_for_post_issues_delete_query(): void {
        SimpleRSVP_Database::delete_for_post( 42 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertNotNull( $deleteCall, 'Expected a DELETE call.' );
    }

    public function test_delete_for_post_targets_correct_post_id(): void {
        SimpleRSVP_Database::delete_for_post( 42 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertSame( 42, $deleteCall['where']['post_id'] );
    }

    public function test_delete_for_post_targets_correct_table(): void {
        SimpleRSVP_Database::delete_for_post( 1 );

        $deleteCall = $this->findCall( 'delete' );
        $this->assertStringContainsString( 'simplersvp', $deleteCall['table'] );
    }

    public function test_delete_for_post_does_not_affect_other_tables(): void {
        SimpleRSVP_Database::delete_for_post( 1 );

        $deleteCall = $this->findCall( 'delete' );
        // Table must be the plugin's own table, not an arbitrary WP table.
        $this->assertStringStartsWith( 'wp_', $deleteCall['table'] );
        $this->assertStringEndsWith( 'simplersvp', $deleteCall['table'] );
    }

    // ── Utility ───────────────────────────────────────────────────────────

    /** Find the first recorded call of a given method, or null. */
    private function findCall( string $method ): ?array {
        foreach ( $this->wpdb->__calls as $call ) {
            if ( $call['method'] === $method ) {
                return $call;
            }
        }
        return null;
    }
}
