<?php
/**
 * File 10 v1.1 extensions: resumable private ingest, chapters, waiting room,
 * recording consent, Q&A/resources, rights-aware downloads, aggregate creator
 * metrics, cross-file media contracts and non-destructive migration helpers.
 */
defined( 'ABSPATH' ) || exit;

final class VWLB_Extensions {
	const OPTION = 'vwlb_ext_schema_version';
	const PRIVATE_DIR = 'vwlb-private-media';

	public static function register() {
		add_filter( 'vwlb_process_job', array( __CLASS__, 'process_job' ), 10, 3 );
		add_filter( 'vwlb_local_processor_result', array( __CLASS__, 'local_processor' ), 10, 2 );
		add_filter( 'vwlb_reels_media_contract', array( __CLASS__, 'reels_media_contract' ), 10, 3 );
		add_filter( 'vwlb_finalize_live_recording', array( __CLASS__, 'recording_consent_gate' ), 1, 3 );
		add_filter( 'vwlb_file10_media_contract', array( __CLASS__, 'media_contract' ), 10, 3 );
		add_filter( 'vwlb_asset_technical_validation', array( __CLASS__, 'technical_validation' ), 20, 2 );
		add_filter( 'vwlb_video_publication_gate', array( __CLASS__, 'media_safety_gate' ), 20, 2 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'observe_rest' ), 20, 3 );
		add_action( 'vwlb_cleanup', array( __CLASS__, 'cleanup' ), 20 );
		if ( get_option( self::OPTION ) !== VWLB_EXT_SCHEMA_VERSION ) {
			self::install_schema();
		}
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = function( $n ) { return VWLB_Helpers::table( $n ); };
		$sql = array();

		$sql[] = "CREATE TABLE {$t('upload_sessions')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			asset_id bigint unsigned NOT NULL,
			owner_id bigint unsigned NOT NULL,
			token_hash varchar(255) NOT NULL,
			expected_bytes bigint unsigned NOT NULL DEFAULT 0,
			received_bytes bigint unsigned NOT NULL DEFAULT 0,
			next_offset bigint unsigned NOT NULL DEFAULT 0,
			chunk_size int unsigned NOT NULL DEFAULT 5242880,
			checksum_expected varchar(128) NOT NULL DEFAULT '',
			private_filename varchar(191) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			expires_at datetime NOT NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY owner_status (owner_id,status), KEY asset_id (asset_id), KEY expires_at (expires_at)
		) $c";

		$sql[] = "CREATE TABLE {$t('chapters')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint unsigned NOT NULL,
			start_seconds int unsigned NOT NULL DEFAULT 0,
			end_seconds int unsigned NULL,
			title varchar(255) NOT NULL,
			summary text NULL,
			source varchar(32) NOT NULL DEFAULT 'manual',
			status varchar(32) NOT NULL DEFAULT 'published',
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			UNIQUE KEY object_start (object_type,object_id,start_seconds),
			KEY object_status (object_type,object_id,status)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_attendees')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			state varchar(32) NOT NULL DEFAULT 'waiting',
			reminder_minutes int unsigned NOT NULL DEFAULT 15,
			recording_consent tinyint(1) NOT NULL DEFAULT 0,
			consent_version varchar(64) NOT NULL DEFAULT '',
			consented_at datetime NULL,
			joined_at datetime NULL,
			left_at datetime NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			UNIQUE KEY event_user (live_event_id,user_id),
			KEY state_event (state,live_event_id)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_questions')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			question text NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			answer text NULL,
			moderator_id bigint unsigned NOT NULL DEFAULT 0,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY event_status (live_event_id,status), KEY user_id (user_id)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_resources')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			title varchar(255) NOT NULL,
			resource_type varchar(32) NOT NULL DEFAULT 'link',
			url text NULL,
			attachment_id bigint unsigned NOT NULL DEFAULT 0,
			rights_status varchar(32) NOT NULL DEFAULT 'declared',
			status varchar(32) NOT NULL DEFAULT 'published',
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY event_status (live_event_id,status)
		) $c";

		$sql[] = "CREATE TABLE {$t('download_tokens')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			token_hash varchar(255) NOT NULL,
			user_id bigint unsigned NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint unsigned NOT NULL,
			rights_snapshot varchar(64) NOT NULL DEFAULT '',
			max_downloads int unsigned NOT NULL DEFAULT 1,
			download_count int unsigned NOT NULL DEFAULT 0,
			status varchar(32) NOT NULL DEFAULT 'active',
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY object_user (object_type,object_id,user_id), KEY expires_at (expires_at)
		) $c";

		$sql[] = "CREATE TABLE {$t('creator_metrics_daily')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			metric_date date NOT NULL,
			owner_id bigint unsigned NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint unsigned NOT NULL,
			views bigint unsigned NOT NULL DEFAULT 0,
			completions bigint unsigned NOT NULL DEFAULT 0,
			saves bigint unsigned NOT NULL DEFAULT 0,
			source_opens bigint unsigned NOT NULL DEFAULT 0,
			meaningful_comments bigint unsigned NOT NULL DEFAULT 0,
			harm_reports bigint unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY metric_object (metric_date,owner_id,object_type,object_id),
			KEY owner_date (owner_id,metric_date)
		) $c";

		$sql[] = "CREATE TABLE {$t('provider_health')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(64) NOT NULL,
			capability varchar(64) NOT NULL,
			state varchar(32) NOT NULL DEFAULT 'unknown',
			failures int unsigned NOT NULL DEFAULT 0,
			last_latency_ms int unsigned NOT NULL DEFAULT 0,
			circuit_open_until datetime NULL,
			last_error_code varchar(128) NOT NULL DEFAULT '',
			checked_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY provider_capability (provider,capability),
			KEY state (state)
		) $c";

		$sql[] = "CREATE TABLE {$t('premieres')} (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		public_id varchar(64) NOT NULL,
		video_id bigint unsigned NOT NULL,
		live_event_id bigint unsigned NOT NULL,
		owner_id bigint unsigned NOT NULL,
		status varchar(32) NOT NULL DEFAULT 'scheduled',
		scheduled_at datetime NOT NULL,
		version bigint unsigned NOT NULL DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
		UNIQUE KEY live_event_id (live_event_id), KEY video_schedule (video_id,scheduled_at),
		KEY owner_status (owner_id,status)
	) $c";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		$verified=VWLB_DB::verify_schema_sql($sql);if(is_wp_error($verified))return $verified;$podcasts=VWLB_Podcasts::install_schema();if(is_wp_error($podcasts))return $podcasts;$private=self::ensure_private_dir();if(is_wp_error($private))return $private;if(!update_option( self::OPTION, VWLB_EXT_SCHEMA_VERSION, false )&&get_option( self::OPTION )!==VWLB_EXT_SCHEMA_VERSION)return VWLB_Helpers::error('vwlb_schema_version_persist_failed',__('File 10 extension schema version could not be recorded.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit( 'system', 10, 'extension_schema_upgrade', '', VWLB_EXT_SCHEMA_VERSION, 'File 10 extension schema reconciled.' );return true;
	}

	private static function ensure_private_dir() {
		$base = trailingslashit( WP_CONTENT_DIR ) . self::PRIVATE_DIR;
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return VWLB_Helpers::error( 'vwlb_private_storage_unavailable', __( 'Private media storage could not be created.', VWLB_TEXT_DOMAIN ), 503 );
		}
		$protect = array(
			'index.php' => "<?php\nhttp_response_code(404);\nexit;\n",
			'.htaccess' => "Require all denied\nDeny from all\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>",
		);
		foreach ( $protect as $file => $content ) {
			$path = trailingslashit( $base ) . $file;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $content, LOCK_EX );
			}
		}
		return $base;
	}

	private static function upload_path( $filename ) {
		$base = self::ensure_private_dir();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$name = sanitize_file_name( basename( (string) $filename ) );
		if ( ! $name || ! str_ends_with( $name, '.part' ) ) {
			return VWLB_Helpers::error( 'vwlb_private_filename_invalid', __( 'Private upload path is invalid.', VWLB_TEXT_DOMAIN ), 500 );
		}
		return trailingslashit( $base ) . $name;
	}

	private static function abandon_resumable_asset( $media, $reason ) {
		if ( ! is_array( $media ) || empty( $media['id'] ) ) return true;
		global $wpdb;$table=VWLB_Helpers::table('media_assets');$id=(int)$media['id'];$uid=get_current_user_id();
		$deleted=$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id=%d AND owner_id=%d AND status='initiated' AND source_object_id=0",$id,$uid));
		if(1===$deleted){VWLB_Helpers::audit('asset',$id,'resumable_setup_compensated','initiated','deleted',VWLB_Helpers::text($reason,191));return true;}
		$row=VWLB_Repository::find('media_assets',$id);if(!$row)return true;
		$changed=$wpdb->update($table,array('status'=>'failed','error_code'=>'vwlb_upload_session_failed','error_message'=>VWLB_Helpers::text($reason,500),'version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$id,'version'=>$row['version'],'owner_id'=>$uid));
		if(false===$changed)return VWLB_Helpers::error('vwlb_upload_compensation_failed',__('Upload setup failed and its asset could not be compensated safely.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit('asset',$id,'resumable_setup_compensated','initiated','failed',VWLB_Helpers::text($reason,191));return true;
	}

	public static function initiate_resumable( $data ) {
		if ( ! VWLB_Security::can( VWLB_Contracts::CAP_SUBMIT, null, 'initiate_resumable_upload' ) ) {
			return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot upload media.', VWLB_TEXT_DOMAIN ), 403 );
		}
		$bytes = max( 1, (int) ( $data['bytes'] ?? 0 ) );
		$max = (int) apply_filters( 'vwlb_max_upload_bytes', 1024 * 1024 * 1024, $data['media_class'] ?? 'video' );
		if ( $bytes > $max ) {
			return VWLB_Helpers::error( 'vwlb_file_too_large', __( 'The file exceeds the allowed size.', VWLB_TEXT_DOMAIN ), 413 );
		}
		$checksum = strtolower( VWLB_Helpers::text( $data['checksum'] ?? '', 128 ) );
		if ( $checksum && ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) ) {
			return VWLB_Helpers::error( 'vwlb_checksum_invalid', __( 'A SHA-256 checksum is required when a checksum is supplied.', VWLB_TEXT_DOMAIN ), 422 );
		}
		$media = VWLB_Media::initiate( array_merge( $data, array( 'bytes'=>$bytes, 'checksum'=>$checksum ) ) );
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$filename = 'upload-' . preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $media['public_id'] ) . '-' . bin2hex( random_bytes( 8 ) ) . '.part';
		$path = self::upload_path( $filename );
		if ( is_wp_error( $path ) || false === @file_put_contents( $path, '', LOCK_EX ) ) {
			$failure=is_wp_error($path)?$path:VWLB_Helpers::error( 'vwlb_private_storage_unavailable', __( 'Private media storage is unavailable.', VWLB_TEXT_DOMAIN ), 503 );$comp=self::abandon_resumable_asset($media,$failure->get_error_code());return is_wp_error($comp)?$comp:$failure;
		}
		@chmod( $path, 0600 );
		global $wpdb;
		$now = VWLB_Helpers::now();
		$public = VWLB_Helpers::public_id( 'upl' );
		$wpdb->insert(
			VWLB_Helpers::table( 'upload_sessions' ),
			array(
				'public_id'=>$public, 'asset_id'=>(int)$media['id'], 'owner_id'=>get_current_user_id(),
				'token_hash'=>password_hash( $token, PASSWORD_DEFAULT ), 'expected_bytes'=>$bytes,
				'received_bytes'=>0, 'next_offset'=>0,
				'chunk_size'=>max( 262144, min( 8 * 1024 * 1024, (int)($data['chunk_size'] ?? 5 * 1024 * 1024) ) ),
				'checksum_expected'=>$checksum, 'private_filename'=>$filename, 'status'=>'active',
				'expires_at'=>gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'version'=>1,
				'created_at'=>$now, 'updated_at'=>$now,
			)
		);
		if ( ! $wpdb->insert_id ) {
			@unlink( $path );$comp=self::abandon_resumable_asset($media,'upload_session_insert_failed');if(is_wp_error($comp))return $comp;
			return VWLB_Helpers::error( 'vwlb_database_error', __( 'Upload session could not be created.', VWLB_TEXT_DOMAIN ), 500 );
		}
		VWLB_Helpers::audit( 'upload_session', (int)$wpdb->insert_id, 'create', '', 'active', 'Resumable private upload initialized.' );
		return array(
			'session_id'=>$public, 'asset_public_id'=>$media['public_id'],
			'upload_token'=>$token, 'display_once'=>true, 'next_offset'=>0,
			'chunk_size'=>max( 262144, min( 8 * 1024 * 1024, (int)($data['chunk_size'] ?? 5 * 1024 * 1024) ) ),
			'expires_at'=>gmdate( 'c', time() + DAY_IN_SECONDS ),
		);
	}

	private static function upload_session( $public_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . VWLB_Helpers::table('upload_sessions') . ' WHERE public_id=%s LIMIT 1', VWLB_Helpers::text( $public_id, 64 ) ),
			ARRAY_A
		);
	}

	private static function authorize_upload_session( $session, $token ) {
		if ( ! $session || 'active' !== $session['status'] || strtotime( $session['expires_at'] . ' UTC' ) <= time() ) {
			return VWLB_Helpers::error( 'vwlb_upload_session_expired', __( 'The upload session is unavailable or expired.', VWLB_TEXT_DOMAIN ), 410 );
		}
		if ( (int)$session['owner_id'] !== get_current_user_id() || ! VWLB_Security::can( VWLB_Contracts::CAP_SUBMIT, null, 'write_upload_chunk' ) ) {
			return VWLB_Helpers::error( 'vwlb_not_found', __( 'Upload session not found.', VWLB_TEXT_DOMAIN ), 404 );
		}
		if ( ! $token || ! password_verify( (string)$token, $session['token_hash'] ) ) {
			return VWLB_Helpers::error( 'vwlb_upload_token_invalid', __( 'Upload token is invalid.', VWLB_TEXT_DOMAIN ), 401 );
		}
		return true;
	}

	public static function append_chunk( $public_id, $token, $offset, $body, $chunk_sha256 = '' ) {
		$rate = VWLB_Security::rate_limit( 'upload_chunk', 240, HOUR_IN_SECONDS );
		if ( is_wp_error( $rate ) ) return $rate;
		$session = self::upload_session( $public_id );
		$auth = self::authorize_upload_session( $session, $token );
		if ( is_wp_error( $auth ) ) return $auth;
		$offset = max( 0, (int)$offset );
		if ( $offset !== (int)$session['next_offset'] ) {
			return VWLB_Helpers::error( 'vwlb_upload_offset_conflict', __( 'Upload offset is stale. Resume from the server offset.', VWLB_TEXT_DOMAIN ), 409, array( 'next_offset'=>(int)$session['next_offset'] ) );
		}
		$length = strlen( (string)$body );
		if ( $length < 1 || $length > (int)$session['chunk_size'] ) {
			return VWLB_Helpers::error( 'vwlb_chunk_size_invalid', __( 'Upload chunk size is invalid.', VWLB_TEXT_DOMAIN ), 413 );
		}
		if ( $offset + $length > (int)$session['expected_bytes'] ) {
			return VWLB_Helpers::error( 'vwlb_upload_overflow', __( 'Upload exceeds the declared file size.', VWLB_TEXT_DOMAIN ), 422 );
		}
		$chunk_sha256 = strtolower( trim( (string)$chunk_sha256 ) );
		if ( $chunk_sha256 && ! hash_equals( $chunk_sha256, hash( 'sha256', (string)$body ) ) ) {
			return VWLB_Helpers::error( 'vwlb_chunk_checksum_mismatch', __( 'Upload chunk checksum did not match.', VWLB_TEXT_DOMAIN ), 422 );
		}
		$path = self::upload_path( $session['private_filename'] );
		if ( is_wp_error( $path ) ) return $path;
		$fp = @fopen( $path, 'c+b' );
		if ( ! $fp ) return VWLB_Helpers::error( 'vwlb_private_storage_unavailable', __( 'Private media storage is unavailable.', VWLB_TEXT_DOMAIN ), 503 );
		if ( ! flock( $fp, LOCK_EX ) ) { fclose($fp); return VWLB_Helpers::error( 'vwlb_upload_lock_failed', __( 'Upload is busy. Retry safely.', VWLB_TEXT_DOMAIN ), 409 ); }
		clearstatcache( true, $path );
		$actual = filesize( $path );
		if ( $actual !== $offset ) {
			flock($fp,LOCK_UN); fclose($fp);
			return VWLB_Helpers::error( 'vwlb_upload_storage_offset_conflict', __( 'Stored upload offset differs from the request.', VWLB_TEXT_DOMAIN ), 409, array( 'next_offset'=>(int)$actual ) );
		}
		fseek( $fp, $offset );
		$written = fwrite( $fp, (string)$body );
		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		if ( $written !== $length ) {
			return VWLB_Helpers::error( 'vwlb_upload_write_failed', __( 'The upload chunk could not be stored completely.', VWLB_TEXT_DOMAIN ), 500 );
		}
		$new_offset = $offset + $length;
		global $wpdb;
		$updated = $wpdb->update(
			VWLB_Helpers::table('upload_sessions'),
			array( 'received_bytes'=>$new_offset, 'next_offset'=>$new_offset, 'version'=>(int)$session['version']+1, 'updated_at'=>VWLB_Helpers::now() ),
			array( 'id'=>(int)$session['id'], 'version'=>(int)$session['version'], 'next_offset'=>$offset ),
			array( '%d','%d','%d','%s' ), array( '%d','%d','%d' )
		);
		if ( 1 !== $updated ) {
			$rollback=@fopen($path,'c+b');$rolled=false;if($rollback&&flock($rollback,LOCK_EX)){clearstatcache(true,$path);if(filesize($path)===$new_offset){$rolled=ftruncate($rollback,$offset);fflush($rollback);}flock($rollback,LOCK_UN);fclose($rollback);}
			if(!$rolled){$wpdb->update(VWLB_Helpers::table('upload_sessions'),array('status'=>'failed','updated_at'=>VWLB_Helpers::now()),array('id'=>(int)$session['id']));VWLB_Helpers::audit('upload_session',$session['id'],'chunk_compensation_failed','active','failed','Chunk was written but database CAS failed and file rollback could not be proved.');}
			return VWLB_Helpers::error( 'vwlb_upload_concurrency_conflict', __( 'The upload changed concurrently. Resume from the server offset.', VWLB_TEXT_DOMAIN ), 409 );
		}
		return array( 'session_id'=>$public_id, 'received_bytes'=>$new_offset, 'next_offset'=>$new_offset, 'complete'=>$new_offset === (int)$session['expected_bytes'] );
	}

	public static function complete_resumable( $public_id, $token ) {
		$session = self::upload_session( $public_id );
		$auth = self::authorize_upload_session( $session, $token );
		if ( is_wp_error( $auth ) ) return $auth;
		if ( (int)$session['received_bytes'] !== (int)$session['expected_bytes'] ) {
			return VWLB_Helpers::error( 'vwlb_upload_incomplete', __( 'The upload is incomplete.', VWLB_TEXT_DOMAIN ), 409, array( 'next_offset'=>(int)$session['next_offset'] ) );
		}
		$path = self::upload_path( $session['private_filename'] );
		if ( is_wp_error( $path ) || ! is_file( $path ) ) {
			return VWLB_Helpers::error( 'vwlb_upload_missing', __( 'The private upload file is missing.', VWLB_TEXT_DOMAIN ), 410 );
		}
		$sha = hash_file( 'sha256', $path );
		if ( $session['checksum_expected'] && ! hash_equals( $session['checksum_expected'], $sha ) ) {
			global $wpdb;
			$wpdb->update( VWLB_Helpers::table('upload_sessions'), array( 'status'=>'failed','updated_at'=>VWLB_Helpers::now() ), array( 'id'=>$session['id'] ) );
			return VWLB_Helpers::error( 'vwlb_upload_checksum_mismatch', __( 'The completed upload checksum did not match.', VWLB_TEXT_DOMAIN ), 422 );
		}
		$mime = '';
		if ( class_exists( 'finfo' ) ) {
			$f = new finfo( FILEINFO_MIME_TYPE );
			$mime = (string)$f->file( $path );
		}
		$asset = VWLB_Repository::find( 'media_assets', $session['asset_id'] );
		if ( ! $asset ) return VWLB_Helpers::error( 'vwlb_asset_missing', __( 'Asset not found.', VWLB_TEXT_DOMAIN ), 404 );
		$declared = (string)$asset['mime'];
		if ( $mime && $declared && ! self::mime_compatible( $declared, $mime ) ) {
			return VWLB_Helpers::error( 'vwlb_file_signature_mismatch', __( 'File signature does not match the declared media type.', VWLB_TEXT_DOMAIN ), 415 );
		}
		global $wpdb;
		return VWLB_DB::transaction( function() use ( $wpdb, $session, $asset, $sha, $mime ) {
			$storage = array(
				'driver'=>'private_file', 'relative_path'=>$session['private_filename'],
				'bytes'=>(int)$session['expected_bytes'], 'sha256'=>$sha,
				'detected_mime'=>$mime, 'direct_public_url'=>false,
			);
			$asset_updated = $wpdb->update(
				VWLB_Helpers::table('media_assets'),
				array(
					'status'=>'uploaded','scan_status'=>'pending','checksum'=>$sha,
					'storage_json'=>VWLB_Helpers::json_encode($storage),
					'updated_at'=>VWLB_Helpers::now(),'version'=>(int)$asset['version']+1,
				),
				array( 'id'=>(int)$asset['id'], 'version'=>(int)$asset['version'] )
			);
			if ( 1 !== $asset_updated ) return VWLB_Helpers::error( 'vwlb_asset_concurrency_conflict', __( 'Asset changed concurrently.', VWLB_TEXT_DOMAIN ), 409 );
			$session_updated=$wpdb->update(
				VWLB_Helpers::table('upload_sessions'),
				array( 'status'=>'complete','updated_at'=>VWLB_Helpers::now(),'version'=>(int)$session['version']+1 ),
				array( 'id'=>(int)$session['id'], 'version'=>(int)$session['version'] )
			);
			if(1!==$session_updated)return VWLB_Helpers::error('vwlb_upload_concurrency_conflict',__('Upload session changed concurrently.',VWLB_TEXT_DOMAIN),409);
			$job_id=VWLB_Media::enqueue(
				$asset['id'], 'verify_and_process',
				array( 'required_derivatives'=>array('hls','mp4_high','mp4_low','audio_only','poster','storyboard','transcript_draft') ),
				25
			);
			if(!$job_id)return VWLB_Helpers::error('vwlb_queue_write_failed',__('Processing job could not be queued.',VWLB_TEXT_DOMAIN),500);
			VWLB_Helpers::audit( 'asset', $asset['id'], 'resumable_upload_complete', 'initiated', 'uploaded', 'Private resumable upload completed; scan/transcode queued.' );
			return array( 'asset_public_id'=>$asset['public_id'], 'status'=>'uploaded', 'checksum'=>$sha, 'scan_status'=>'pending' );
		} );
	}

	private static function mime_compatible( $declared, $detected ) {
		$declared = strtolower( trim( $declared ) );
		$detected = strtolower( trim( $detected ) );
		if ( $declared === $detected ) return true;
		$families = array(
			'video/mp4'=>array('video/mp4','application/mp4'),
			'video/webm'=>array('video/webm','application/octet-stream'),
			'video/ogg'=>array('video/ogg','application/ogg'),
			'audio/mpeg'=>array('audio/mpeg','audio/mp3'),
			'audio/mp4'=>array('audio/mp4','video/mp4','application/mp4'),
			'audio/ogg'=>array('audio/ogg','application/ogg'),
		);
		return isset($families[$declared]) && in_array($detected,$families[$declared],true);
	}

	public static function add_chapter( $object_type, $object_id, $data ) {
		$object_type = VWLB_Helpers::enum( $object_type, array('video','podcast','live_replay'), '' );
		if ( ! $object_type ) return VWLB_Helpers::error( 'vwlb_chapter_type_invalid', __( 'Chapter object type is invalid.', VWLB_TEXT_DOMAIN ), 422 );
		$object_id = absint( $object_id );
		$object = 'video' === $object_type ? VWLB_Repository::find('videos',$object_id) : null;
		if ( 'video' === $object_type && ( ! $object || ! VWLB_Security::can( VWLB_Contracts::CAP_PUBLISH, $object, 'edit_chapters' ) ) ) {
			return VWLB_Helpers::error( 'vwlb_not_found', __( 'Media not found.', VWLB_TEXT_DOMAIN ), 404 );
		}
		if ( 'video' !== $object_type && ! VWLB_Security::can( VWLB_Contracts::CAP_PUBLISH, null, 'edit_chapters' ) ) {
			return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot edit chapters.', VWLB_TEXT_DOMAIN ), 403 );
		}
		$start = max(0,(int)($data['start_seconds']??0));
		$end = isset($data['end_seconds']) ? max(0,(int)$data['end_seconds']) : null;
		if ( null !== $end && $end <= $start ) return VWLB_Helpers::error( 'vwlb_chapter_time_invalid', __( 'Chapter end must be after chapter start.', VWLB_TEXT_DOMAIN ), 422 );
		$title = VWLB_Helpers::text( $data['title'] ?? '', 255 );
		if ( ! $title ) return VWLB_Helpers::error( 'vwlb_title_required', __( 'Title is required.', VWLB_TEXT_DOMAIN ), 422 );
		global $wpdb;
		$now=VWLB_Helpers::now();
		$ok=$wpdb->insert(VWLB_Helpers::table('chapters'),array(
			'public_id'=>VWLB_Helpers::public_id('chp'),'object_type'=>$object_type,'object_id'=>$object_id,
			'start_seconds'=>$start,'end_seconds'=>$end,'title'=>$title,
			'summary'=>VWLB_Helpers::textarea($data['summary']??'',5000),
			'source'=>VWLB_Helpers::enum($data['source']??'manual',array('manual','imported','machine_draft'),'manual'),
			'status'=>'published','version'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now
		));
		if(!$ok)return VWLB_Helpers::error('vwlb_database_error',__('Chapter could not be saved.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit('chapter',(int)$wpdb->insert_id,'create','','published');
		return array('public_id'=>$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('chapters').' WHERE id=%d',$wpdb->insert_id)),'start_seconds'=>$start,'end_seconds'=>$end,'title'=>$title);
	}

	public static function chapters( $object_type, $object_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT public_id,start_seconds,end_seconds,title,summary,source FROM '.VWLB_Helpers::table('chapters').' WHERE object_type=%s AND object_id=%d AND status=%s ORDER BY start_seconds ASC,id ASC LIMIT 500',
				sanitize_key($object_type), absint($object_id), 'published'
			), ARRAY_A
		);
	}

	public static function join_waiting_room( $live_id, $data=array() ) {
		$event=VWLB_Repository::find('live_events',$live_id);
		if(!$event||!VWLB_Security::can_view($event,'waiting_room'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in to join the waiting room.',VWLB_TEXT_DOMAIN),401);
		if(!in_array($event['status'],array('scheduled','rehearsal','ready','live'),true))return VWLB_Helpers::error('vwlb_waiting_room_closed',__('The waiting room is closed.',VWLB_TEXT_DOMAIN),409);
		return VWLB_DB::transaction(function()use($event,$data){
			global $wpdb;
			$events=VWLB_Helpers::table('live_events');
			$fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE id=%d FOR UPDATE",$event['id']),ARRAY_A);
			if(!$fresh||!VWLB_Security::can_view($fresh,'waiting_room'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
			if(!in_array($fresh['status'],array('scheduled','rehearsal','ready','live'),true))return VWLB_Helpers::error('vwlb_waiting_room_closed',__('The waiting room is closed.',VWLB_TEXT_DOMAIN),409);
			$table=VWLB_Helpers::table('live_attendees');$uid=get_current_user_id();
			$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE",$fresh['id'],$uid),ARRAY_A);
			$capacity=self::event_capacity($fresh);
			if(!$existing&&$capacity>0){
				$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE live_event_id=%d AND state IN ('waiting','approved','joined')",$fresh['id']));
				if($count>=$capacity)return VWLB_Helpers::error('vwlb_live_capacity_reached',__('This live event has reached capacity.',VWLB_TEXT_DOMAIN),409);
			}
			$state='live'===$fresh['status']?'joined':'waiting';$reminder=max(0,min(1440,(int)($data['reminder_minutes']??15)));$now=VWLB_Helpers::now();
			if($existing){
				$changed=$wpdb->update($table,array('state'=>$state,'reminder_minutes'=>$reminder,'joined_at'=>'joined'===$state?$now:($existing['joined_at']??null),'version'=>(int)$existing['version']+1,'updated_at'=>$now),array('id'=>$existing['id'],'version'=>$existing['version']));
				if(1!==$changed)return VWLB_Helpers::error('vwlb_waiting_room_conflict',__('Waiting-room state changed concurrently. Retry safely.',VWLB_TEXT_DOMAIN),409);
				$id=(int)$existing['id'];
			}else{
				$saved=$wpdb->insert($table,array('public_id'=>VWLB_Helpers::public_id('att'),'live_event_id'=>$fresh['id'],'user_id'=>$uid,'state'=>$state,'reminder_minutes'=>$reminder,'recording_consent'=>0,'consent_version'=>'','joined_at'=>'joined'===$state?$now:null,'version'=>1,'created_at'=>$now,'updated_at'=>$now));
				if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Waiting-room attendance could not be saved.',VWLB_TEXT_DOMAIN),500);
				$id=(int)$wpdb->insert_id;
			}
			VWLB_Helpers::audit('live_attendee',$id,'waiting_room_join','',$state,'',array('live_event_id'=>$fresh['id']));
			$attendee_public=$existing?($existing['public_id']??''):(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.$table.' WHERE id=%d',$id));return array('attendee_public_id'=>$attendee_public,'state'=>$state,'reminder_minutes'=>$reminder,'recording_consent'=>$existing?!empty($existing['recording_consent']):false);
		});
	}

	public static function set_recording_consent( $live_id, $consent, $version='' ) {
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in first.',VWLB_TEXT_DOMAIN),401);$event=VWLB_Repository::find('live_events',$live_id);if(!$event||!VWLB_Security::can_view($event,'recording_consent'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);$policy=VWLB_Helpers::json($event['recording_policy_json']??'{}');$required=VWLB_Helpers::text($policy['consent_version']??'v1',64);$submitted=VWLB_Helpers::text($version?:$required,64);$consent=(bool)$consent;if($consent&&!hash_equals($required,$submitted))return VWLB_Helpers::error('vwlb_recording_consent_version_stale',__('The recording-consent text changed. Review the current policy before consenting.',VWLB_TEXT_DOMAIN),409,array('required_consent_version'=>$required));
		return VWLB_DB::transaction(function()use($event,$consent,$required){global $wpdb;$table=VWLB_Helpers::table('live_attendees');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE",$event['id'],get_current_user_id()),ARRAY_A);if(!$row)return VWLB_Helpers::error('vwlb_attendee_missing',__('Join the waiting room before setting recording consent.',VWLB_TEXT_DOMAIN),409);$now=VWLB_Helpers::now();$changed=$wpdb->update($table,array('recording_consent'=>$consent?1:0,'consent_version'=>$required,'consented_at'=>$now,'version'=>(int)$row['version']+1,'updated_at'=>$now),array('id'=>$row['id'],'version'=>$row['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_recording_consent_conflict',__('Recording consent changed concurrently. Refresh and try again.',VWLB_TEXT_DOMAIN),409);VWLB_Helpers::audit('live_attendee',$row['id'],'recording_consent_change',$row['recording_consent']?'yes':'no',$consent?'yes':'no','',array('live_event_id'=>$event['id'],'purpose'=>'recording_consent','consent_version'=>$required));VWLB_Helpers::outbox('LiveRecordingConsentChanged','live',$event['id'],array('attendee_public_id'=>$row['public_id'],'consented'=>$consent,'consent_version'=>$required));return array('recording_consent'=>$consent,'consent_version'=>$required,'consented_at'=>VWLB_Helpers::iso_utc($now));});
	}

	public static function ask_question( $live_id, $question ) {
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in first.',VWLB_TEXT_DOMAIN),401);
		$rate=VWLB_Security::rate_limit('live_question',10,MINUTE_IN_SECONDS);if(is_wp_error($rate))return $rate;
		$event=VWLB_Repository::find('live_events',$live_id);if(!$event||!VWLB_Security::can_view($event,'live_question'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
		$q=VWLB_Helpers::textarea($question,4000);if(!$q)return VWLB_Helpers::error('vwlb_question_required',__('Question is required.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$saved=$wpdb->insert(VWLB_Helpers::table('live_questions'),array('public_id'=>VWLB_Helpers::public_id('q'),'live_event_id'=>$event['id'],'user_id'=>get_current_user_id(),'question'=>$q,'status'=>'queued','answer'=>'','moderator_id'=>0,'version'=>1,'created_at'=>$now,'updated_at'=>$now));
		if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Question could not be saved.',VWLB_TEXT_DOMAIN),500);
		$id=(int)$wpdb->insert_id;$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_questions').' WHERE id=%d',$id));VWLB_Helpers::audit('live_question',$id,'submit','','queued','',array('live_event_id'=>$event['id']));
		return array('public_id'=>$public,'status'=>'queued');
	}

	public static function moderate_question( $question_id, $status, $answer='' ) {
		$status=VWLB_Helpers::enum($status,array('approved','answered','dismissed'),'');
		if(!$status)return VWLB_Helpers::error('vwlb_question_state_invalid',__('Question state is invalid.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$table=VWLB_Helpers::table('live_questions');
		return VWLB_DB::transaction(function()use($wpdb,$table,$question_id,$status,$answer){
			$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE public_id=%s FOR UPDATE",VWLB_Helpers::text($question_id,64)),ARRAY_A);if(!$row)return VWLB_Helpers::error('vwlb_not_found',__('Question not found.',VWLB_TEXT_DOMAIN),404);
			$event=VWLB_Repository::find('live_events',$row['live_event_id']);
			if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_MODERATE,$event,'moderate_live_question'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot moderate this live event question.',VWLB_TEXT_DOMAIN),403);
			$changed=$wpdb->update($table,array('status'=>$status,'answer'=>VWLB_Helpers::textarea($answer,10000),'moderator_id'=>get_current_user_id(),'version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$row['id'],'version'=>$row['version']));
			if(1!==$changed)return VWLB_Helpers::error('vwlb_question_conflict',__('Question changed concurrently. Refresh and try again.',VWLB_TEXT_DOMAIN),409);
			VWLB_Helpers::audit('live_question',$row['id'],'moderate',$row['status'],$status,'',array('live_event_id'=>$event['id']));
			return array('public_id'=>$row['public_id'],'status'=>$status);
		});
	}

	public static function add_live_resource( $live_id, $data ) {
		$event=VWLB_Repository::find('live_events',$live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'add_live_resource'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);$title=VWLB_Helpers::text($data['title']??'',255);if(!$title)return VWLB_Helpers::error('vwlb_title_required',__('Title is required.',VWLB_TEXT_DOMAIN),422);$url=VWLB_Helpers::remote_url($data['url']??'');$attachment=absint($data['attachment_id']??0);if(!$url&&!$attachment)return VWLB_Helpers::error('vwlb_resource_required',__('A resource link or attachment is required.',VWLB_TEXT_DOMAIN),422);if($attachment&&(!current_user_can('read_post',$attachment)||!apply_filters('vwlb_live_resource_attachment_allowed',false,$attachment,$event,$data)))return VWLB_Helpers::error('vwlb_resource_attachment_forbidden',__('Live resource attachments must be explicitly authorized and safety-validated by the File 10 private-media boundary.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$now=VWLB_Helpers::now();$rights_status=VWLB_Helpers::enum($data['rights_status']??'declared',array('declared','verified','restricted'),'declared');$resource_status='restricted'===$rights_status?'restricted':'published';$saved=$wpdb->insert(VWLB_Helpers::table('live_resources'),array('public_id'=>VWLB_Helpers::public_id('res'),'live_event_id'=>$event['id'],'title'=>$title,'resource_type'=>$attachment?'attachment':'link','url'=>$url,'attachment_id'=>$attachment,'rights_status'=>$rights_status,'status'=>$resource_status,'version'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Live resource could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('live_resource',$id,'create','',$resource_status,'',array('live_event_id'=>$event['id']));$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_resources').' WHERE id=%d',$id));return array('public_id'=>$public,'title'=>$title,'url'=>$url,'resource_type'=>$attachment?'attachment':'link');
	}

	public static function live_extras( $event ) {
		if(!$event)return array();
		global $wpdb;
		$eid=(int)$event['id'];
		$resources=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,resource_type,url,rights_status FROM '.VWLB_Helpers::table('live_resources').' WHERE live_event_id=%d AND status=%s ORDER BY id ASC LIMIT 100',$eid,'published'),ARRAY_A);
		$questions=array();
		if(VWLB_Security::can(VWLB_Contracts::CAP_MODERATE,$event,'view_live_questions')){
			$questions=$wpdb->get_results($wpdb->prepare("SELECT public_id,question,status,answer,created_at FROM ".VWLB_Helpers::table('live_questions')." WHERE live_event_id=%d ORDER BY id ASC LIMIT 200",$eid),ARRAY_A);
		}
		$attendee=null;
		if(is_user_logged_in())$attendee=$wpdb->get_row($wpdb->prepare('SELECT public_id,state,reminder_minutes,recording_consent,consent_version,consented_at FROM '.VWLB_Helpers::table('live_attendees').' WHERE live_event_id=%d AND user_id=%d',$eid,get_current_user_id()),ARRAY_A);
		return array('capacity'=>self::event_capacity($event),'resources'=>$resources,'questions'=>$questions,'viewer'=>$attendee);
	}

	private static function event_capacity( $event ) {
		$policy=VWLB_Helpers::json($event['access_policy_json']??'{}');
		return max(0,min(100000,(int)($policy['capacity']??0)));
	}


	public static function create_premiere( $data, $idempotency_key ) {
		$video=VWLB_Repository::find('videos',$data['video_id']??0);if(!$video||'published'!==$video['status']||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'create_premiere'))return VWLB_Helpers::error('vwlb_premiere_video_invalid',__('A published, authorized video is required for a premiere.',VWLB_TEXT_DOMAIN),422);
		$live_data=array('title'=>VWLB_Helpers::text($data['title']??$video['title'],255),'description'=>VWLB_Helpers::textarea($data['description']??$video['description']),'scheduled_start'=>$data['scheduled_start']??null,'scheduled_end'=>$data['scheduled_end']??null,'timezone'=>$data['timezone']??'UTC','visibility'=>$data['visibility']??$video['visibility'],'provider'=>$data['provider']??'custom','language'=>$data['language']??$video['language'],'chat_policy'=>array('enabled'=>true,'moderated'=>true,'slow_mode_seconds'=>max(0,min(300,absint($data['slow_mode_seconds']??0)))),'recording_policy'=>array('record'=>false,'publish_replay'=>false,'consent_required'=>false),'access_policy'=>array('audience'=>$data['visibility']??$video['visibility'],'premiere_video_public_id'=>$video['public_id']));
		$live=VWLB_Live::schedule($live_data,$idempotency_key);if(is_wp_error($live))return $live;$extras=self::schedule_live_extras($live['id'],array('capacity'=>$data['capacity']??0,'waiting_room'=>true,'reminders'=>$data['reminders']??array(1440,60,15)));if(is_wp_error($extras))return $extras;
		global $wpdb;$table=VWLB_Helpers::table('premieres');$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d LIMIT 1",$live['id']),ARRAY_A);if($existing){if((int)$existing['video_id']!==(int)$video['id']||(int)$existing['owner_id']!==get_current_user_id())return VWLB_Helpers::error('vwlb_premiere_replay_conflict',__('This live event is already mapped to another premiere.',VWLB_TEXT_DOMAIN),409);return array('id'=>$existing['public_id'],'video_public_id'=>$video['public_id'],'live_public_id'=>$live['public_id'],'scheduled_at'=>VWLB_Helpers::iso_utc($existing['scheduled_at']),'status'=>$existing['status']);}
		$now=VWLB_Helpers::now();$public=VWLB_Helpers::public_id('pre');$saved=$wpdb->insert($table,array('public_id'=>$public,'video_id'=>$video['id'],'live_event_id'=>$live['id'],'owner_id'=>get_current_user_id(),'status'=>'scheduled','scheduled_at'=>VWLB_Helpers::datetime_in_timezone($data['scheduled_start']??null,$data['timezone']??'UTC'),'version'=>1,'created_at'=>$now,'updated_at'=>$now));if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Premiere mapping could not be created.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit('premiere',(int)$wpdb->insert_id,'create','','scheduled','Recorded premiere linked to moderated live discussion.');VWLB_Helpers::outbox('VideoPremiereScheduled','video',$video['id'],array('premiere_public_id'=>$public,'live_public_id'=>$live['public_id']));return array('id'=>$public,'video_public_id'=>$video['public_id'],'live_public_id'=>$live['public_id'],'scheduled_at'=>$data['scheduled_start']??null,'status'=>'scheduled');
	}

	public static function premiere( $id ) {
		global $wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('premieres').' WHERE public_id=%s LIMIT 1',VWLB_Helpers::text($id,64)),ARRAY_A);if(!$row)return null;
		$video=VWLB_Repository::find('videos',$row['video_id']);$live=VWLB_Live::state($row['live_event_id']);if(!$video||is_wp_error($live)||!VWLB_Security::can_view($video,'premiere'))return null;
		return array('id'=>$row['public_id'],'status'=>$row['status'],'scheduled_at'=>VWLB_Helpers::iso_utc($row['scheduled_at']),'video'=>VWLB_Repository::public_video_dto(VWLB_Repository::video_bundle($video['id'])),'live'=>$live,'discussion_owner'=>'File 17 contextual bridge / File 10 moderation policy');
	}

	public static function creator_studio() {
		if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'creator_studio'))return VWLB_Helpers::error('vwlb_forbidden',__('Creator Studio is restricted.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$uid=get_current_user_id();$videos=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,status,visibility,view_count,like_count,dislike_count,updated_at FROM '.VWLB_Helpers::table('videos').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);
		$live=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,status,scheduled_start,visibility,updated_at FROM '.VWLB_Helpers::table('live_events').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);
		$jobs=$wpdb->get_results($wpdb->prepare('SELECT j.public_id,j.job_type,j.status,j.attempts,j.error_code,j.updated_at FROM '.VWLB_Helpers::table('processing_jobs').' j INNER JOIN '.VWLB_Helpers::table('media_assets').' a ON a.id=j.asset_id WHERE a.owner_id=%d ORDER BY j.id DESC LIMIT 100',$uid),ARRAY_A);
		$rights=$wpdb->get_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);
		return array('videos'=>$videos,'live'=>$live,'jobs'=>$jobs,'copyright'=>$rights,'insights'=>self::creator_insights(30),'comments'=>apply_filters('vwlb_creator_comment_projection',array(),$uid),'canonical_owner'=>'File 10','comments_owner'=>'File 21/shared interaction contract');
	}

	public static function create_download_token( $object_type, $object_id, $ttl=900, $max_downloads=1 ) {
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in first.',VWLB_TEXT_DOMAIN),401);
		$object_type=VWLB_Helpers::enum($object_type,array('video','podcast'),'');if(!$object_type)return VWLB_Helpers::error('vwlb_download_type_invalid',__('Download object type is invalid.',VWLB_TEXT_DOMAIN),422);
		$object='video'===$object_type?VWLB_Repository::find('videos',$object_id):VWLB_Podcasts::episode($object_id,true);
		if(!$object)return VWLB_Helpers::error('vwlb_not_found',__('Media not found.',VWLB_TEXT_DOMAIN),404);
		if('podcast'===$object_type&&'published'!==($object['status']??'')){$owns=(int)($object['owner_id']??0)===get_current_user_id();if(!$owns||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$object,'download_unpublished_podcast'))return VWLB_Helpers::error('vwlb_not_found',__('Media not found.',VWLB_TEXT_DOMAIN),404);}elseif(!VWLB_Security::can_view($object,'download'))return VWLB_Helpers::error('vwlb_not_found',__('Media not found.',VWLB_TEXT_DOMAIN),404);
		if(!self::download_allowed($object_type,$object))return VWLB_Helpers::error('vwlb_download_not_allowed',__('Download is not allowed by the current rights policy.',VWLB_TEXT_DOMAIN),403);
		$token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$ttl=max(60,min(HOUR_IN_SECONDS,(int)$ttl));$max_downloads=max(1,min(20,(int)$max_downloads));
		global $wpdb;$now=VWLB_Helpers::now();$public=VWLB_Helpers::public_id('dl');$saved=$wpdb->insert(VWLB_Helpers::table('download_tokens'),array('public_id'=>$public,'token_hash'=>password_hash($token,PASSWORD_DEFAULT),'user_id'=>get_current_user_id(),'object_type'=>$object_type,'object_id'=>(int)$object['id'],'rights_snapshot'=>hash('sha256',VWLB_Helpers::json_encode(array($object['rights_status']??'',$object['visibility']??''))),'max_downloads'=>$max_downloads,'download_count'=>0,'status'=>'active','expires_at'=>gmdate('Y-m-d H:i:s',time()+$ttl),'created_at'=>$now,'updated_at'=>$now));
		if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Download token could not be stored.',VWLB_TEXT_DOMAIN),500);
		VWLB_Helpers::audit('download_token',(int)$wpdb->insert_id,'create','','active','',array('object_type'=>$object_type,'object_id'=>$object['id']));
		return array('token_id'=>$public,'download_token'=>$token,'display_once'=>true,'expires_at'=>gmdate('c',time()+$ttl),'max_downloads'=>$max_downloads);
	}

	private static function download_allowed( $object_type, $object ) {
		if('video'===$object_type){
			$rights=VWLB_Helpers::json($object['rights_json']??'{}');
			return !empty($rights['download_allowed']) && in_array($object['rights_status']??'',array('declared','verified'),true);
		}
		return !empty($object['download_allowed']) && in_array($object['rights_status']??'',array('declared','verified'),true);
	}

	public static function resolve_download( $public_id, $token ) {
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in first.',VWLB_TEXT_DOMAIN),401);
		global $wpdb;$table=VWLB_Helpers::table('download_tokens');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE public_id=%s",VWLB_Helpers::text($public_id,64)),ARRAY_A);
		if(!$row||(int)$row['user_id']!==get_current_user_id()||'active'!==$row['status']||strtotime($row['expires_at'].' UTC')<=time()||!password_verify((string)$token,$row['token_hash']))return VWLB_Helpers::error('vwlb_download_token_invalid',__('Download token is invalid or expired.',VWLB_TEXT_DOMAIN),410);
		if((int)$row['download_count']>=(int)$row['max_downloads'])return VWLB_Helpers::error('vwlb_download_limit_reached',__('Download limit has been reached.',VWLB_TEXT_DOMAIN),410);
		$object='video'===$row['object_type']?VWLB_Repository::video_bundle($row['object_id']):VWLB_Podcasts::episode($row['object_id'],true);
		if(!$object||('podcast'===$row['object_type']&&'published'!==($object['status']??''))||!VWLB_Security::can_view($object,'download')||!self::download_allowed($row['object_type'],$object))return VWLB_Helpers::error('vwlb_download_revoked',__('Download access has been revoked.',VWLB_TEXT_DOMAIN),403);
		$asset='video'===$row['object_type']?($object['asset']??array()):VWLB_Repository::find('media_assets',$object['asset_id']??0);$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$url=$derivatives['download']??$derivatives['mp4_high']??$derivatives['mp4']??$derivatives['audio_only']??'';
		if(!$url)$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);$url=esc_url_raw($url);if(!$url)return VWLB_Helpers::error('vwlb_download_unavailable',__('Download derivative is not ready.',VWLB_TEXT_DOMAIN),503);
		$consumed=$wpdb->query($wpdb->prepare("UPDATE $table SET download_count=download_count+1,updated_at=%s WHERE id=%d AND status='active' AND expires_at>%s AND download_count<max_downloads",VWLB_Helpers::now(),$row['id'],VWLB_Helpers::now()));
		if(1!==$consumed)return VWLB_Helpers::error('vwlb_download_limit_reached',__('Download token was already consumed or changed concurrently.',VWLB_TEXT_DOMAIN),410);
		return array('url'=>$url,'expires_in'=>120,'checksum'=>$asset['checksum']??'','rights_status'=>$object['rights_status']??'');
	}

	public static function increment_metric( $owner_id, $object_type, $object_id, $metric, $amount=1 ) {
		$allowed=array('views','completions','saves','source_opens','meaningful_comments','harm_reports');
		if(!in_array($metric,$allowed,true))return false;
		global $wpdb;$table=VWLB_Helpers::table('creator_metrics_daily');$date=gmdate('Y-m-d');$amount=max(0,min(1000,(int)$amount));
		$written=$wpdb->query($wpdb->prepare(
			"INSERT INTO $table (metric_date,owner_id,object_type,object_id,$metric,updated_at) VALUES (%s,%d,%s,%d,%d,%s)
			ON DUPLICATE KEY UPDATE $metric=$metric+VALUES($metric),updated_at=VALUES(updated_at)",
			$date,absint($owner_id),sanitize_key($object_type),absint($object_id),$amount,VWLB_Helpers::now()
		));
		if(false===$written){do_action('vwlb_metric_write_failed',array('owner_id'=>absint($owner_id),'object_type'=>sanitize_key($object_type),'object_id'=>absint($object_id),'metric'=>$metric));return false;}return true;
	}

	public static function creator_insights( $days=30 ) {
		if(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'creator_insights'))return VWLB_Helpers::error('vwlb_forbidden',__('Creator insights are restricted.',VWLB_TEXT_DOMAIN),403);
		$days=max(1,min(365,(int)$days));global $wpdb;$table=VWLB_Helpers::table('creator_metrics_daily');
		$since=gmdate('Y-m-d',time()-($days-1)*DAY_IN_SECONDS);
		$rows=$wpdb->get_results($wpdb->prepare("SELECT metric_date,SUM(views) views,SUM(completions) completions,SUM(saves) saves,SUM(source_opens) source_opens,SUM(meaningful_comments) meaningful_comments,SUM(harm_reports) harm_reports FROM $table WHERE owner_id=%d AND metric_date>=%s GROUP BY metric_date ORDER BY metric_date ASC",get_current_user_id(),$since),ARRAY_A);
		$totals=array('views'=>0,'completions'=>0,'saves'=>0,'source_opens'=>0,'meaningful_comments'=>0,'harm_reports'=>0);
		foreach($rows as $r)foreach($totals as $k=>$v)$totals[$k]+=(int)$r[$k];
		$totals['completion_rate']=$totals['views']?round($totals['completions']/$totals['views'],4):0;
		return array('days'=>$days,'totals'=>$totals,'series'=>$rows,'privacy'=>'aggregate-only','ranking_guardrail'=>'watch-time-alone-is-not-a-quality-score');
	}

	public static function media_contract( $value, $media_id, $consumer='generic' ) {
		$video=VWLB_Repository::video_bundle($media_id);
		if(!$video||!VWLB_Security::can_view($video,'media_contract'))return VWLB_Helpers::error('vwlb_not_found',__('Media is unavailable.',VWLB_TEXT_DOMAIN),404);
		$asset=$video['asset']??array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');
		return array(
			'contract'=>'File10Media.v1','owner'=>'File 10','consumer'=>sanitize_key($consumer),
			'media_public_id'=>$video['public_id'],'status'=>$video['status'],'visibility'=>$video['visibility'],
			'duration_seconds'=>(int)$video['duration_seconds'],'language'=>$video['language'],
			'rights_status'=>$video['rights_status'],'consent_status'=>$video['consent_status'],
			'derivatives'=>array_intersect_key($derivatives,array_flip(array('hls','mp4_high','mp4_low','audio_only','poster','storyboard'))),
			'captions'=>array_map(function($c){return array('public_id'=>$c['public_id'],'language'=>$c['language'],'kind'=>$c['kind'],'format'=>$c['format']);},$video['captions']??array()),
			'chapters'=>self::chapters('video',$video['id']),
			'authoritative'=>true,
		);
	}

	public static function reels_media_contract( $value, $media_id, $context=array() ) {
		$video=VWLB_Repository::video_bundle($media_id);if(!$video||!VWLB_Security::can_view($video,'file11_media_contract'))return VWLB_Helpers::error('vwlb_not_found',__('Media is unavailable.',VWLB_TEXT_DOMAIN),404);$asset=$video['asset']??array();if(!$asset||'ready'!==($asset['status']??'')||'passed'!==($asset['scan_status']??''))return VWLB_Helpers::error('vwlb_reel_media_not_ready',__('Reel media must be fully processed and malware-scanned by File 10.',VWLB_TEXT_DOMAIN),409);$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');if(!array_intersect_key($derivatives,array_flip(array('hls','mp4_high','mp4_low','mp4'))))return VWLB_Helpers::error('vwlb_reel_derivative_missing',__('A server-verified playable derivative is required before File 11 may reference this media.',VWLB_TEXT_DOMAIN),409);
		$contract=self::media_contract($value,$media_id,'file11');
		if(is_wp_error($contract))return $contract;
		$duration=(int)$contract['duration_seconds'];
		if($duration<60||$duration>600)return VWLB_Helpers::error('vwlb_reel_duration_invalid',__('Reels require a server-verified duration from 60 to 600 seconds.',VWLB_TEXT_DOMAIN),422);
		if(!in_array($contract['rights_status'],array('declared','verified'),true)||!in_array($contract['consent_status'],array('not_patient_case','documented','anonymized','approved'),true))return VWLB_Helpers::error('vwlb_reel_safety_gate',__('Media rights or patient-consent review is incomplete.',VWLB_TEXT_DOMAIN),422);
		$contract['reel_eligible']=true;$contract['source_card_required']=true;$contract['autoplay_default']='off';
		return $contract;
	}


	public static function technical_validation( $default, $asset ) {
		if(!is_array($asset))return false;
		$storage=VWLB_Helpers::json($asset['storage_json']??'{}');
		if('private_file'===($storage['driver']??'')){
			$path=self::upload_path($storage['relative_path']??'');if(is_wp_error($path)||!is_file($path))return false;
			$size=filesize($path);if(isset($storage['bytes'])&&(int)$storage['bytes']!==$size)return false;
			$sha=hash_file('sha256',$path);if(!empty($asset['checksum'])&&!hash_equals(strtolower((string)$asset['checksum']),$sha))return false;
			if(class_exists('finfo')&&!empty($asset['mime'])){$f=new finfo(FILEINFO_MIME_TYPE);$det=(string)$f->file($path);if($det&&!self::mime_compatible($asset['mime'],$det))return false;}
			$scan=apply_filters('vwlb_malware_scan_result',null,$path,$asset);
			if(true===$scan)return true;
			if(is_array($scan)&&in_array(($scan['status']??''),array('clean','passed'),true))return true;
			VWLB_Helpers::audit('asset',$asset['id']??0,'scan_gate',$asset['status']??'','quarantined','Malware scanner did not return a clean result.',array('purpose'=>'upload_security'));
			return false;
		}
		$external=apply_filters('vwlb_external_media_validation',null,$asset);
		if(true===$external||is_array($external)&&in_array(($external['status']??''),array('clean','passed','provider_verified'),true))return true;
		return false;
	}

	public static function media_safety_gate( $allowed, $video ) {
		if(!$allowed||!is_array($video))return false;
		$rights=VWLB_Helpers::json($video['rights_json']??'{}');
		$risk=VWLB_Helpers::enum($rights['risk_tier']??'low',array('low','medium','high','critical'),'low');
		if(in_array($risk,array('high','critical'),true)&& (empty($rights['safety_reviewed'])||empty($rights['reviewer_qualified'])))return false;
		if(!empty($rights['medical_content'])&&empty($rights['source_disclosure']))return false;
		if(!empty($rights['patient_case'])&&empty($rights['consent_reference']))return false;
		return true;
	}

	public static function local_processor( $result, $asset ) {
		if ( ! is_array( $asset ) ) return $result;
		$storage=VWLB_Helpers::json($asset['storage_json']??'{}');
		if ( 'private_file' !== ($storage['driver']??'') ) return $result;
		// Real transcode/scan is an explicit provider boundary. No fake derivative is emitted.
		$external=apply_filters('vwlb_private_media_pipeline_result',null,$asset,$storage);
		if(is_array($external))return $external;
		return VWLB_Helpers::error('vwlb_transcoder_unconfigured',__('Private media is stored safely, but a scanning/transcoding provider must finish processing before publication.',VWLB_TEXT_DOMAIN),503);
	}

	public static function process_job( $default, $job, $asset ) {
		if('send_live_reminder'===$job['job_type']){
			$input=VWLB_Helpers::json($job['input_json']);
			VWLB_Helpers::outbox('LiveReminderDue','live',absint($input['live_event_id']??0),array('audience'=>'registered_attendees','minutes'=>(int)($input['minutes']??15)));
			return array('status'=>'complete');
		}
		return $default;
	}

	public static function recording_consent_gate( $result, $input, $job ) {
		$live_id=absint($input['live_event_id']??0);if(!$live_id)return $result;$event=VWLB_Repository::find('live_events',$live_id);if(!$event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found for recording finalization.',VWLB_TEXT_DOMAIN),404);$policy=VWLB_Helpers::json($event['recording_policy_json']??'{}');if(empty($policy['record']))return VWLB_Helpers::error('vwlb_recording_not_authorized',__('Recording was not authorized for this live event.',VWLB_TEXT_DOMAIN),409);
		if(!empty($policy['consent_required'])){global $wpdb;$required=VWLB_Helpers::text($policy['consent_version']??'v1',64);$missing=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".VWLB_Helpers::table('live_attendees')." WHERE live_event_id=%d AND state IN ('approved','joined') AND (recording_consent=0 OR consent_version<>%s)",$event['id'],$required));if($missing>0){VWLB_Helpers::audit('live',$event['id'],'recording_blocked_consent',$event['status'],$event['status'],'Recording finalization blocked because attendee consent is incomplete or stale.',array('missing_consent_count'=>$missing,'consent_version'=>$required,'purpose'=>'recording_consent'));return VWLB_Helpers::error('vwlb_recording_consent_incomplete',__('Recording cannot be finalized while required attendee consent is incomplete or stale.',VWLB_TEXT_DOMAIN),409);}}
		return $result;
	}

	public static function schedule_live_extras( $live_id, $data ) {
		$event=VWLB_Repository::find('live_events',$live_id);if(!$event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);$capacity=max(0,min(100000,(int)($data['capacity']??0)));$reminders=array_values(array_unique(array_filter(array_map('absint',(array)($data['reminders']??array(1440,60,15))))));
		return VWLB_DB::transaction(function()use($event,$data,$capacity,$reminders){global $wpdb;$events=VWLB_Helpers::table('live_events');$fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE id=%d FOR UPDATE",$event['id']),ARRAY_A);if(!$fresh)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);$access=VWLB_Helpers::json($fresh['access_policy_json']);$access['capacity']=$capacity;$access['waiting_room']=array_key_exists('waiting_room',$data)?(bool)$data['waiting_room']:true;$changed=$wpdb->update($events,array('access_policy_json'=>VWLB_Helpers::json_encode($access),'version'=>(int)$fresh['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$fresh['id'],'version'=>$fresh['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_live_extras_conflict',__('Live waiting-room settings changed concurrently.',VWLB_TEXT_DOMAIN),409);
			$jobs_table=VWLB_Helpers::table('processing_jobs');$old=$wpdb->get_results("SELECT id,input_json FROM $jobs_table WHERE job_type='send_live_reminder' AND status IN ('pending','retry') AND asset_id=0 LIMIT 500",ARRAY_A);foreach($old as $job){$input=VWLB_Helpers::json($job['input_json']);if((int)($input['live_event_id']??0)===(int)$fresh['id']){if(false===$wpdb->delete($jobs_table,array('id'=>$job['id']),array('%d')))return VWLB_Helpers::error('vwlb_database_error',__('Existing live reminders could not be reconciled.',VWLB_TEXT_DOMAIN),500);}}
			foreach($reminders as $minutes){if($minutes>10080)continue;$available=strtotime($fresh['scheduled_start'].' UTC')-$minutes*MINUTE_IN_SECONDS;if($available<=time())continue;$saved=$wpdb->insert($jobs_table,array('public_id'=>VWLB_Helpers::public_id('job'),'asset_id'=>0,'job_type'=>'send_live_reminder','provider'=>'local','status'=>'pending','priority'=>50,'attempts'=>0,'max_attempts'=>5,'available_at'=>gmdate('Y-m-d H:i:s',$available),'input_json'=>VWLB_Helpers::json_encode(array('live_event_id'=>$fresh['id'],'minutes'=>$minutes)),'output_json'=>'{}','created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));if(!$saved)return VWLB_Helpers::error('vwlb_database_error',__('Live reminder could not be scheduled.',VWLB_TEXT_DOMAIN),500);}
			VWLB_Helpers::outbox('LiveWaitingRoomOpened','live',$fresh['id'],array('capacity'=>$capacity,'waiting_room'=>$access['waiting_room']));return true;});
	}

	public static function cleanup() {
		global $wpdb;$now=VWLB_Helpers::now();
		$sessions=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".VWLB_Helpers::table('upload_sessions')." WHERE status IN ('active','failed') AND expires_at<%s LIMIT 100",$now),ARRAY_A);
		foreach($sessions as $s){
			$path=self::upload_path($s['private_filename']);if(is_wp_error($path)){VWLB_Helpers::audit('upload_session',$s['id'],'expire_failed',$s['status'],$s['status'],'Private upload cleanup path could not be resolved.');continue;}if(is_file($path)&&!@unlink($path)){VWLB_Helpers::audit('upload_session',$s['id'],'expire_failed',$s['status'],$s['status'],'Expired private upload file could not be deleted.');continue;}
			$changed=$wpdb->update(VWLB_Helpers::table('upload_sessions'),array('status'=>'expired','updated_at'=>$now),array('id'=>$s['id'],'status'=>$s['status']));if(false===$changed){VWLB_Helpers::audit('upload_session',$s['id'],'expire_failed',$s['status'],$s['status'],'Expired upload state could not be persisted.');continue;}if(0===$changed)continue;
			VWLB_Helpers::audit('upload_session',$s['id'],'expire',$s['status'],'expired');
		}
		$wpdb->query($wpdb->prepare("UPDATE ".VWLB_Helpers::table('download_tokens')." SET status='expired',updated_at=%s WHERE status='active' AND expires_at<%s",$now,$now));
	}


	public static function observe_rest( $response, $server, $request ) {
		if(!($response instanceof WP_REST_Response)||!($request instanceof WP_REST_Request))return $response;
		$route=$request->get_route();$is_file10=false;foreach(VWLB_Contracts::namespaces() as $n){if(str_starts_with($route,'/'.$n.'/')){$is_file10=true;break;}}if(!$is_file10||$response->get_status()>=400)return $response;
		$data=$response->get_data();$method=strtoupper($request->get_method());
		if($method==='GET'&&preg_match('#/videos/([A-Za-z0-9_-]+)/playback$#',$route,$m)){$v=VWLB_Repository::find('videos',$m[1]);if($v){$key='vwlb_metric_view_'.hash('sha256',$v['id'].'|'.get_current_user_id().'|'.VWLB_Helpers::ip_hash());if(!get_transient($key)){set_transient($key,1,6*HOUR_IN_SECONDS);self::increment_metric($v['owner_id'],'video',$v['id'],'views');}}}
		if($method==='POST'&&preg_match('#/videos/([A-Za-z0-9_-]+)/progress$#',$route,$m)&&is_array($data)&&!empty($data['completed'])){$v=VWLB_Repository::find('videos',$m[1]);if($v&&is_user_logged_in()){$k='vwlb_completed_metric_'.$v['id'];if(!get_user_meta(get_current_user_id(),$k,true)){update_user_meta(get_current_user_id(),$k,1);self::increment_metric($v['owner_id'],'video',$v['id'],'completions');}}}
		if($method==='POST'&&preg_match('#/videos/([A-Za-z0-9_-]+)/interactions$#',$route,$m)&&is_array($data)&&($data['interaction']??'')==='save'&&!empty($data['active'])){$v=VWLB_Repository::find('videos',$m[1]);if($v)self::increment_metric($v['owner_id'],'video',$v['id'],'saves');}
		if($method==='GET'&&preg_match('#/media/([A-Za-z0-9_-]+)/contract$#',$route,$m)){$v=VWLB_Repository::find('videos',$m[1]);if($v)self::increment_metric($v['owner_id'],'video',$v['id'],'source_opens');}
		if($method==='POST'&&str_ends_with($route,'/moderation/reports')){$body=$request->get_json_params();if(is_array($body)&&($body['target_type']??'')==='video'){$v=VWLB_Repository::find('videos',$body['target_id']??0);if($v)self::increment_metric($v['owner_id'],'video',$v['id'],'harm_reports');}}
		return $response;
	}

	public static function status() {
		global $wpdb;
		$dead=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('processing_jobs')." WHERE status='dead'");
		$active_uploads=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('upload_sessions')." WHERE status='active'");
		return array(
			'schema'=>get_option(self::OPTION,''),'canonical_api'=>VWLB_Contracts::CANONICAL_API_NAMESPACE,
			'dead_jobs'=>$dead,'active_resumable_uploads'=>$active_uploads,
			'private_storage'=>is_dir(trailingslashit(WP_CONTENT_DIR).self::PRIVATE_DIR),
			'central_trace_count'=>count(VWLB_Contracts::CENTRAL_TRACE),
		);
	}
}
