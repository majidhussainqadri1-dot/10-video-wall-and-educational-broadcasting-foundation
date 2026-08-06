<?php
/**
 * Reactions, saves, reports, history, view counts, and cleanup.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Interactions {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_ajax_svw_action', array( $this, 'rate_limit' ), 1 );
		add_action( 'wp_ajax_svw_action', array( $this, 'ajax' ) );
		add_action( 'template_redirect', array( $this, 'record_view' ) );
		add_action( 'before_delete_post', array( $this, 'delete_video_data' ) );
		add_action( 'delete_user', array( $this, 'delete_user_data' ) );
	}

	/**
	 * Load per-user interaction state in one query per table.
	 *
	 * @param int[] $video_ids Video IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public static function states_for_videos( $video_ids ) {
		$video_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $video_ids ) ) ) );
		$states    = array();
		foreach ( $video_ids as $video_id ) {
			$states[ $video_id ] = array(
				'reaction' => '',
				'saved'    => false,
			);
		}

		if ( ! is_user_logged_in() || empty( $video_ids ) ) {
			return $states;
		}

		global $wpdb;
		$user_id      = get_current_user_id();
		$placeholders = implode( ',', array_fill( 0, count( $video_ids ), '%d' ) );
		$params       = array_merge( array( $user_id ), $video_ids );

		$reaction_sql = $wpdb->prepare(
			"SELECT video_id, reaction FROM {$wpdb->prefix}svw_reactions WHERE user_id = %d AND video_id IN ({$placeholders})",
			$params
		);
		foreach ( (array) $wpdb->get_results( $reaction_sql ) as $row ) {
			$states[ absint( $row->video_id ) ]['reaction'] = (string) $row->reaction;
		}

		$save_sql = $wpdb->prepare(
			"SELECT video_id FROM {$wpdb->prefix}svw_saves WHERE user_id = %d AND video_id IN ({$placeholders})",
			$params
		);
		foreach ( (array) $wpdb->get_col( $save_sql ) as $video_id ) {
			$states[ absint( $video_id ) ]['saved'] = true;
		}

		return $states;
	}

	/**
	 * Render interaction controls.
	 *
	 * @param int        $video_id Video ID.
	 * @param array|null $state    Optional preloaded state.
	 * @return string
	 */
	public static function buttons( $video_id, $state = null ) {
		$video_id = absint( $video_id );
		$logged   = is_user_logged_in();
		if ( null === $state ) {
			$all   = self::states_for_videos( array( $video_id ) );
			$state = isset( $all[ $video_id ] ) ? $all[ $video_id ] : array( 'reaction' => '', 'saved' => false );
		}

		$reaction = isset( $state['reaction'] ) ? (string) $state['reaction'] : '';
		$saved    = ! empty( $state['saved'] );
		$status_id = 'svw-status-' . $video_id . '-' . wp_rand( 1000, 9999 );

		ob_start();
		?>
		<div class="svw-actions" data-video-id="<?php echo absint( $video_id ); ?>">
			<?php if ( $logged ) : ?>
				<button type="button" data-svw-action="like" class="<?php echo 'like' === $reaction ? 'is-active' : ''; ?>" aria-pressed="<?php echo 'like' === $reaction ? 'true' : 'false'; ?>">
					Like <span><?php echo absint( SVW_Helpers::meta( $video_id, 'likes', 0 ) ); ?></span>
				</button>
				<button type="button" data-svw-action="dislike" class="<?php echo 'dislike' === $reaction ? 'is-active' : ''; ?>" aria-pressed="<?php echo 'dislike' === $reaction ? 'true' : 'false'; ?>">
					Dislike <span><?php echo absint( SVW_Helpers::meta( $video_id, 'dislikes', 0 ) ); ?></span>
				</button>
				<button type="button" data-svw-action="save" class="<?php echo $saved ? 'is-active' : ''; ?>" aria-pressed="<?php echo $saved ? 'true' : 'false'; ?>">
					<?php echo $saved ? 'Saved' : 'Save'; ?>
				</button>
				<details>
					<summary>Report</summary>
					<form data-svw-report aria-describedby="<?php echo esc_attr( $status_id ); ?>">
						<label>
							Report reason
							<select name="reason" required>
								<option value="">Choose reason</option>
								<?php
								foreach ( self::report_reasons() as $key => $label ) {
									printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) );
								}
								?>
							</select>
						</label>
						<label>
							Report details
							<textarea name="details" maxlength="1500" required></textarea>
						</label>
						<button type="submit">Send Report</button>
					</form>
				</details>
				<p id="<?php echo esc_attr( $status_id ); ?>" class="svw-action-status" data-svw-status role="status" aria-live="polite"></p>
			<?php else : ?>
				<a href="<?php echo esc_url( wp_login_url( get_permalink( $video_id ) ) ); ?>">Log in to react, save, or report</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Process an authenticated AJAX action.
	 *
	 * @return void
	 */
	public function ajax() {
		check_ajax_referer( 'svw_action', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Log in first.' ), 401 );
		}

		$video_id = isset( $_POST['videoId'] ) ? absint( $_POST['videoId'] ) : 0;
		$action   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( SVW_Helpers::TYPE !== get_post_type( $video_id ) || 'publish' !== get_post_status( $video_id ) ) {
			wp_send_json_error( array( 'message' => 'Video not found.' ), 404 );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		if ( in_array( $action, array( 'like', 'dislike' ), true ) ) {
			$table = $wpdb->prefix . 'svw_reactions';
			$old   = (string) $wpdb->get_var(
				$wpdb->prepare( "SELECT reaction FROM {$table} WHERE user_id = %d AND video_id = %d", $user_id, $video_id )
			);
			if ( $old === $action ) {
				$result = $wpdb->delete( $table, array( 'user_id' => $user_id, 'video_id' => $video_id ), array( '%d', '%d' ) );
			} else {
				$result = $wpdb->replace(
					$table,
					array(
						'user_id'    => $user_id,
						'video_id'   => $video_id,
						'reaction'   => $action,
						'created_at' => current_time( 'mysql', true ),
					),
					array( '%d', '%d', '%s', '%s' )
				);
			}
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => 'The reaction could not be saved.' ), 500 );
			}
			self::recount_video( $video_id );
			wp_send_json_success( array( 'reload' => true ) );
		}

		if ( 'save' === $action ) {
			$table  = $wpdb->prefix . 'svw_saves';
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND video_id = %d", $user_id, $video_id )
			);
			if ( $exists ) {
				$result = $wpdb->delete( $table, array( 'id' => absint( $exists ) ), array( '%d' ) );
			} else {
				$result = $wpdb->insert(
					$table,
					array(
						'user_id'    => $user_id,
						'video_id'   => $video_id,
						'progress'   => SVW_Helpers::history_progress( $user_id, $video_id ),
						'updated_at' => current_time( 'mysql', true ),
					),
					array( '%d', '%d', '%d', '%s' )
				);
			}
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => 'The saved-video state could not be changed.' ), 500 );
			}
			wp_send_json_success( array( 'reload' => true ) );
		}

		if ( 'report' === $action ) {
			$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
			$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
			if ( ! isset( self::report_reasons()[ $reason ] ) || ! trim( $details ) ) {
				wp_send_json_error( array( 'message' => 'Complete the report.' ), 400 );
			}
			$result = $wpdb->insert(
				$wpdb->prefix . 'svw_reports',
				array(
					'user_id'    => $user_id,
					'video_id'   => $video_id,
					'reason'     => $reason,
					'details'    => $details,
					'status'     => 'open',
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => 'The report could not be recorded. Please try again.' ), 500 );
			}
			SVW_Helpers::audit( 'report', $wpdb->insert_id, 'submitted', '', 'open', '', array( 'video_id' => $video_id ), $user_id );
			wp_send_json_success( array( 'message' => 'Report received.' ) );
		}

		if ( 'progress' === $action ) {
			$progress = isset( $_POST['progress'] ) ? absint( $_POST['progress'] ) : 0;
			$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : absint( SVW_Helpers::meta( $video_id, 'duration', 0 ) );
			$duration = min( $duration, 100 * HOUR_IN_SECONDS );
			$progress = $duration ? min( $progress, $duration ) : $progress;
			$completed = $duration > 0 && $progress >= max( 1, $duration - 10 ) ? 1 : 0;
			$result = $wpdb->replace(
				$wpdb->prefix . 'svw_history',
				array(
					'user_id'    => $user_id,
					'video_id'   => $video_id,
					'progress'   => $progress,
					'duration'   => $duration,
					'completed'  => $completed,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%d', '%d', '%s' )
			);
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => 'Viewing progress could not be saved.' ), 500 );
			}
			$wpdb->update(
				$wpdb->prefix . 'svw_saves',
				array( 'progress' => $progress, 'updated_at' => current_time( 'mysql', true ) ),
				array( 'user_id' => $user_id, 'video_id' => $video_id ),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);
			wp_send_json_success( array( 'saved' => true ) );
		}

		wp_send_json_error( array( 'message' => 'Invalid action.' ), 400 );
	}

	/**
	 * Recalculate reaction totals and ranking score.
	 *
	 * @param int $video_id Video ID.
	 * @return void
	 */
	public static function recount_video( $video_id ) {
		global $wpdb;
		$video_id = absint( $video_id );
		$likes    = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}svw_reactions WHERE video_id = %d AND reaction = 'like'", $video_id )
		);
		$dislikes = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}svw_reactions WHERE video_id = %d AND reaction = 'dislike'", $video_id )
		);
		$views = absint( SVW_Helpers::meta( $video_id, 'views', 0 ) );
		update_post_meta( $video_id, '_svw_likes', $likes );
		update_post_meta( $video_id, '_svw_dislikes', $dislikes );
		update_post_meta( $video_id, '_svw_score', max( 0, $views + ( $likes * 5 ) - $dislikes ) );
	}

	/**
	 * Record a bounded public view and a private history entry.
	 *
	 * @return void
	 */
	public function record_view() {
		if ( ! is_singular( SVW_Helpers::TYPE ) || is_preview() ) {
			return;
		}

		$video_id = get_queried_object_id();
		if ( 'publish' !== get_post_status( $video_id ) || current_user_can( 'manage_video_wall' ) || self::is_probable_bot() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$this->touch_history( get_current_user_id(), $video_id );
		}

		$cookie = 'svw_viewed_' . $video_id;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}

		global $wpdb;
		add_post_meta( $video_id, '_svw_views', 0, true );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = '_svw_views'",
				$video_id
			)
		);
		self::recount_video( $video_id );

		setcookie(
			$cookie,
			'1',
			array(
				'expires'  => time() + ( 12 * HOUR_IN_SECONDS ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Add or refresh a history row without destroying existing progress.
	 *
	 * @param int $user_id  User ID.
	 * @param int $video_id Video ID.
	 * @return void
	 */
	private function touch_history( $user_id, $video_id ) {
		global $wpdb;
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT progress, duration, completed FROM {$wpdb->prefix}svw_history WHERE user_id = %d AND video_id = %d",
				$user_id,
				$video_id
			)
		);
		$wpdb->replace(
			$wpdb->prefix . 'svw_history',
			array(
				'user_id'    => $user_id,
				'video_id'   => $video_id,
				'progress'   => $existing ? absint( $existing->progress ) : 0,
				'duration'   => $existing ? absint( $existing->duration ) : absint( SVW_Helpers::meta( $video_id, 'duration', 0 ) ),
				'completed'  => $existing ? absint( $existing->completed ) : 0,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Prevent abusive AJAX bursts.
	 *
	 * @return void
	 */
	public function rate_limit() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$key   = 'svw_action_' . get_current_user_id();
		$count = absint( get_transient( $key ) );
		if ( $count >= 60 ) {
			wp_send_json_error( array( 'message' => 'Please wait before trying again.' ), 429 );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Remove plugin-owned rows when a video is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_video_data( $post_id ) {
		if ( SVW_Helpers::TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		foreach ( array( 'svw_reactions', 'svw_saves', 'svw_reports', 'svw_history' ) as $table ) {
			$wpdb->delete( $wpdb->prefix . $table, array( 'video_id' => absint( $post_id ) ), array( '%d' ) );
		}
		SVW_Helpers::audit( 'video', $post_id, 'deleted', get_post_status( $post_id ), 'deleted' );
	}

	/**
	 * Remove or anonymize plugin-owned rows when a user is deleted.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function delete_user_data( $user_id ) {
		global $wpdb;
		$video_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT video_id FROM {$wpdb->prefix}svw_reactions WHERE user_id = %d", absint( $user_id ) )
		);
		$wpdb->delete( $wpdb->prefix . 'svw_reactions', array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'svw_saves', array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'svw_history', array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
		$wpdb->update(
			$wpdb->prefix . 'svw_reports',
			array( 'user_id' => 0, 'details' => '[Reporter account removed]' ),
			array( 'user_id' => absint( $user_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		$wpdb->update( $wpdb->prefix . 'svw_audit', array( 'actor_id' => 0 ), array( 'actor_id' => absint( $user_id ) ), array( '%d' ), array( '%d' ) );
		foreach ( $video_ids as $video_id ) {
			self::recount_video( $video_id );
		}
	}

	/**
	 * Approved report reasons.
	 *
	 * @return array<string,string>
	 */
	private static function report_reasons() {
		return array(
			'medical-safety'  => 'Medical safety concern',
			'copyright'       => 'Copyright violation',
			'patient-privacy' => 'Patient privacy',
			'false-credentials' => 'False credentials',
			'misleading-claim'  => 'Misleading health claim',
			'spam'              => 'Spam',
			'harassment'        => 'Harassment',
			'inappropriate'     => 'Inappropriate content',
			'other'             => 'Other',
		);
	}

	/**
	 * Basic bot suppression for public view counts.
	 *
	 * @return bool
	 */
	private static function is_probable_bot() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		return '' === $user_agent || (bool) preg_match( '/bot|crawler|spider|slurp|headless|preview|facebookexternalhit|whatsapp/', $user_agent );
	}
}
