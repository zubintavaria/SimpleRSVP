<?php
/**
 * Public respondents list template.
 *
 * Variables available from SimpleRSVP_Shortcode::render_list():
 *   $post_id        int     WordPress post ID
 *   $atts           array   Shortcode attributes
 *   $show_maybe     bool
 *   $show_anonymous bool
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="simplersvp-list-widget"
	 data-post-id="<?php echo esc_attr( $post_id ); ?>"
	 data-yes="<?php echo esc_attr( $atts['yes'] ); ?>"
	 data-no="<?php echo esc_attr( $atts['no'] ); ?>"
	 data-maybe="<?php echo esc_attr( $atts['maybe'] ); ?>"
	 data-show-maybe="<?php echo $show_maybe ? 'true' : 'false'; ?>"
	 data-show-anonymous="<?php echo $show_anonymous ? 'true' : 'false'; ?>">

	<div class="simplersvp-list-card">

		<?php if ( $atts['title'] ) : ?>
			<p class="simplersvp-list-title"><?php echo esc_html( $atts['title'] ); ?></p>
		<?php endif; ?>

		<p class="simplersvp-list-loading" aria-live="polite">
			<?php esc_html_e( 'Loading responses&hellip;', 'simplersvp' ); ?>
		</p>

		<p class="simplersvp-list-empty" hidden>
			<?php esc_html_e( 'No responses yet.', 'simplersvp' ); ?>
		</p>

		<table class="simplersvp-list-table" hidden aria-live="polite" aria-atomic="false">
			<thead>
				<tr>
					<th class="simplersvp-list-th-name"><?php esc_html_e( 'Name', 'simplersvp' ); ?></th>
					<th class="simplersvp-list-th-response"><?php esc_html_e( 'Response', 'simplersvp' ); ?></th>
				</tr>
			</thead>
			<tbody class="simplersvp-list-body"></tbody>
		</table>

	</div><!-- .simplersvp-list-card -->
</div><!-- .simplersvp-list-widget -->
