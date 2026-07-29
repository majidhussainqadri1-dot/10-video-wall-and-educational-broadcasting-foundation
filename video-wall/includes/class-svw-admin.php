<?php
/**
 * Moderation and report administration.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_svw_review', array( $this, 'review' ) );
		add_action( 'admin_post_svw_report', array( $this, 'report' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page( 'Video Wall Management', 'Video Wall Management', 'manage_video_wall', 'video-wall-management', array( $this, 'page' ), 'dashicons-video-alt3', 30 );
		add_submenu_page( 'video-wall-management', 'Reports', 'Reports', 'manage_video_wall', 'video-wall-reports', array( $this, 'reports' ) );
	}

	/**
	 * Render the paginated moderation queue.
	 *
	 * @return void
	 */
	public function page() {
		$this->guard();
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'publish', 'draft', 'private' ), true ) ) {
			$status = 'pending';
		}
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$query = new WP_Query(
			array(
				'post_type'      => SVW_Helpers::TYPE,
				'post_status'    => $status,
				'posts_per_page' => 25,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap svw-admin">
			<h1>Video Wall Management</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( array( 'pending' => 'Pending', 'publish' => 'Published', 'draft' => 'Rejected', 'private' => 'Hidden' ) as $key => $label ) : ?>
					<a class="nav-tab <?php echo $status === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'video-wall-management', 'status' => $key, 'paged' => false ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<table class="widefat striped">
				<thead><tr><th>Video</th><th>Author</th><th>Safety</th><th>Recent Audit</th><th>Review</th></tr></thead>
				<tbody>
				<?php if ( $query->have_posts() ) : ?>
					<?php foreach ( $query->posts as $video ) : ?>
						<tr>
							<td><strong><a href="<?php echo esc_url( get_permalink( $video ) ); ?>"><?php echo esc_html( $video->post_title ); ?></a></strong><br><?php echo esc_html( SVW_Helpers::category( $video->ID ) ); ?></td>
							<td><?php echo esc_html( get_the_author_meta( 'display_name', $video->post_author ) ); ?><br><small><?php echo esc_html( SVW_Helpers::author_label( $video->post_author ) ); ?></small></td>
							<td><?php echo esc_html( wp_trim_words( SVW_Helpers::meta( $video->ID, 'safety' ), 20 ) ); ?></td>
							<td><?php echo wp_kses_post( $this->recent_audit( 'video', $video->ID ) ); ?></td>
							<td>
								<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
									<input type="hidden" name="action" value="svw_review">
									<input type="hidden" name="video_id" value="<?php echo absint( $video->ID ); ?>">
									<?php wp_nonce_field( 'svw_review_' . $video->ID ); ?>
									<label>Decision
										<select name="decision">
											<option value="approve">Approve</option>
											<option value="reject">Reject</option>
											<option value="feature">Toggle Featured</option>
											<option value="hide">Hide</option>
										</select>
									</label>
									<label>Internal review note<textarea name="note" maxlength="2000" placeholder="Required for rejection or hiding"></textarea></label>
									<button class="button button-primary">Apply</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5">No videos found in this state.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<?php $this->pagination( $paged, (int) $query->max_num_pages, array( 'page' => 'video-wall-management', 'status' => $status ) ); ?>
		</div>
		<?php
	}

	/**
	 * Apply a moderation decision and write an audit trail.
	 *
	 * @return void
	 */
	public function review() {
		$this->guard();
		$video_id = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		check_admin_referer( 'svw_review_' . $video_id );
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( SVW_Helpers::TYPE !== get_post_type( $video_id ) || ! in_array( $decision, array( 'approve', 'reject', 'feature', 'hide' ), true ) ) {
			wp_die( esc_html__( 'Invalid review.', 'video-wall' ), '', array( 'response' => 400 ) );
		}
		if ( in_array( $decision, array( 'reject', 'hide' ), true ) && ! trim( $note ) ) {
			wp_die( esc_html__( 'An internal review note is required for rejection or hiding.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		$previous = get_post_status( $video_id );
		$new       = $previous;
		$metadata  = array();
		if ( 'approve' === $decision ) {
			$new = 'publish';
		} elseif ( 'reject' === $decision ) {
			$new = 'draft';
		} elseif ( 'hide' === $decision ) {
			$new = 'private';
		} else {
			$old_featured = '1' === SVW_Helpers::meta( $video_id, 'featured', '0' );
			$new_featured = $old_featured ? '0' : '1';
			update_post_meta( $video_id, '_svw_featured', $new_featured );
			$metadata = array( 'featured' => $new_featured );
		}

		if ( $new !== $previous ) {
			$result = wp_update_post( array( 'ID' => $video_id, 'post_status' => $new ), true );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
			}
		}

		SVW_Helpers::audit( 'video', $video_id, $decision, $previous, $new, $note, $metadata );
		wp_safe_redirect( add_query_arg( array( 'page' => 'video-wall-management', 'status' => $new ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render reports with pagination and selected status.
	 *
	 * @return void
	 */
	public function reports() {
		$this->guard();
		global $wpdb;
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$limit  = 25;
		$offset = ( $paged - 1 ) * $limit;
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}svw_reports" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}svw_reports ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		?>
		<div class="wrap svw-admin">
			<h1>Reported Videos</h1>
			<table class="widefat striped">
				<thead><tr><th>Video</th><th>Report</th><th>Reporter</th><th>Recent Audit</th><th>Status</th></tr></thead>
				<tbody>
				<?php if ( $rows ) : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( get_the_title( $row->video_id ) ); ?></td>
							<td><strong><?php echo esc_html( $row->reason ); ?></strong><br><?php echo nl2br( esc_html( $row->details ) ); ?></td>
							<td><?php echo $row->user_id ? esc_html( get_the_author_meta( 'display_name', $row->user_id ) ) : 'Anonymized'; ?></td>
							<td><?php echo wp_kses_post( $this->recent_audit( 'report', $row->id ) ); ?></td>
							<td>
								<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
									<input type="hidden" name="action" value="svw_report">
									<input type="hidden" name="report_id" value="<?php echo absint( $row->id ); ?>">
									<?php wp_nonce_field( 'svw_report_' . $row->id ); ?>
									<label>Report status
										<select name="status">
											<?php foreach ( array( 'open', 'reviewing', 'resolved', 'dismissed' ) as $status ) : ?>
												<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row->status, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
									<label>Internal note<textarea name="note" maxlength="2000"></textarea></label>
									<button class="button">Save</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5">No reports found.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<?php $this->pagination( $paged, (int) ceil( $total / $limit ), array( 'page' => 'video-wall-reports' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Update a report status and audit it.
	 *
	 * @return void
	 */
	public function report() {
		$this->guard();
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		check_admin_referer( 'svw_report_' . $report_id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( ! in_array( $status, array( 'open', 'reviewing', 'resolved', 'dismissed' ), true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'video-wall' ), '', array( 'response' => 400 ) );
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}svw_reports WHERE id = %d", $report_id ) );
		if ( ! $row ) {
			wp_die( esc_html__( 'Report not found.', 'video-wall' ), '', array( 'response' => 404 ) );
		}
		$result = $wpdb->update(
			$wpdb->prefix . 'svw_reports',
			array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $report_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $result ) {
			wp_die( esc_html__( 'The report status could not be saved.', 'video-wall' ), '', array( 'response' => 500 ) );
		}
		SVW_Helpers::audit( 'report', $report_id, 'status_changed', $row->status, $status, $note, array( 'video_id' => absint( $row->video_id ) ) );
		wp_safe_redirect( add_query_arg( 'page', 'video-wall-reports', admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Enforce the admin capability.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( 'manage_video_wall' ) ) {
			wp_die( esc_html__( 'Access denied.', 'video-wall' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Activation notice.
	 *
	 * @return void
	 */
	public function notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'svw_notice' ) ) {
			delete_transient( 'svw_notice' );
			echo '<div class="notice notice-success"><p>Video Wall is active. Existing unrelated page content was not overwritten; review the configured pages before public launch.</p></div>';
		}
	}

	/**
	 * Render the most recent audit entry.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @return string
	 */
	private function recent_audit( $object_type, $object_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action, previous_state, new_state, actor_id, note, created_at FROM {$wpdb->prefix}svw_audit WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT 1",
				sanitize_key( $object_type ),
				absint( $object_id )
			)
		);
		if ( ! $row ) {
			return '<em>No audit entry yet.</em>';
		}
		$actor = $row->actor_id ? get_the_author_meta( 'display_name', $row->actor_id ) : 'System/Anonymized';
		$output = '<strong>' . esc_html( $row->action ) . '</strong><br>' . esc_html( $row->previous_state . ' → ' . $row->new_state ) . '<br><small>' . esc_html( $actor . ' · ' . $row->created_at ) . '</small>';
		if ( $row->note ) {
			$output .= '<br>' . esc_html( wp_trim_words( $row->note, 18 ) );
		}
		return $output;
	}

	/**
	 * Render admin pagination.
	 *
	 * @param int   $current Current page.
	 * @param int   $total   Total pages.
	 * @param array $args    Persistent URL args.
	 * @return void
	 */
	private function pagination( $current, $total, $args ) {
		if ( $total <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => esc_url_raw( add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ) ),
					'format'  => '',
					'current' => max( 1, absint( $current ) ),
					'total'   => max( 1, absint( $total ) ),
				)
			)
		);
		echo '</div></div>';
	}
}
