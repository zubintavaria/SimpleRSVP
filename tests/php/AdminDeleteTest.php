<?php
/**
 * Tests for the per-row "Delete Response" feature in SimpleRSVP_Admin.
 */

use PHPUnit\Framework\TestCase;

class AdminDeleteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb']                = new WpdbStub();
		$GLOBALS['_srsvp_last_redirect'] = null;
		$_POST = [];
		$_GET  = [];
	}

	// ── handle_delete_response: validation ───────────────────────────────────

	public function test_delete_dies_when_entry_id_is_zero(): void {
		$_POST = [ 'entry_id' => '0', 'post_id' => '5' ];
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/wp_die/' );
		SimpleRSVP_Admin::handle_delete_response();
	}

	public function test_delete_dies_when_post_id_is_zero(): void {
		$_POST = [ 'entry_id' => '10', 'post_id' => '0' ];
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/wp_die/' );
		SimpleRSVP_Admin::handle_delete_response();
	}

	public function test_delete_dies_when_both_ids_missing(): void {
		$_POST = [];
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/wp_die/' );
		SimpleRSVP_Admin::handle_delete_response();
	}

	// ── handle_delete_response: database call ────────────────────────────────

	public function test_delete_calls_delete_by_id_with_correct_entry_id(): void {
		$_POST = [
			'entry_id'              => '17',
			'post_id'               => '5',
			'simplersvp_delete_nonce' => 'test-nonce',
		];

		try {
			SimpleRSVP_Admin::handle_delete_response();
		} catch ( SimpleRSVP_RedirectException $e ) { /* expected */ }

		$deleteCall = $this->findCall( 'delete' );
		$this->assertNotNull( $deleteCall, 'Expected a DELETE call on wpdb.' );
		$this->assertSame( 17, $deleteCall['where']['id'] );
	}

	public function test_delete_does_not_call_delete_when_entry_id_is_zero(): void {
		$_POST = [ 'entry_id' => '0', 'post_id' => '5' ];

		try {
			SimpleRSVP_Admin::handle_delete_response();
		} catch ( \RuntimeException $e ) { /* wp_die thrown — good */ }

		$this->assertNull( $this->findCall( 'delete' ), 'DELETE must not fire for entry_id=0.' );
	}

	// ── handle_delete_response: redirect ─────────────────────────────────────

	public function test_delete_redirects_to_detail_page_with_deleted_flag(): void {
		$_POST = [
			'entry_id'              => '7',
			'post_id'               => '3',
			'simplersvp_delete_nonce' => 'test-nonce',
		];

		try {
			SimpleRSVP_Admin::handle_delete_response();
			$this->fail( 'Expected SimpleRSVP_RedirectException.' );
		} catch ( SimpleRSVP_RedirectException $e ) {
			$url = $e->getMessage();
			$this->assertStringContainsString( 'page=simplersvp',  $url );
			$this->assertStringContainsString( 'post_id=3',         $url );
			$this->assertStringContainsString( 'srsvp_deleted=1',   $url );
		}
	}

	public function test_delete_redirect_contains_correct_post_id(): void {
		$_POST = [
			'entry_id'              => '99',
			'post_id'               => '42',
			'simplersvp_delete_nonce' => 'test-nonce',
		];

		try {
			SimpleRSVP_Admin::handle_delete_response();
		} catch ( SimpleRSVP_RedirectException $e ) {
			$this->assertStringContainsString( 'post_id=42', $e->getMessage() );
		}
	}

	// ── delete_response_form: HTML output ────────────────────────────────────

	public function test_delete_form_contains_entry_id_hidden_input(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 88, 5 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="entry_id"', $html );
		$this->assertStringContainsString( 'value="88"',       $html );
	}

	public function test_delete_form_contains_post_id_hidden_input(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 1, 55 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="post_id"', $html );
		$this->assertStringContainsString( 'value="55"',      $html );
	}

	public function test_delete_form_contains_action_simplersvp_delete_response(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 1, 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="simplersvp_delete_response"', $html );
	}

	public function test_delete_form_contains_nonce_field(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 1, 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'simplersvp_delete_nonce', $html );
	}

	public function test_delete_form_has_confirm_dialog(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 1, 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'onsubmit', $html );
		$this->assertStringContainsString( 'confirm(',  $html );
	}

	public function test_delete_form_posts_to_admin_post_php(): void {
		ob_start();
		SimpleRSVP_Admin::delete_response_form( 1, 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'admin-post.php', $html );
		$this->assertStringContainsString( 'method="post"',   $html );
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	private function findCall( string $method ): ?array {
		foreach ( $GLOBALS['wpdb']->__calls as $call ) {
			if ( $call['method'] === $method ) { return $call; }
		}
		return null;
	}
}
