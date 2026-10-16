<?php
/** Current sequential review hardening. Each section is tied to a completed review round. */
defined( 'ABSPATH' ) || exit;

final class VWLB_Sequential_Review_Hardening {
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_overrides' ), 100 );
		add_filter( 'vwlb_asset_technical_validation', array( __CLASS__, 'enforce_private_signature_validation' ), 100, 2 );
		add_filter( 'vwlb_remote_url_allowed', array( __CLASS__, 'validate_remote_url_dns' ), PHP_INT_MAX, 2 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'enforce_command_idempotency_after' ), 9, 3 );
	}

	public static function register_rest_overrides() {
		foreach ( VWLB_Contracts::namespaces() as $namespace ) {
			register_rest_route(
				$namespace,
				'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete',
				array(
					'methods' => 'POST',
					'callback' => array( __CLASS__, 'complete_resumable' ),
					'permission_callback' => function() { return is_user_logged_in(); },
				),
				true
			);
			register_rest_route(
				$namespace,
				'/captions/(?P<id>[A-Za-z0-9_-]+)',
				array(
					'methods' => 'GET',
					'callback' => array( __CLASS__, 'caption_delivery' ),
					'permission_callback' => '__return_true',
				),
				true
			);
			register_rest_route(
				$namespace,
				'/videos/(?P<id>[A-Za-z0-9_-]+)/annotations',
				array(
					'methods' => 'POST',
					'callback' => array( __CLASS__, 'create_annotation' ),
					'permission_callback' => function() { return VWLB_Security::can( VWLB_Contracts::CAP_PUBLISH ); },
				),
				true
			);
			register_rest_route(
				$namespace,
				'/live-events/(?P<id>[A-Za-z0-9_-]+)/kill',
				array(
					'methods' => 'POST',
					'callback' => array( __CLASS__, 'kill_live' ),
					'permission_callback' => function() { return VWLB_Security::can( VWLB_Contracts::CAP_MODERATE ); },
				),
				true
			);
		}
	}

	private static function body( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : array();
	}

	private static function upload_token( WP_REST_Request $request ) {
		$body = self::body( $request );
		return VWLB_Helpers::text( $request->get_header( 'X-VWLB-Upload-Token' ) ?: ( $body['upload_token'] ?? '' ), 200 );
	}

	private static function response( $value, $status = null ) {
		if ( is_wp_error( $value ) ) return $value;
		$response = rest_ensure_response( $value );
		if ( null !== $status ) $response->set_status( (int) $status );
		$response->header( 'X-Sabri-File', '10' );
		$response->header( 'X-VWLB-Version', VWLB_VERSION );
		$response->header( 'X-VWLB-Canonical-API', VWLB_Contracts::CANONICAL_API_NAMESPACE );
		return $response;
	}

	/** R09: deliver each reviewed caption using its actual stored format. */
	public static function caption_delivery( WP_REST_Request $request ) {
		global $wpdb;
		$caption = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VWLB_Helpers::table('captions') . ' WHERE public_id=%s LIMIT 1', VWLB_Helpers::text( $request['id'], 80 ) ), ARRAY_A );
		if ( ! $caption || 'published' !== ( $caption['status'] ?? '' ) ) return VWLB_Helpers::error( 'vwlb_not_found', __( 'Caption not found.', VWLB_TEXT_DOMAIN ), 404 );
		$video = VWLB_Repository::find( 'videos', (int) $caption['video_id'] );
		if ( ! $video || ! VWLB_Security::can_view( $video, 'caption' ) ) return VWLB_Helpers::error( 'vwlb_not_found', __( 'Caption not found.', VWLB_TEXT_DOMAIN ), 404 );
		$type = array( 'vtt'=>'text/vtt; charset=UTF-8', 'srt'=>'application/x-subrip; charset=UTF-8', 'ttml'=>'application/ttml+xml; charset=UTF-8' );
		$format = VWLB_Helpers::enum( $caption['format'] ?? 'vtt', array_keys( $type ), 'vtt' );
		$response = new WP_REST_Response( (string) $caption['content'], 200 );
		$response->header( 'Content-Type', $type[$format] );
		$response->header( 'X-VWLB-Caption-Format', $format );
		if ( in_array( $video['visibility'], array( 'public','unlisted' ), true ) ) $response->header( 'Cache-Control', 'public, max-age=300' );
		else { $response->header( 'Cache-Control', 'private, no-store' ); $response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' ); }
		return $response;
	}

	private static function contains_raw_secret( $value ) {
		if ( ! is_array( $value ) ) return false;
		foreach ( $value as $key => $child ) {
			$key = sanitize_key( (string) $key );
			if ( ! str_ends_with( $key, '_ref' ) && in_array( $key, array( 'secret','password','api_key','access_token','refresh_token','private_key','token','stream_key' ), true ) ) return true;
			if ( is_array( $child ) && self::contains_raw_secret( $child ) ) return true;
		}
		return false;
	}

	/** R09: a timestamp correction is reviewed at creation, but publication fact is emitted only by the later publish transition. */
	public static function create_annotation( WP_REST_Request $request ) {
		$data = self::body( $request );
		if ( 'correction' !== sanitize_key( (string) ( $data['kind'] ?? '' ) ) ) {
			$row = VWLB_Future_Intelligence::create_annotation( $request['id'], $data );
			return self::response( is_wp_error($row) ? $row : self::annotation_dto($row), 201 );
		}
		$video = VWLB_Repository::find( 'videos', $request['id'] );
		if ( ! $video || ! VWLB_Security::can( VWLB_Contracts::CAP_PUBLISH, $video, 'future_annotation' ) || ! VWLB_Security::can( VWLB_Contracts::CAP_REVIEW, $video, 'future_annotation_review' ) ) return VWLB_Helpers::error( 'vwlb_review_required', __( 'Timestamp corrections require independent review permission.', VWLB_TEXT_DOMAIN ), 403 );
		$source = VWLB_Helpers::enum( $data['source'] ?? 'manual', array( 'manual','ai_assisted','imported' ), 'manual' );
		$start = max( 0, (int) ( $data['start_ms'] ?? 0 ) );
		$end = max( 0, (int) ( $data['end_ms'] ?? 0 ) );
		if ( $end && $end < $start ) return VWLB_Helpers::error( 'vwlb_annotation_time_invalid', __( 'Annotation end time cannot precede its start time.', VWLB_TEXT_DOMAIN ), 422 );
		$duration = max( 0, (int) ( $video['duration_seconds'] ?? 0 ) ) * 1000;
		if ( $duration && ( $start > $duration || ( $end && $end > $duration ) ) ) return VWLB_Helpers::error( 'vwlb_annotation_time_invalid', __( 'Annotation time must remain within the verified video duration.', VWLB_TEXT_DOMAIN ), 422 );
		$metadata = (array) ( $data['metadata'] ?? array() );
		if ( self::contains_raw_secret( $metadata ) ) return VWLB_Helpers::error( 'vwlb_annotation_secret_forbidden', __( 'Raw credentials cannot be stored in annotation metadata.', VWLB_TEXT_DOMAIN ), 422 );
		global $wpdb; $now = VWLB_Helpers::now(); $public = VWLB_Helpers::public_id('ann');
		$row = array( 'public_id'=>$public,'video_id'=>(int)$video['id'],'kind'=>'correction','start_ms'=>$start,'end_ms'=>$end,'title'=>VWLB_Helpers::text($data['title']??'',255),'body'=>VWLB_Helpers::textarea($data['body']??''),'source_owner'=>VWLB_Helpers::text($data['source_owner']??'',64),'source_ref'=>VWLB_Helpers::text($data['source_ref']??'',191),'status'=>'reviewed','metadata_json'=>VWLB_Helpers::json_encode(array_merge($metadata,array('source_mode'=>$source))),'version'=>1,'created_by'=>get_current_user_id(),'reviewed_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now );
		if ( ! $wpdb->insert( VWLB_Helpers::table('video_annotations'), $row ) || ! (int) $wpdb->insert_id ) return VWLB_Helpers::error( 'vwlb_database_error', __( 'Annotation could not be saved.', VWLB_TEXT_DOMAIN ), 500 );
		$id = (int) $wpdb->insert_id;
		VWLB_Helpers::audit( 'annotation', $id, 'create', '', 'reviewed', '', array( 'video_id'=>$video['id'], 'kind'=>'correction', 'publication_event_deferred'=>true ) );
		return self::response( self::annotation_dto( array_merge( $row, array('id'=>$id) ) ), 201 );
	}

	private static function annotation_dto( $row ) {
		if ( ! is_array( $row ) ) return $row;
		$out = array(); foreach ( array('public_id','kind','start_ms','end_ms','title','body','source_owner','source_ref','status','version') as $field ) if ( array_key_exists( $field, $row ) ) $out[$field] = $row[$field];
		return $out;
	}

	/** R03: refuse completion when signature validation is unavailable, unknown or disallowed. */
	public static function complete_resumable( WP_REST_Request $request ) {
		global $wpdb;
		$public_id = VWLB_Helpers::text( $request['id'], 64 );
		$token = self::upload_token( $request );
		$table = VWLB_Helpers::table( 'upload_sessions' );
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE public_id=%s LIMIT 1", $public_id ), ARRAY_A );
		if ( ! $session || 'active' !== ( $session['status'] ?? '' ) || strtotime( ( $session['expires_at'] ?? '' ) . ' UTC' ) <= time() || (int) $session['owner_id'] !== get_current_user_id() || ! $token || ! password_verify( $token, (string) $session['token_hash'] ) ) {
			return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
		}
		if ( ! class_exists( 'finfo' ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_unavailable', __( 'Server-side file signature validation is unavailable. Retry after the media validator is restored.', VWLB_TEXT_DOMAIN ), 503 );
		}
		$name = sanitize_file_name( basename( (string) $session['private_filename'] ) );
		if ( ! $name || ! str_ends_with( $name, '.part' ) ) {
			return VWLB_Helpers::error( 'vwlb_private_filename_invalid', __( 'Private upload path is invalid.', VWLB_TEXT_DOMAIN ), 500 );
		}
		$path = trailingslashit( WP_CONTENT_DIR ) . VWLB_Extensions::PRIVATE_DIR . '/' . $name;
		if ( ! is_file( $path ) ) {
			return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
		}
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$detected = strtolower( trim( (string) $finfo->file( $path ) ) );
		if ( ! $detected ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_unknown', __( 'The uploaded file signature could not be determined.', VWLB_TEXT_DOMAIN ), 415 );
		}
		$asset = VWLB_Repository::find( 'media_assets', (int) $session['asset_id'] );
		if ( ! $asset ) return VWLB_Helpers::error( 'vwlb_asset_missing', __( 'Asset not found.', VWLB_TEXT_DOMAIN ), 404 );
		$declared = strtolower( trim( (string) ( $asset['mime'] ?? '' ) ) );
		if ( $declared && ! self::mime_compatible( $declared, $detected ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_mismatch', __( 'File signature does not match the declared media type.', VWLB_TEXT_DOMAIN ), 415 );
		}
		if ( ! $declared && ! self::detected_mime_allowed( $detected, $asset['media_class'] ?? 'video' ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_not_allowed', __( 'The detected file type is not allowed for media ingest.', VWLB_TEXT_DOMAIN ), 415 );
		}
		return self::response( VWLB_Extensions::complete_resumable( $public_id, $token ) );
	}

	/** R06: custom/server-fetched remote URLs must pass WordPress DNS/private-address validation too. */
	public static function validate_remote_url_dns( $url, $host ) {
		if ( ! is_string( $url ) || '' === $url ) return '';
		if ( ! function_exists( 'wp_http_validate_url' ) ) return '';
		$validated = wp_http_validate_url( $url );
		return is_string( $validated ) && '' !== $validated ? $url : '';
	}

	/** R07: callers that historically ignored command-level replay persistence cannot return a false durable success. */
	public static function enforce_command_idempotency_after( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || in_array( strtoupper( (string) $request->get_method() ), array( 'GET','HEAD','OPTIONS' ), true ) ) return $response;
		$callback = $handler['callback'] ?? null;
		$name = is_array( $callback ) && isset( $callback[1] ) ? sanitize_key( (string) $callback[1] ) : '';
		$scope = '';
		if ( 'create_video' === $name ) $scope = 'create_video';
		elseif ( in_array( $name, array( 'schedule_live','premiere_create' ), true ) ) $scope = 'schedule_live';
		if ( ! $scope ) return $response;
		$key = VWLB_Helpers::text( $request->get_header( 'Idempotency-Key' ), 128 );
		if ( ! $key ) return $response;
		global $wpdb;
		$actor = get_current_user_id() ? 'u' . get_current_user_id() : 'a' . substr( VWLB_Helpers::ip_hash(), 0, 32 );
		$db_scope = substr( sanitize_key( $scope ) . ':' . $actor, 0, 100 );
		$table = VWLB_Helpers::table( 'idempotency' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM $table WHERE idempotency_key=%s AND scope=%s LIMIT 1", $key, $db_scope ), ARRAY_A );
		$wrapped = is_wp_error( $response ) ? null : rest_ensure_response( $response );
		$failed = is_wp_error( $response ) || ( $wrapped && (int) $wrapped->get_status() >= 500 );
		if ( $failed ) {
			if ( $row && 'processing' === ( $row['status'] ?? '' ) ) {
				$aborted = VWLB_Security::idempotency_abort( $key, $scope );
				if ( is_wp_error( $aborted ) ) return $aborted;
			}
			return $response;
		}
		if ( ! $row || 'complete' !== ( $row['status'] ?? '' ) ) {
			return VWLB_Helpers::error( 'vwlb_idempotency_persist_failed', __( 'The operation completed but its command replay state could not be verified safely.', VWLB_TEXT_DOMAIN ), 503 );
		}
		return $response;
	}

	/** R05: local emergency-end durability must not roll back after an irreversible provider termination attempt. */
	public static function kill_live( WP_REST_Request $request ) {
		$body = self::body( $request );
		$event = VWLB_Repository::find( 'live_events', $request['id'] );
		if ( ! $event ) return VWLB_Helpers::error( 'vwlb_live_missing', __( 'Live event not found.', VWLB_TEXT_DOMAIN ), 404 );
		if ( ! in_array( $event['status'], array( 'rehearsal','ready','live','interrupted' ), true ) ) return VWLB_Helpers::error( 'vwlb_live_state_invalid', __( 'Emergency end is not available in this state.', VWLB_TEXT_DOMAIN ), 409 );
		if ( ! VWLB_Security::can( VWLB_Contracts::CAP_MODERATE, $event, 'emergency_end' ) ) return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot end this broadcast.', VWLB_TEXT_DOMAIN ), 403 );
		$step = VWLB_Security::require_step_up( 'emergency_end' );
		if ( is_wp_error( $step ) ) return $step;
		$expected = absint( $body['version'] ?? 0 );
		$reason = VWLB_Helpers::text( $body['reason'] ?? 'emergency_end', 191 );
		global $wpdb;
		$updated = VWLB_DB::transaction( function() use ( $event, $expected, $reason, $wpdb ) {
			$changed = VWLB_Repository::update_versioned( 'live_events', $event['id'], $expected, array( 'status'=>'ended','kill_switch'=>1,'actual_end'=>VWLB_Helpers::now() ) );
			if ( is_wp_error( $changed ) ) return $changed;
			$revoked = $wpdb->update( VWLB_Helpers::table( 'stream_credentials' ), array( 'status'=>'revoked','revoked_at'=>VWLB_Helpers::now() ), array( 'live_event_id'=>$event['id'],'status'=>'active' ) );
			if ( false === $revoked ) return VWLB_Helpers::error( 'vwlb_database_error', __( 'Active stream credentials could not be revoked.', VWLB_TEXT_DOMAIN ), 500 );
			$policy = VWLB_Helpers::json( $changed['recording_policy_json'] ?? '{}' );
			if ( ! empty( $policy['record'] ) ) {
				$saved = $wpdb->insert( VWLB_Helpers::table( 'processing_jobs' ), array(
					'public_id'=>VWLB_Helpers::public_id('job'),'asset_id'=>(int)$changed['recording_asset_id'],
					'job_type'=>'finalize_live_recording','provider'=>$changed['provider'],'status'=>'pending','priority'=>10,
					'attempts'=>0,'max_attempts'=>8,'available_at'=>VWLB_Helpers::now(),
					'input_json'=>VWLB_Helpers::json_encode(array('live_event_id'=>$changed['id'])),'output_json'=>'{}',
					'created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now(),
				) );
				if ( ! $saved || ! (int) $wpdb->insert_id ) return VWLB_Helpers::error( 'vwlb_recording_queue_failed', __( 'Recording finalization could not be queued durably.', VWLB_TEXT_DOMAIN ), 503 );
			}
			VWLB_Helpers::audit( 'live', $event['id'], 'emergency_end', $event['status'], 'ended', $reason );
			VWLB_Helpers::outbox( 'LiveBroadcastEnded', 'live', $event['id'], array( 'emergency'=>true,'reason_category'=>sanitize_key($reason) ) );
			return $changed;
		} );
		if ( is_wp_error( $updated ) ) return $updated;
		try {
			do_action( 'vwlb_provider_emergency_end', $event, $reason );
			$provider_result = apply_filters( 'vwlb_provider_emergency_end_result', null, $event, $reason );
		} catch ( Throwable $error ) {
			do_action( 'vwlb_operational_failure', 'live', 'vwlb_provider_emergency_end_exception', array( 'live_public_id'=>$event['public_id'] ) );
			return VWLB_Helpers::error( 'vwlb_provider_emergency_end_reconcile_required', __( 'The local broadcast was ended safely, but provider termination could not be confirmed. Reconciliation is required.', VWLB_TEXT_DOMAIN ), 503, array( 'local_state'=>'ended','reconcile_required'=>true,'live_public_id'=>$event['public_id'] ) );
		}
		$confirmed = true === $provider_result || ( is_array( $provider_result ) && in_array( $provider_result['status'] ?? '', array( 'ended','stopped','terminated' ), true ) );
		if ( ! $confirmed ) {
			do_action( 'vwlb_operational_failure', 'live', 'vwlb_provider_emergency_end_unconfirmed', array( 'live_public_id'=>$event['public_id'] ) );
			return VWLB_Helpers::error( 'vwlb_provider_emergency_end_reconcile_required', __( 'The local broadcast was ended safely, but provider termination is unconfirmed. Reconciliation is required.', VWLB_TEXT_DOMAIN ), 503, array( 'local_state'=>'ended','reconcile_required'=>true,'live_public_id'=>$event['public_id'] ) );
		}
		return self::response( VWLB_Repository::live_mutation_dto( $updated ) );
	}

	/** R03: the asynchronous verification worker must not turn a clean malware result into a MIME/signature bypass. */
	public static function enforce_private_signature_validation( $allowed, $asset ) {
		if ( ! $allowed || ! is_array( $asset ) ) return false;
		$storage = VWLB_Helpers::json( $asset['storage_json'] ?? '{}' );
		if ( 'private_file' !== ( $storage['driver'] ?? '' ) ) return $allowed;
		if ( ! class_exists( 'finfo' ) ) return false;
		$name = sanitize_file_name( basename( (string) ( $storage['relative_path'] ?? '' ) ) );
		if ( ! $name || ! str_ends_with( $name, '.part' ) ) return false;
		$path = trailingslashit( WP_CONTENT_DIR ) . VWLB_Extensions::PRIVATE_DIR . '/' . $name;
		if ( ! is_file( $path ) ) return false;
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$detected = strtolower( trim( (string) $finfo->file( $path ) ) );
		if ( ! $detected ) return false;
		$declared = strtolower( trim( (string) ( $asset['mime'] ?? '' ) ) );
		if ( $declared ) return self::mime_compatible( $declared, $detected );
		return self::detected_mime_allowed( $detected, $asset['media_class'] ?? 'video' );
	}

	private static function detected_mime_allowed( $detected, $media_class ) {
		$allowed = (array) apply_filters( 'vwlb_allowed_mimes', array(
			'video/mp4','application/mp4','video/webm','video/ogg','application/ogg',
			'audio/mpeg','audio/mp3','audio/mp4','audio/ogg','image/jpeg','image/png','text/vtt',
		), $media_class );
		return in_array( strtolower( trim( (string) $detected ) ), array_map( 'strtolower', $allowed ), true );
	}

	private static function mime_compatible( $declared, $detected ) {
		$declared = strtolower( trim( (string) $declared ) );
		$detected = strtolower( trim( (string) $detected ) );
		if ( $declared === $detected ) return true;
		$families = array(
			'video/mp4'=>array('video/mp4','application/mp4'),
			'video/webm'=>array('video/webm','application/octet-stream'),
			'video/ogg'=>array('video/ogg','application/ogg'),
			'audio/mpeg'=>array('audio/mpeg','audio/mp3'),
			'audio/mp4'=>array('audio/mp4','video/mp4','application/mp4'),
			'audio/ogg'=>array('audio/ogg','application/ogg'),
		);
		return isset( $families[$declared] ) && in_array( $detected, $families[$declared], true );
	}
}
