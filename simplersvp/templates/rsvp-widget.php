<?php
/**
 * RSVP widget template.
 *
 * Variables available from SimpleRSVP_Shortcode::render():
 *   $post_id    int     WordPress post ID
 *   $atts       array   Shortcode attributes (question, yes, no, maybe, show_maybe)
 *   $show_maybe bool    Whether to render the maybe button/count
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="simplersvp-widget"
	 data-post-id="<?php echo esc_attr( $post_id ); ?>"
	 data-yes="<?php echo esc_attr( $atts['yes'] ); ?>"
	 data-no="<?php echo esc_attr( $atts['no'] ); ?>"
	 data-maybe="<?php echo esc_attr( $atts['maybe'] ); ?>"
	 data-show-maybe="<?php echo $show_maybe ? 'true' : 'false'; ?>">

	<div class="simplersvp-card">

		<p class="simplersvp-question"><?php echo esc_html( $atts['question'] ); ?></p>

		<!-- Optional name input -->
		<div class="simplersvp-name-row">
			<input type="text"
				   class="simplersvp-name-input"
				   placeholder="<?php esc_attr_e( 'Your name (optional)', 'simplersvp' ); ?>"
				   maxlength="100"
				   autocomplete="name" />
		</div>

		<!-- Response buttons (visible before submission) -->
		<div class="simplersvp-buttons" role="group" aria-label="<?php esc_attr_e( 'RSVP options', 'simplersvp' ); ?>">
			<button class="simplersvp-btn simplersvp-btn-yes" data-value="yes" type="button">
				<?php echo esc_html( $atts['yes'] ); ?>
			</button>
			<button class="simplersvp-btn simplersvp-btn-no" data-value="no" type="button">
				<?php echo esc_html( $atts['no'] ); ?>
			</button>
			<?php if ( $show_maybe ) : ?>
			<button class="simplersvp-btn simplersvp-btn-maybe" data-value="maybe" type="button">
				<?php echo esc_html( $atts['maybe'] ); ?>
			</button>
			<?php endif; ?>
		</div>

		<!-- Submitted state (hidden until a response is recorded) -->
		<div class="simplersvp-submitted" hidden>
			<p class="simplersvp-current-response">
				<?php esc_html_e( 'Your response:', 'simplersvp' ); ?>
				<strong class="simplersvp-response-label"></strong>
			</p>
			<button class="simplersvp-change-btn" type="button">
				<?php esc_html_e( 'Change my response', 'simplersvp' ); ?>
			</button>
		</div>

		<!-- Live count display -->
		<div class="simplersvp-counts" aria-live="polite" aria-atomic="false">
			<div class="simplersvp-count-item simplersvp-count-yes">
				<span class="simplersvp-count-num" data-key="yes">0</span>
				<span class="simplersvp-count-label"><?php echo esc_html( $atts['yes'] ); ?></span>
			</div>
			<div class="simplersvp-count-item simplersvp-count-no">
				<span class="simplersvp-count-num" data-key="no">0</span>
				<span class="simplersvp-count-label"><?php echo esc_html( $atts['no'] ); ?></span>
			</div>
			<?php if ( $show_maybe ) : ?>
			<div class="simplersvp-count-item simplersvp-count-maybe">
				<span class="simplersvp-count-num" data-key="maybe">0</span>
				<span class="simplersvp-count-label"><?php echo esc_html( $atts['maybe'] ); ?></span>
			</div>
			<?php endif; ?>
		</div>

	</div><!-- .simplersvp-card -->
</div><!-- .simplersvp-widget -->
