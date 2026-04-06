<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleRSVP_Shortcode {

	public static function register() {
		add_shortcode( 'simplersvp', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the RSVP widget.
	 *
	 * Supported attributes:
	 *   question   — prompt text          (default: "Will you attend?")
	 *   yes        — yes button label     (default: "Yes")
	 *   no         — no button label      (default: "No")
	 *   maybe      — maybe button label   (default: "Maybe")
	 *   show_maybe — show the maybe btn   (default: "true")
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string      HTML output.
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'question'   => 'Will you attend?',
				'yes'        => 'Yes',
				'no'         => 'No',
				'maybe'      => 'Maybe',
				'show_maybe' => 'true',
			),
			$atts,
			'simplersvp'
		);

		$post_id    = get_the_ID();
		$show_maybe = filter_var( $atts['show_maybe'], FILTER_VALIDATE_BOOLEAN );

		wp_enqueue_style( 'simplersvp' );
		wp_enqueue_script( 'simplersvp' );

		ob_start();
		include SIMPLERSVP_DIR . 'templates/rsvp-widget.php';
		return ob_get_clean();
	}
}
