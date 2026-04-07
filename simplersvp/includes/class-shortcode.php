<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleRSVP_Shortcode {

	public static function register() {
		add_shortcode( 'simplersvp',      array( __CLASS__, 'render' ) );
		add_shortcode( 'simplersvp_list', array( __CLASS__, 'render_list' ) );
	}

	// -------------------------------------------------------------------------
	// [simplersvp] — RSVP buttons + live counts
	// -------------------------------------------------------------------------

	/**
	 * Supported attributes:
	 *   question   — prompt text          (default: "Will you attend?")
	 *   yes        — yes button label     (default: "Yes")
	 *   no         — no button label      (default: "No")
	 *   maybe      — maybe button label   (default: "Maybe")
	 *   show_maybe — show the maybe btn   (default: "true")
	 *
	 * @param  array $atts
	 * @return string HTML
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

	// -------------------------------------------------------------------------
	// [simplersvp_list] — public respondents table
	// -------------------------------------------------------------------------

	/**
	 * Supported attributes:
	 *   post_id          — which post to display (default: current post)
	 *   title            — heading above the table (default: "Responses")
	 *   yes              — label for Yes responses  (default: "Yes")
	 *   no               — label for No responses   (default: "No")
	 *   maybe            — label for Maybe          (default: "Maybe")
	 *   show_maybe       — include Maybe rows        (default: "true")
	 *   show_anonymous   — include rows with no name (default: "true")
	 *
	 * @param  array $atts
	 * @return string HTML
	 */
	public static function render_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'        => '',
				'title'          => 'Responses',
				'yes'            => 'Yes',
				'no'             => 'No',
				'maybe'          => 'Maybe',
				'show_maybe'     => 'true',
				'show_anonymous' => 'true',
			),
			$atts,
			'simplersvp_list'
		);

		$post_id        = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
		$show_maybe     = filter_var( $atts['show_maybe'],     FILTER_VALIDATE_BOOLEAN );
		$show_anonymous = filter_var( $atts['show_anonymous'], FILTER_VALIDATE_BOOLEAN );

		wp_enqueue_style( 'simplersvp' );
		wp_enqueue_script( 'simplersvp' );

		ob_start();
		include SIMPLERSVP_DIR . 'templates/rsvp-list.php';
		return ob_get_clean();
	}
}
