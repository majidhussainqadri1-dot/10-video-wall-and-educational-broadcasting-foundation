<?php
/**
 * Shared helpers for Video Wall.
 *
 * @package Video_Wall
 */

defined( 'ABSPATH' ) || exit;

final class SVW_Helpers {
	const TYPE = 'svw_video';
	const TAX  = 'svw_category';

	/**
	 * Approved publishing categories.
	 *
	 * @return array<string,string>
	 */
	public static function categories() {
		return array(
			'founder-update'             => 'Founder Update',
			'classical-homeopathy'       => 'Classical Homeopathy',
			'homeopathy-education'       => 'Homeopathy Education',
			'materia-medica'             => 'Materia Medica',
			'repertory'                  => 'Repertory',
			'clinical-education'         => 'Clinical Education',
			'patient-cases'              => 'Patient Cases',
			'homeopathy-philosophy'      => 'Homeopathy Philosophy',
			'research'                   => 'Research',
			'nutrition'                  => 'Nutrition',
			'public-health-education'    => 'Public Health Education',
			'pathology'                  => 'Pathology',
			'anatomy'                    => 'Anatomy',
			'principles-hygiene'         => 'Principles of Hygiene',
			'islamic-spiritual-healing'  => 'Islamic Spiritual Healing',
			'platform-news'              => 'Platform News',
		);
	}

	/**
	 * Whether a user is the configured Founder.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id > 0 && $user_id === absint( get_option( 'spf_founder_user_id', 0 ) );
	}

	/**
	 * Whether a user is currently a verified doctor.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id > 0
			&& class_exists( 'SPD_Helpers' )
			&& SPD_Helpers::is_doctor( $user_id )
			&& 'verified' === SPD_Helpers::verification_status( $user_id );
	}

	/**
	 * Whether a user may submit through the controlled frontend workflow.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_submit( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id > 0 && (
			user_can( $user_id, 'manage_video_wall' )
			|| self::founder( $user_id )
			|| self::doctor( $user_id )
		);
	}

	/**
	 * Initial moderation state for a submitted video.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function initial_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'manage_video_wall' ) || self::founder( $user_id ) ? 'publish' : 'pending';
	}

	/**
	 * Read plugin post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta suffix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function meta( $post_id, $key, $default = '' ) {
		$value = get_post_meta( absint( $post_id ), '_svw_' . sanitize_key( $key ), true );
		return '' === $value ? $default : $value;
	}

	/**
	 * Get the first approved category name.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function category( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), self::TAX );
		return $terms && ! is_wp_error( $terms ) ? (string) $terms[0]->name : '';
	}

	/**
	 * Get the first approved category slug.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function category_slug( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), self::TAX );
		return $terms && ! is_wp_error( $terms ) ? (string) $terms[0]->slug : '';
	}

	/**
	 * Format seconds as HH:MM:SS.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	public static function duration( $seconds ) {
		$seconds = absint( $seconds );
		return $seconds
			? sprintf( '%02d:%02d:%02d', floor( $seconds / 3600 ), floor( ( $seconds % 3600 ) / 60 ), $seconds % 60 )
			: '';
	}

	/**
	 * Parse a strict HH:MM:SS duration.
	 *
	 * @param string $value Duration text.
	 * @return int|false Seconds, or false when invalid.
	 */
	public static function parse_duration( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{1,3}):([0-5]\d):([0-5]\d)$/', $value, $matches ) ) {
			return false;
		}

		$hours   = (int) $matches[1];
		$minutes = (int) $matches[2];
		$seconds = (int) $matches[3];
		$total    = ( $hours * HOUR_IN_SECONDS ) + ( $minutes * MINUTE_IN_SECONDS ) + $seconds;

		return $total > 0 && $total <= ( 100 * HOUR_IN_SECONDS ) ? $total : false;
	}

	/**
	 * Validate and normalize a supported external video URL.
	 *
	 * @param string $source Source name.
	 * @param string $url    Submitted URL.
	 * @return array<string,string>|WP_Error
	 */
	public static function normalize_external_url( $source, $url ) {
		$source = sanitize_key( $source );
		$url    = esc_url_raw( trim( (string) $url ), array( 'https' ) );

		if ( ! $url ) {
			return new WP_Error( 'svw_missing_url', __( 'A valid HTTPS video URL is required.', 'video-wall' ) );
		}

		$parts = wp_parse_url( $url );
		$host  = isset( $parts['host'] ) ? strtolower( rtrim( $parts['host'], '.' ) ) : '';
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		if ( 'youtube' === $source ) {
			$allowed_hosts = array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be' );
			if ( ! in_array( $host, $allowed_hosts, true ) ) {
				return new WP_Error( 'svw_invalid_youtube_host', __( 'Use an official YouTube URL.', 'video-wall' ) );
			}

			$video_id = '';
			if ( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) ) {
				$video_id = trim( $path, '/' );
			} elseif ( isset( $query['v'] ) ) {
				$video_id = (string) $query['v'];
			} elseif ( preg_match( '~^/(?:embed|shorts)/([A-Za-z0-9_-]{6,20})/?$~', $path, $matches ) ) {
				$video_id = $matches[1];
			}

			if ( ! preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $video_id ) ) {
				return new WP_Error( 'svw_invalid_youtube_id', __( 'The YouTube video ID is invalid.', 'video-wall' ) );
			}

			return array(
				'canonical' => 'https://www.youtube.com/watch?v=' . rawurlencode( $video_id ),
				'embed'     => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video_id ),
				'id'        => $video_id,
			);
		}

		if ( 'vimeo' === $source ) {
			$allowed_hosts = array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' );
			if ( ! in_array( $host, $allowed_hosts, true ) ) {
				return new WP_Error( 'svw_invalid_vimeo_host', __( 'Use an official Vimeo URL.', 'video-wall' ) );
			}

			if ( ! preg_match( '~/(?:video/)?([0-9]{5,12})(?:/|$)~', $path, $matches ) ) {
				return new WP_Error( 'svw_invalid_vimeo_id', __( 'The Vimeo video ID is invalid.', 'video-wall' ) );
			}

			$video_id = $matches[1];
			return array(
				'canonical' => 'https://vimeo.com/' . rawurlencode( $video_id ),
				'embed'     => 'https://player.vimeo.com/video/' . rawurlencode( $video_id ),
				'id'        => $video_id,
			);
		}

		return new WP_Error( 'svw_invalid_source', __( 'The selected video source is not supported.', 'video-wall' ) );
	}

	/**
	 * Render a safe player.
	 *
	 * @param int $post_id Video post ID.
	 * @return string
	 */
	public static function embed( $post_id ) {
		$post_id = absint( $post_id );
		$source  = self::meta( $post_id, 'source' );
		$title   = get_the_title( $post_id );

		if ( in_array( $source, array( 'youtube', 'vimeo' ), true ) ) {
			$embed_url = self::meta( $post_id, 'embed_url' );
			if ( ! $embed_url ) {
				$normalized = self::normalize_external_url( $source, self::meta( $post_id, 'video_url' ) );
				$embed_url  = is_wp_error( $normalized ) ? '' : $normalized['embed'];
			}
			if ( ! $embed_url ) {
				return '';
			}

			return sprintf(
				'<iframe src="%1$s" title="%2$s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>',
				esc_url( $embed_url ),
				esc_attr( $title )
			);
		}

		if ( 'local' === $source ) {
			$attachment_id = absint( self::meta( $post_id, 'attachment_id' ) );
			$src           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			if ( ! $src ) {
				return '';
			}

			$resume = is_user_logged_in() ? self::history_progress( get_current_user_id(), $post_id ) : 0;
			$video  = wp_video_shortcode(
				array(
					'src'     => $src,
					'preload' => 'metadata',
				)
			);

			if ( is_user_logged_in() ) {
				return sprintf(
					'<div class="svw-local-player" data-svw-local-player data-video-id="%1$d" data-resume="%2$d">%3$s</div>',
					$post_id,
					absint( $resume ),
					$video // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}

			return '<div class="svw-local-player">' . $video . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return '';
	}

	/**
	 * Get a factual public author label without implying verification.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function author_label( $user_id ) {
		$user_id = absint( $user_id );
		if ( self::founder( $user_id ) ) {
			return 'Verified Founder';
		}
		if ( self::doctor( $user_id ) ) {
			return 'Verified Doctor';
		}
		if ( user_can( $user_id, 'manage_video_wall' ) ) {
			return 'Platform Administrator';
		}
		return 'Video Contributor';
	}

	/**
	 * Read history progress.
	 *
	 * @param int $user_id  User ID.
	 * @param int $video_id Video ID.
	 * @return int
	 */
	public static function history_progress( $user_id, $video_id ) {
		global $wpdb;
		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT progress FROM {$wpdb->prefix}svw_history WHERE user_id = %d AND video_id = %d",
					absint( $user_id ),
					absint( $video_id )
				)
			)
		);
	}

	/**
	 * Write an immutable operational audit entry.
	 *
	 * @param string $object_type    Object type.
	 * @param int    $object_id      Object ID.
	 * @param string $action         Action.
	 * @param string $previous_state Previous state.
	 * @param string $new_state      New state.
	 * @param string $note           Internal note.
	 * @param array  $metadata       Additional metadata.
	 * @param int    $actor_id       Actor ID.
	 * @return bool
	 */
	public static function audit( $object_type, $object_id, $action, $previous_state = '', $new_state = '', $note = '', $metadata = array(), $actor_id = 0 ) {
		global $wpdb;
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		$result   = $wpdb->insert(
			$wpdb->prefix . 'svw_audit',
			array(
				'object_type'    => sanitize_key( $object_type ),
				'object_id'      => absint( $object_id ),
				'action'         => sanitize_key( $action ),
				'previous_state' => sanitize_key( $previous_state ),
				'new_state'      => sanitize_key( $new_state ),
				'actor_id'       => $actor_id,
				'note'           => sanitize_textarea_field( $note ),
				'metadata'       => wp_json_encode( $metadata ),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return false !== $result;
	}

	/**
	 * Return page IDs that contain private account data.
	 *
	 * @return int[]
	 */
	public static function private_page_ids() {
		$map = (array) get_option( 'svw_page_map', array() );
		return array_values(
			array_filter(
				array(
					isset( $map['saved'] ) ? absint( $map['saved'] ) : 0,
					isset( $map['history'] ) ? absint( $map['history'] ) : 0,
				)
			)
		);
	}

	/**
	 * Render companion navigation where available.
	 *
	 * @return string
	 */
	public static function nav() {
		return class_exists( 'SDD_Helpers' )
			? str_replace( 'class="sdd-main-nav"', 'class="svw-main-nav"', SDD_Helpers::navigation() )
			: '';
	}
}
