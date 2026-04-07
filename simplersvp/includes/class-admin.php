<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleRSVP_Admin {

	public static function register_menu() {
		add_menu_page(
			__( 'SimpleRSVP', 'simplersvp' ),
			__( 'SimpleRSVP', 'simplersvp' ),
			'manage_options',
			'simplersvp',
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			30
		);
	}

	public static function register_post_handlers() {
		add_action( 'admin_post_simplersvp_reset',           array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_post_simplersvp_delete_response', array( __CLASS__, 'handle_delete_response' ) );
	}

	// -------------------------------------------------------------------------
	// Reset all responses for an event
	// -------------------------------------------------------------------------

	public static function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simplersvp' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid event.', 'simplersvp' ) );
		}

		check_admin_referer( 'simplersvp_reset_' . $post_id, 'simplersvp_reset_nonce' );

		SimpleRSVP_Database::delete_for_post( $post_id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'simplersvp', 'post_id' => $post_id, 'srsvp_reset' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Delete a single response
	// -------------------------------------------------------------------------

	/**
	 * Handle the per-row "Delete" form submission.
	 */
	public static function handle_delete_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simplersvp' ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$post_id  = isset( $_POST['post_id'] )  ? absint( $_POST['post_id'] )  : 0;

		if ( ! $entry_id || ! $post_id ) {
			wp_die( esc_html__( 'Invalid request.', 'simplersvp' ) );
		}

		check_admin_referer( 'simplersvp_delete_response_' . $entry_id, 'simplersvp_delete_nonce' );

		SimpleRSVP_Database::delete_by_id( $entry_id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'simplersvp', 'post_id' => $post_id, 'srsvp_deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Page routing
	// -------------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'simplersvp' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( $post_id ) {
			self::render_detail( $post_id );
		} else {
			self::render_list();
		}
	}

	// -------------------------------------------------------------------------
	// Event list
	// -------------------------------------------------------------------------

	private static function render_list() {
		$posts = SimpleRSVP_Database::get_posts_with_rsvps();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SimpleRSVP — Events', 'simplersvp' ); ?></h1>

			<?php if ( empty( $posts ) ) : ?>
				<p><?php esc_html_e( 'No RSVPs recorded yet.', 'simplersvp' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Event / Post', 'simplersvp' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Total RSVPs', 'simplersvp' ); ?></th>
							<th style="width:180px;"><?php esc_html_e( 'Actions', 'simplersvp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $posts as $row ) :
							$pid        = (int) $row['post_id'];
							$title      = get_the_title( $pid );
							$title      = $title ?: sprintf( __( 'Post #%d', 'simplersvp' ), $pid );
							$detail_url = admin_url( 'admin.php?page=simplersvp&post_id=' . $pid );
						?>
							<tr>
								<td>
									<strong><?php echo esc_html( $title ); ?></strong>
									<div class="row-actions">
										<span>
											<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"
											   target="_blank"><?php esc_html_e( 'View post', 'simplersvp' ); ?></a>
										</span>
									</div>
								</td>
								<td><?php echo esc_html( $row['total'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( $detail_url ); ?>">
										<?php esc_html_e( 'View Details', 'simplersvp' ); ?>
									</a>
									&nbsp;|&nbsp;
									<?php self::reset_form( $pid, /* compact */ true ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Per-event detail
	// -------------------------------------------------------------------------

	private static function render_detail( $post_id ) {
		$title   = get_the_title( $post_id );
		$title   = $title ?: sprintf( __( 'Post #%d', 'simplersvp' ), $post_id );
		$counts  = SimpleRSVP_Database::get_counts( $post_id );
		$entries = SimpleRSVP_Database::get_all_for_post( $post_id );
		$total   = array_sum( $counts );

		$back_url = admin_url( 'admin.php?page=simplersvp' );

		$response_labels = array(
			'yes'   => __( 'Yes', 'simplersvp' ),
			'no'    => __( 'No', 'simplersvp' ),
			'maybe' => __( 'Maybe', 'simplersvp' ),
		);
		$response_colors = array(
			'yes'   => '#2e7d32',
			'no'    => '#c62828',
			'maybe' => '#e65100',
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?> — <?php esc_html_e( 'RSVPs', 'simplersvp' ); ?></h1>

			<?php if ( isset( $_GET['srsvp_reset'] ) && $_GET['srsvp_reset'] === '1' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'RSVP counters have been reset to zero.', 'simplersvp' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['srsvp_deleted'] ) && $_GET['srsvp_deleted'] === '1' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Response deleted.', 'simplersvp' ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to all events', 'simplersvp' ); ?></a>
			</p>

			<h2><?php esc_html_e( 'Summary', 'simplersvp' ); ?></h2>
			<table class="wp-list-table widefat fixed" style="max-width:400px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Response', 'simplersvp' ); ?></th>
						<th><?php esc_html_e( 'Count', 'simplersvp' ); ?></th>
						<th><?php esc_html_e( 'Share', 'simplersvp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $counts as $key => $count ) :
						$pct   = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
						$color = $response_colors[ $key ] ?? '#666';
						$label = $response_labels[ $key ] ?? ucfirst( $key );
					?>
					<tr>
						<th scope="row" style="color:<?php echo esc_attr( $color ); ?>;">
							<?php echo esc_html( $label ); ?>
						</th>
						<td><strong><?php echo esc_html( $count ); ?></strong></td>
						<td>
							<div style="background:#eee;border-radius:4px;height:12px;width:100%;">
								<div style="background:<?php echo esc_attr( $color ); ?>;width:<?php echo esc_attr( $pct ); ?>%;height:12px;border-radius:4px;"></div>
							</div>
							<small><?php echo esc_html( $pct ); ?>%</small>
						</td>
					</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total', 'simplersvp' ); ?></th>
						<td><strong><?php echo esc_html( $total ); ?></strong></td>
						<td></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1em;">
				<?php self::reset_form( $post_id, /* compact */ false ); ?>
			</p>

			<h2 style="margin-top:2em;"><?php esc_html_e( 'Individual Responses', 'simplersvp' ); ?></h2>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'No responses yet.', 'simplersvp' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'simplersvp' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Response', 'simplersvp' ); ?></th>
							<th style="width:200px;"><?php esc_html_e( 'Last Updated', 'simplersvp' ); ?></th>
							<th style="width:80px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) :
							$key   = $entry['response'];
							$color = $response_colors[ $key ] ?? '#666';
							$label = $response_labels[ $key ] ?? ucfirst( $key );
						?>
							<tr>
								<td><?php echo esc_html( $entry['name'] ?: __( '(anonymous)', 'simplersvp' ) ); ?></td>
								<td style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;">
									<?php echo esc_html( $label ); ?>
								</td>
								<td><?php echo esc_html( $entry['updated_at'] ); ?></td>
								<td><?php self::delete_response_form( (int) $entry['id'], $post_id ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Shared form helpers
	// -------------------------------------------------------------------------

	/**
	 * Render the "Reset Counters" form.
	 *
	 * @param int  $post_id
	 * @param bool $compact  true = link style (list view), false = button (detail view).
	 */
	public static function reset_form( $post_id, $compact = false ) {
		$confirm_msg = esc_js(
			__( 'Reset all RSVPs for this event? This cannot be undone.', 'simplersvp' )
		);
		?>
		<form method="post"
		      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		      style="display:inline;"
		      onsubmit="return confirm('<?php echo $confirm_msg; ?>')">
			<?php wp_nonce_field( 'simplersvp_reset_' . $post_id, 'simplersvp_reset_nonce' ); ?>
			<input type="hidden" name="action"  value="simplersvp_reset">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php if ( $compact ) : ?>
				<button type="submit" class="button-link" style="color:#b32d2e;">
					<?php esc_html_e( 'Reset', 'simplersvp' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" class="button button-secondary" style="color:#b32d2e;border-color:#b32d2e;">
					<?php esc_html_e( 'Reset Counters', 'simplersvp' ); ?>
				</button>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Render the per-row "Delete" form for a single response.
	 *
	 * @param int $entry_id  Primary key of the row to delete.
	 * @param int $post_id   Parent post (for redirect target).
	 */
	public static function delete_response_form( $entry_id, $post_id ) {
		$confirm_msg = esc_js( __( 'Delete this response? This cannot be undone.', 'simplersvp' ) );
		?>
		<form method="post"
		      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		      style="display:inline;"
		      onsubmit="return confirm('<?php echo $confirm_msg; ?>')">
			<?php wp_nonce_field( 'simplersvp_delete_response_' . $entry_id, 'simplersvp_delete_nonce' ); ?>
			<input type="hidden" name="action"   value="simplersvp_delete_response">
			<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry_id ); ?>">
			<input type="hidden" name="post_id"  value="<?php echo esc_attr( $post_id ); ?>">
			<button type="submit" class="button-link" style="color:#b32d2e;">
				<?php esc_html_e( 'Delete', 'simplersvp' ); ?>
			</button>
		</form>
		<?php
	}
}
