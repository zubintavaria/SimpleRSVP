<?php
/**
 * Tests for SimpleRSVP_Shortcode.
 *
 * We call SimpleRSVP_Shortcode::render() directly and inspect the HTML it
 * produces.  The wp-stubs provide the minimal WP environment needed.
 */

use PHPUnit\Framework\TestCase;

class ShortcodeTest extends TestCase {

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Render the widget with the given shortcode attributes and return HTML.
     *
     * @param  array<string,string> $atts
     * @return string
     */
    private function render( array $atts = [] ): string {
        return SimpleRSVP_Shortcode::render( $atts );
    }

    // ── Default attributes ────────────────────────────────────────────────

    public function test_default_question_text_appears_in_output(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'Will you attend?', $html );
    }

    public function test_default_yes_label_appears_in_output(): void {
        $html = $this->render();
        $this->assertStringContainsString( '>Yes<', $html );
    }

    public function test_default_no_label_appears_in_output(): void {
        $html = $this->render();
        $this->assertStringContainsString( '>No<', $html );
    }

    public function test_default_maybe_label_appears_in_output(): void {
        $html = $this->render();
        $this->assertStringContainsString( '>Maybe<', $html );
    }

    // ── Custom attributes ─────────────────────────────────────────────────

    public function test_custom_question_appears_in_output(): void {
        $html = $this->render( [ 'question' => 'Coming to the party?' ] );
        $this->assertStringContainsString( 'Coming to the party?', $html );
    }

    public function test_custom_yes_label_replaces_default(): void {
        $html = $this->render( [ 'yes' => 'Count me in' ] );
        $this->assertStringContainsString( 'Count me in', $html );
        $this->assertStringNotContainsString( '>Yes<', $html );
    }

    public function test_custom_no_label_replaces_default(): void {
        $html = $this->render( [ 'no' => 'Declining' ] );
        $this->assertStringContainsString( 'Declining', $html );
        $this->assertStringNotContainsString( '>No<', $html );
    }

    public function test_custom_maybe_label_replaces_default(): void {
        $html = $this->render( [ 'maybe' => 'Not sure yet' ] );
        $this->assertStringContainsString( 'Not sure yet', $html );
    }

    // ── show_maybe ────────────────────────────────────────────────────────

    public function test_show_maybe_true_renders_maybe_button(): void {
        $html = $this->render( [ 'show_maybe' => 'true' ] );
        $this->assertStringContainsString( 'simplersvp-btn-maybe', $html );
    }

    public function test_show_maybe_false_hides_maybe_button(): void {
        $html = $this->render( [ 'show_maybe' => 'false' ] );
        $this->assertStringNotContainsString( 'simplersvp-btn-maybe', $html );
    }

    public function test_show_maybe_false_hides_maybe_count(): void {
        $html = $this->render( [ 'show_maybe' => 'false' ] );
        $this->assertStringNotContainsString( 'simplersvp-count-maybe', $html );
    }

    public function test_show_maybe_defaults_to_true_when_omitted(): void {
        $html = $this->render( [] );
        $this->assertStringContainsString( 'simplersvp-btn-maybe', $html );
    }

    // ── data-* attributes (consumed by JS) ───────────────────────────────

    public function test_widget_has_data_post_id_attribute(): void {
        $html = $this->render();
        $this->assertMatchesRegularExpression( '/data-post-id="\d+"/', $html );
    }

    public function test_widget_data_yes_reflects_custom_label(): void {
        $html = $this->render( [ 'yes' => 'Attending' ] );
        $this->assertStringContainsString( 'data-yes="Attending"', $html );
    }

    public function test_widget_data_no_reflects_custom_label(): void {
        $html = $this->render( [ 'no' => 'Skipping' ] );
        $this->assertStringContainsString( 'data-no="Skipping"', $html );
    }

    public function test_widget_data_maybe_reflects_custom_label(): void {
        $html = $this->render( [ 'maybe' => 'Perhaps' ] );
        $this->assertStringContainsString( 'data-maybe="Perhaps"', $html );
    }

    public function test_widget_data_show_maybe_is_false_when_disabled(): void {
        $html = $this->render( [ 'show_maybe' => 'false' ] );
        $this->assertStringContainsString( 'data-show-maybe="false"', $html );
    }

    public function test_widget_data_show_maybe_is_true_when_enabled(): void {
        $html = $this->render( [ 'show_maybe' => 'true' ] );
        $this->assertStringContainsString( 'data-show-maybe="true"', $html );
    }

    // ── HTML structure ────────────────────────────────────────────────────

    public function test_output_contains_widget_wrapper(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'simplersvp-widget', $html );
    }

    public function test_output_contains_card_element(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'simplersvp-card', $html );
    }

    public function test_output_contains_name_input(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'simplersvp-name-input', $html );
    }

    public function test_output_contains_submitted_div(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'simplersvp-submitted', $html );
    }

    public function test_output_contains_counts_section(): void {
        $html = $this->render();
        $this->assertStringContainsString( 'simplersvp-counts', $html );
    }

    public function test_output_contains_yes_and_no_count_items_always(): void {
        $html = $this->render( [ 'show_maybe' => 'false' ] );
        $this->assertStringContainsString( 'simplersvp-count-yes', $html );
        $this->assertStringContainsString( 'simplersvp-count-no',  $html );
    }

    // ── XSS: custom attribute values are escaped ──────────────────────────

    public function test_xss_in_question_is_escaped(): void {
        $html = $this->render( [ 'question' => '<script>alert(1)</script>' ] );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_xss_in_yes_label_is_escaped_in_data_attribute(): void {
        $html = $this->render( [ 'yes' => '" onmouseover="alert(1)' ] );
        // The double-quote must be HTML-entity encoded so it cannot break out of the attribute context.
        $this->assertStringContainsString( '&quot;', $html );
        // The raw unencoded double-quote must not appear directly after data-yes=.
        $this->assertDoesNotMatchRegularExpression( '/data-yes="[^"]*"[^"]*onmouseover/', $html );
    }
}
