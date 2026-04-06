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
							<th style="width:100px;"><?php esc_html_e( 'Actions', 'simplersvp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $posts as $row ) :
							$title      = get_the_title( (int) $row['post_id'] );
							$title      = $title ?: sprintf( __( 'Post #%d', 'simplersvp' ), $row['post_id'] );
							$detail_url = admin_url( 'admin.php?page=simplersvp&post_id=' . absint( $row['post_id'] ) );
						?>
							<tr>
								<td>
									<strong><?php echo esc_html( $title ); ?></strong>
									<div class="row-actions">
										<span>
											<a href="<?php echo esc_url( get_permalink( (int) $row['post_id'] ) ); ?>"
											   target="_blank"><?php esc_html_e( 'View post', 'simplersvp' ); ?></a>
										</span>
									</div>
								</td>
								<td><?php echo esc_html( $row['total'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( $detail_url ); ?>">
										<?php esc_html_e( 'View Details', 'simplersvp' ); ?>
									</a>
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
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
