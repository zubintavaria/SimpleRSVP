<?php
/**
 * Tests for input validation logic in SimpleRSVP_Ajax.
 *
 * We exercise the handler by populating $_POST / $_GET then calling the
 * static method directly.  wp_send_json_* stubs record results in
 * $GLOBALS['_srsvp_last_json'] instead of dying, so we can assert on them.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AjaxValidationTest extends TestCase {

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Reset shared state before every test. */
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['_srsvp_last_json']  = null;
        $GLOBALS['_srsvp_transients'] = [];
        $GLOBALS['_srsvp_posts']      = [];
        $_POST = [];
        $_GET  = [];
    }

    /** Convenience: make a valid WP_Post-like object and register it. */
    private function registerPost( int $id, string $status = 'publish' ): void {
        $post              = new stdClass();
        $post->post_status = $status;
        $GLOBALS['_srsvp_posts'][ $id ] = $post;
    }

    private function lastJson(): ?array {
        return $GLOBALS['_srsvp_last_json'];
    }

    // ── UUID_PATTERN ─────────────────────────────────────────────────────

    public function test_uuid_pattern_constant_is_defined(): void {
        $this->assertIsString( SimpleRSVP_Ajax::UUID_PATTERN );
    }

    /** @return array<string, array{string}> */
    public static function validUuidProvider(): array {
        return [
            'lowercase v4'   => [ '550e8400-e29b-41d4-a716-446655440000' ],
            'uppercase v4'   => [ 'F47AC10B-58CC-4372-A567-0E02B2C3D479' ],
            'mixed case v4'  => [ 'f47ac10b-58cc-4372-a567-0e02b2c3d479' ],
            'variant 8 msb'  => [ 'a8098c1a-f86e-42ba-81d1-0e02b2c3d479' ],
            'variant 9 msb'  => [ 'a8098c1a-f86e-42ba-91d1-0e02b2c3d479' ],
            'variant a msb'  => [ 'a8098c1a-f86e-42ba-a1d1-0e02b2c3d479' ],
            'variant b msb'  => [ 'a8098c1a-f86e-42ba-b1d1-0e02b2c3d479' ],
        ];
    }

    #[DataProvider( 'validUuidProvider' )]
    public function test_uuid_pattern_accepts_valid_uuid( string $uuid ): void {
        $this->assertMatchesRegularExpression( SimpleRSVP_Ajax::UUID_PATTERN, $uuid );
    }

    /** @return array<string, array{string}> */
    public static function invalidUuidProvider(): array {
        return [
            'empty string'          => [ '' ],
            'random text'           => [ 'not-a-uuid' ],
            'missing hyphens'       => [ '550e8400e29b41d4a716446655440000' ],
            'uuid v3 (not v4)'      => [ '550e8400-e29b-31d4-a716-446655440000' ],
            'uuid v5 (not v4)'      => [ '550e8400-e29b-51d4-a716-446655440000' ],
            'wrong variant bits'    => [ '550e8400-e29b-41d4-0716-446655440000' ],
            'path traversal'        => [ '../../../etc/passwd' ],
            'sql injection'         => [ "1' OR '1'='1" ],
            'xss payload'           => [ '<script>alert(1)</script>' ],
            'null byte'             => [ "valid-uuid\x00extra" ],
            'too short'             => [ '550e8400-e29b-41d4-a716-44665544000' ],
            'too long'              => [ '550e8400-e29b-41d4-a716-4466554400001' ],
        ];
    }

    #[DataProvider( 'invalidUuidProvider' )]
    public function test_uuid_pattern_rejects_invalid( string $input ): void {
        $this->assertDoesNotMatchRegularExpression( SimpleRSVP_Ajax::UUID_PATTERN, $input );
    }

    // ── VALID_RESPONSES ───────────────────────────────────────────────────

    public function test_valid_responses_contains_expected_values(): void {
        $this->assertContains( 'yes',   SimpleRSVP_Ajax::VALID_RESPONSES );
        $this->assertContains( 'no',    SimpleRSVP_Ajax::VALID_RESPONSES );
        $this->assertContains( 'maybe', SimpleRSVP_Ajax::VALID_RESPONSES );
    }

    public function test_valid_responses_rejects_dangerous_values(): void {
        foreach ( [ '', 'delete', 'drop', '1', 'true', 'null', '<yes>' ] as $bad ) {
            $this->assertNotContains( $bad, SimpleRSVP_Ajax::VALID_RESPONSES, "'{$bad}' should not be a valid response" );
        }
    }

    public function test_valid_responses_has_exactly_three_entries(): void {
        $this->assertCount( 3, SimpleRSVP_Ajax::VALID_RESPONSES );
    }

    // ── handle_submit: post_id validation ────────────────────────────────

    public function test_submit_rejects_zero_post_id(): void {
        $_POST = [
            'post_id'   => '0',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $json = $this->lastJson();
        $this->assertFalse( $json['success'] );
        $this->assertSame( 400, $json['status'] );
    }

    public function test_submit_rejects_missing_post_id(): void {
        $_POST = [
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    // ── handle_submit: device_id validation ──────────────────────────────

    public function test_submit_rejects_missing_device_id(): void {
        $this->registerPost( 5 );
        $_POST = [ 'post_id' => '5', 'response' => 'yes' ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    public function test_submit_rejects_non_uuid_device_id(): void {
        $this->registerPost( 5 );
        $_POST = [
            'post_id'   => '5',
            'device_id' => 'totally-not-a-uuid',
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    public function test_submit_rejects_sql_injection_in_device_id(): void {
        $this->registerPost( 5 );
        $_POST = [
            'post_id'   => '5',
            'device_id' => "'; DROP TABLE wp_simplersvp; --",
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    // ── handle_submit: response validation ───────────────────────────────

    public function test_submit_rejects_invalid_response_value(): void {
        $this->registerPost( 5 );
        $_POST = [
            'post_id'   => '5',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'definitely',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
        $this->assertSame( 400, $this->lastJson()['status'] );
    }

    public function test_submit_rejects_empty_response(): void {
        $this->registerPost( 5 );
        $_POST = [
            'post_id'   => '5',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => '',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    // ── handle_submit: post existence check ──────────────────────────────

    public function test_submit_rejects_nonexistent_post(): void {
        // Post NOT registered in $GLOBALS['_srsvp_posts'].
        $_POST = [
            'post_id'   => '999',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
        $this->assertSame( 404, $this->lastJson()['status'] );
    }

    public function test_submit_rejects_draft_post(): void {
        $this->registerPost( 7, 'draft' );
        $_POST = [
            'post_id'   => '7',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();
        $this->assertFalse( $this->lastJson()['success'] );
        $this->assertSame( 404, $this->lastJson()['status'] );
    }

    // ── handle_submit: rate limiting ─────────────────────────────────────

    public function test_rate_limit_constant_is_sensible(): void {
        $this->assertGreaterThan( 0, SimpleRSVP_Ajax::RATE_LIMIT );
        $this->assertLessThanOrEqual( 100, SimpleRSVP_Ajax::RATE_LIMIT );
    }

    public function test_submit_blocks_after_rate_limit_exceeded(): void {
        $this->registerPost( 5 );
        $device = '550e8400-e29b-41d4-a716-446655440000';
        $rateKey = 'srsvp_rate_' . md5( $device );

        // Simulate the transient already being at the limit.
        $GLOBALS['_srsvp_transients'][ $rateKey ] = SimpleRSVP_Ajax::RATE_LIMIT;

        $_POST = [
            'post_id'   => '5',
            'device_id' => $device,
            'response'  => 'yes',
        ];
        SimpleRSVP_Ajax::handle_submit();

        $json = $this->lastJson();
        $this->assertFalse( $json['success'] );
        $this->assertSame( 429, $json['status'] );
    }

    public function test_submit_succeeds_below_rate_limit(): void {
        $this->registerPost( 5 );

        // Wire up $wpdb to return "no existing row" then accept insert.
        global $wpdb;
        $wpdb->__get_var_queue    = [ null ];  // no existing id
        $wpdb->__get_results_queue = [ [] ];    // empty counts

        $_POST = [
            'post_id'   => '5',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'response'  => 'yes',
            'name'      => 'Alice',
        ];
        SimpleRSVP_Ajax::handle_submit();

        $json = $this->lastJson();
        $this->assertTrue( $json['success'] );
        $this->assertArrayHasKey( 'counts', $json['data'] );
    }

    // ── handle_get_counts: validation ────────────────────────────────────

    public function test_get_counts_rejects_zero_post_id(): void {
        $_GET = [ 'post_id' => '0' ];
        SimpleRSVP_Ajax::handle_get_counts();
        $this->assertFalse( $this->lastJson()['success'] );
    }

    public function test_get_counts_ignores_invalid_device_id(): void {
        global $wpdb;
        $wpdb->__get_results_queue = [ [] ];
        $wpdb->__get_var_queue     = [];

        $_GET = [
            'post_id'   => '5',
            'device_id' => 'bad-device-id',
        ];
        SimpleRSVP_Ajax::handle_get_counts();

        $json = $this->lastJson();
        // Should still succeed — invalid device_id is just ignored.
        $this->assertTrue( $json['success'] );
        $this->assertSame( '', $json['data']['response'] );
    }

    public function test_get_counts_returns_counts_and_empty_response_when_no_device(): void {
        global $wpdb;
        $wpdb->__get_results_queue = [
            [ [ 'response' => 'yes', 'count' => '3' ], [ 'response' => 'no', 'count' => '1' ] ],
        ];

        $_GET = [ 'post_id' => '5' ];
        SimpleRSVP_Ajax::handle_get_counts();

        $json = $this->lastJson();
        $this->assertTrue( $json['success'] );
        $this->assertSame( 3, $json['data']['counts']['yes'] );
        $this->assertSame( 1, $json['data']['counts']['no'] );
        $this->assertSame( 0, $json['data']['counts']['maybe'] );
        $this->assertSame( '', $json['data']['response'] );
    }
}
