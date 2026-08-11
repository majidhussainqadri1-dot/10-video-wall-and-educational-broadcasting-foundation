<?php
/**
 * File 10 Future Video & Broadcasting Intelligence — 24 approved enhancements.
 *
 * Canonical ownership remains with File 10 for recorded/live media truth. Companion
 * modules are consumed through versioned filters/actions only; this class never writes
 * companion tables directly. AI/provider outputs are candidates until human review.
 */
defined( 'ABSPATH' ) || exit;

final class VWLB_Future_Intelligence {
	const OPTION = 'vwlb_future_schema_version';
	const SCHEMA = '1.2.0';
	const REQUIREMENTS = array(
		'F10-FUT-001','F10-FUT-002','F10-FUT-003','F10-FUT-004','F10-FUT-005','F10-FUT-006',
		'F10-FUT-007','F10-FUT-008','F10-FUT-009','F10-FUT-010','F10-FUT-011','F10-FUT-012',
		'F10-FUT-013','F10-FUT-014','F10-FUT-015','F10-FUT-016','F10-FUT-017','F10-FUT-018',
		'F10-FUT-019','F10-FUT-020','F10-FUT-021','F10-FUT-022','F10-FUT-023','F10-FUT-024',
	);

	public static function register() {
		add_filter( 'vwlb_future_capabilities', array( __CLASS__, 'capabilities' ) );
		add_filter( 'vwlb_processing_profile', array( __CLASS__, 'content_aware_profile' ), 20, 3 );
		add_filter( 'vwlb_secure_playback_watermark', array( __CLASS__, 'watermark_payload' ), 20, 4 );
		add_action( 'vwlb_reconcile_states', array( __CLASS__, 'reconcile_consent_expiry' ), 20 );
		add_action( 'vwlb_reconcile_states', array( __CLASS__, 'reconcile_live_redundancy' ), 30 );
		add_action( 'vwlb_cleanup', array( __CLASS__, 'cleanup' ), 30 );
		if ( get_option( self::OPTION ) !== self::SCHEMA ) {
			self::install_schema();
		}
	}

	public static function capabilities( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		return array_values( array_unique( array_merge( $caps, array(
			'multi_camera_production','guest_cohost','screen_slides_source','live_dvr','ultra_low_latency',
			'srt_webrtc_ingest','simulcast','redundant_recording','broadcaster_health','content_aware_encoding',
			'uhd_hdr_av1','live_translation','reviewed_dubbing','audio_description','sign_language_companion',
			'ai_chapter_suggestions','timestamped_citations','educational_overlays','live_polls','transcript_search',
			'timestamped_corrections','consent_expiry_restriction','forensic_watermarking','knowledge_ecosystem_bridge',
		) ) ) );
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = function( $name ) { return VWLB_Helpers::table( $name ); };
		$sql = array();

		$sql[] = "CREATE TABLE {$t('future_live_config')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			live_event_id bigint unsigned NOT NULL,
			latency_mode varchar(32) NOT NULL DEFAULT 'standard',
			dvr_window_seconds int unsigned NOT NULL DEFAULT 0,
			backup_provider varchar(64) NOT NULL DEFAULT '',
			multicam_enabled tinyint(1) NOT NULL DEFAULT 0,
			simulcast_enabled tinyint(1) NOT NULL DEFAULT 0,
			redundant_recording tinyint(1) NOT NULL DEFAULT 0,
			protocols_json longtext NULL,
			translation_languages_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			updated_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY live_event_id (live_event_id)
		) $c";

		$sql[] = "CREATE TABLE {$t('production_sources')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			owner_id bigint unsigned NOT NULL,
			source_type varchar(32) NOT NULL,
			label varchar(191) NOT NULL,
			provider_ref varchar(191) NOT NULL DEFAULT '',
			state varchar(32) NOT NULL DEFAULT 'ready',
			config_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY event_state (live_event_id,state), KEY owner_id (owner_id)
		) $c";

		$sql[] = "CREATE TABLE {$t('production_scenes')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			owner_id bigint unsigned NOT NULL,
			title varchar(191) NOT NULL,
			layout_json longtext NULL,
			source_ids_json longtext NULL,
			state varchar(32) NOT NULL DEFAULT 'saved',
			is_program tinyint(1) NOT NULL DEFAULT 0,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY event_program (live_event_id,is_program)
		) $c";

		$sql[] = "CREATE TABLE {$t('broadcast_guests')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			role_name varchar(32) NOT NULL DEFAULT 'guest',
			status varchar(32) NOT NULL DEFAULT 'invited',
			scope_json longtext NULL,
			expires_at datetime NOT NULL,
			invited_by bigint unsigned NOT NULL,
			accepted_at datetime NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			UNIQUE KEY event_user (live_event_id,user_id), KEY status_expiry (status,expires_at)
		) $c";

		$sql[] = "CREATE TABLE {$t('simulcast_targets')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			platform varchar(64) NOT NULL,
			provider_target_ref varchar(191) NOT NULL,
			credential_ref varchar(191) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'disabled',
			config_json longtext NULL,
			last_state_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			UNIQUE KEY event_platform_ref (live_event_id,platform,provider_target_ref),
			KEY event_status (live_event_id,status)
		) $c";

		$sql[] = "CREATE TABLE {$t('broadcast_health_samples')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			live_event_id bigint unsigned NOT NULL,
			source_public_id varchar(64) NOT NULL DEFAULT '',
			bitrate_kbps int unsigned NOT NULL DEFAULT 0,
			packet_loss_bp int unsigned NOT NULL DEFAULT 0,
			dropped_frames int unsigned NOT NULL DEFAULT 0,
			jitter_ms int unsigned NOT NULL DEFAULT 0,
			latency_ms int unsigned NOT NULL DEFAULT 0,
			audio_peak_db decimal(6,2) NULL,
			state varchar(32) NOT NULL DEFAULT 'unknown',
			captured_at datetime NOT NULL,
			PRIMARY KEY (id), KEY event_time (live_event_id,captured_at)
		) $c";

		$sql[] = "CREATE TABLE {$t('media_tracks')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint unsigned NOT NULL,
			track_type varchar(32) NOT NULL,
			language varchar(32) NOT NULL DEFAULT '',
			source varchar(32) NOT NULL DEFAULT 'manual',
			status varchar(32) NOT NULL DEFAULT 'candidate',
			file_ref text NULL,
			provider_ref varchar(191) NOT NULL DEFAULT '',
			metadata_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			reviewed_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY object_track (object_type,object_id,track_type,status), KEY language (language)
		) $c";

		$sql[] = "CREATE TABLE {$t('transcript_segments')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			video_id bigint unsigned NOT NULL,
			track_id bigint unsigned NOT NULL DEFAULT 0,
			language varchar(32) NOT NULL DEFAULT '',
			start_ms bigint unsigned NOT NULL DEFAULT 0,
			end_ms bigint unsigned NOT NULL DEFAULT 0,
			segment_text text NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'reviewed',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), KEY video_language (video_id,language,status), FULLTEXT KEY segment_search (segment_text)
		) $c";

		$sql[] = "CREATE TABLE {$t('video_annotations')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			video_id bigint unsigned NOT NULL,
			kind varchar(32) NOT NULL,
			start_ms bigint unsigned NOT NULL DEFAULT 0,
			end_ms bigint unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			body longtext NULL,
			source_owner varchar(64) NOT NULL DEFAULT '',
			source_ref varchar(191) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'candidate',
			metadata_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			reviewed_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id),
			KEY video_kind_time (video_id,kind,start_ms,status)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_polls')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			live_event_id bigint unsigned NOT NULL,
			question text NOT NULL,
			poll_type varchar(32) NOT NULL DEFAULT 'single',
			status varchar(32) NOT NULL DEFAULT 'draft',
			opens_at datetime NULL,
			closes_at datetime NULL,
			metadata_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id), KEY event_status (live_event_id,status)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_poll_options')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			poll_id bigint unsigned NOT NULL,
			public_id varchar(64) NOT NULL,
			option_text varchar(255) NOT NULL,
			is_correct tinyint(1) NOT NULL DEFAULT 0,
			sort_order int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id), KEY poll_sort (poll_id,sort_order)
		) $c";

		$sql[] = "CREATE TABLE {$t('live_poll_responses')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			poll_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			option_id bigint unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY poll_user_option (poll_id,user_id,option_id), KEY poll_id (poll_id)
		) $c";

		$sql[] = "CREATE TABLE {$t('consent_links')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			video_id bigint unsigned NOT NULL,
			consent_ref varchar(191) NOT NULL,
			subject_ref varchar(191) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'active',
			expires_at datetime NULL,
			withdrawn_at datetime NULL,
			metadata_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY video_consent (video_id,consent_ref), KEY consent_status_expiry (status,expires_at)
		) $c";

		$sql[] = "CREATE TABLE {$t('watermark_policies')} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(32) NOT NULL,
			object_id bigint unsigned NOT NULL,
			mode varchar(32) NOT NULL DEFAULT 'off',
			status varchar(32) NOT NULL DEFAULT 'active',
			policy_json longtext NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			updated_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY object_policy (object_type,object_id)
		) $c";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		update_option( self::OPTION, self::SCHEMA, false );
		VWLB_Helpers::audit( 'system', 10, 'future_schema_upgrade', '', self::SCHEMA, 'File 10 Future Video & Broadcasting Intelligence schema reconciled.' );
	}

	private static function live( $id ) {
		$event = VWLB_Repository::find( 'live_events', $id );
		return $event && is_array( $event ) ? $event : null;
	}

	private static function video( $id ) {
		$video = VWLB_Repository::find( 'videos', $id );
		return $video && is_array( $video ) ? $video : null;
	}

	private static function public_row( $table, $id ) {
		global $wpdb;
		$t = VWLB_Helpers::table( $table );
		if ( is_numeric( $id ) ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", absint( $id ) ), ARRAY_A );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE public_id=%s", VWLB_Helpers::text( $id, 64 ) ), ARRAY_A );
	}

	private static function require_live_control( $event, $purpose ) {
		return $event && VWLB_Security::can( VWLB_Contracts::CAP_BROADCAST, $event, $purpose );
	}

	/** F10-FUT-001, 003 — multi-camera production sources and screen/slides inputs. */
	public static function upsert_source( $live_id, $data ) {
		$event = self::live( $live_id );
		if ( ! self::require_live_control( $event, 'future_production_source' ) ) return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot manage production sources.', VWLB_TEXT_DOMAIN ), 403 );
		$type = VWLB_Helpers::enum( $data['source_type'] ?? 'camera', array( 'camera','microphone','screen','slides','browser','remote_guest','media','whiteboard' ), '' );
		if ( ! $type ) return VWLB_Helpers::error( 'vwlb_source_type_invalid', __( 'Production source type is invalid.', VWLB_TEXT_DOMAIN ), 422 );
		$label = VWLB_Helpers::text( $data['label'] ?? '', 191 );
		if ( ! $label ) return VWLB_Helpers::error( 'vwlb_source_label_required', __( 'Source label is required.', VWLB_TEXT_DOMAIN ), 422 );
		global $wpdb; $table = VWLB_Helpers::table( 'production_sources' ); $now = VWLB_Helpers::now();
		$id = absint( $data['id'] ?? 0 );
		$row = array( 'live_event_id'=>(int)$event['id'], 'owner_id'=>get_current_user_id(), 'source_type'=>$type, 'label'=>$label,
			'provider_ref'=>VWLB_Helpers::text( $data['provider_ref'] ?? '', 191 ), 'state'=>VWLB_Helpers::enum( $data['state'] ?? 'ready', array('ready','muted','offline','failed','removed'), 'ready' ),
			'config_json'=>VWLB_Helpers::json_encode( (array)( $data['config'] ?? array() ) ), 'updated_at'=>$now );
		if ( $id ) {
			$current = self::public_row( 'production_sources', $id );
			if ( ! $current || (int)$current['live_event_id'] !== (int)$event['id'] ) return VWLB_Helpers::error( 'vwlb_source_missing', __( 'Production source not found.', VWLB_TEXT_DOMAIN ), 404 );
			$row['version'] = (int)$current['version'] + 1;
			$ok = $wpdb->update( $table, $row, array( 'id'=>$id, 'version'=>(int)$current['version'] ) );
			if ( 1 !== $ok ) return VWLB_Helpers::error( 'vwlb_version_conflict', __( 'Production source changed. Refresh and try again.', VWLB_TEXT_DOMAIN ), 409 );
			$public = $current['public_id'];
		} else {
			$public = VWLB_Helpers::public_id( 'src' ); $row['public_id']=$public; $row['created_at']=$now; $row['version']=1;
			if ( ! $wpdb->insert( $table, $row ) ) return VWLB_Helpers::error( 'vwlb_database_error', __( 'Production source could not be saved.', VWLB_TEXT_DOMAIN ), 500 );
			$id = (int)$wpdb->insert_id;
		}
		VWLB_Helpers::audit( 'live', $event['id'], 'production_source_saved', $event['status'], $event['status'], $type, array( 'source_id'=>$id ) );
		do_action( 'vwlb_production_source_changed', $event, self::public_row( 'production_sources', $id ) );
		return self::public_row( 'production_sources', $id );
	}

	/** F10-FUT-001 — scene graph with an atomic program-scene switch. */
	public static function upsert_scene( $live_id, $data ) {
		$event = self::live( $live_id );
		if ( ! self::require_live_control( $event, 'future_production_scene' ) ) return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot manage production scenes.', VWLB_TEXT_DOMAIN ), 403 );
		$title = VWLB_Helpers::text( $data['title'] ?? '', 191 ); if ( ! $title ) return VWLB_Helpers::error( 'vwlb_scene_title_required', __( 'Scene title is required.', VWLB_TEXT_DOMAIN ), 422 );
		$sources = array_values( array_unique( array_filter( array_map( 'absint', (array)( $data['source_ids'] ?? array() ) ) ) ) );
		global $wpdb; $table=VWLB_Helpers::table('production_scenes'); $now=VWLB_Helpers::now();
		$id=absint($data['id']??0); $row=array('live_event_id'=>(int)$event['id'],'owner_id'=>get_current_user_id(),'title'=>$title,
			'layout_json'=>VWLB_Helpers::json_encode((array)($data['layout']??array())),'source_ids_json'=>VWLB_Helpers::json_encode($sources),'updated_at'=>$now);
		if($id){$current=self::public_row('production_scenes',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>(int)$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}
		else{$row['public_id']=VWLB_Helpers::public_id('scene');$row['state']='saved';$row['is_program']=0;$row['version']=1;$row['created_at']=$now;if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Scene could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		return self::public_row('production_scenes',$id);
	}

	public static function switch_program_scene( $live_id, $scene_id, $expected_version ) {
		$event=self::live($live_id); if(!self::require_live_control($event,'future_switch_scene'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot switch scenes.',VWLB_TEXT_DOMAIN),403);
		$scene=self::public_row('production_scenes',$scene_id); if(!$scene||(int)$scene['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);
		if((int)$scene['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);
		global $wpdb; $table=VWLB_Helpers::table('production_scenes');
		return VWLB_DB::transaction(function()use($wpdb,$table,$event,$scene){
			$wpdb->update($table,array('is_program'=>0,'updated_at'=>VWLB_Helpers::now()),array('live_event_id'=>$event['id'],'is_program'=>1));
			$ok=$wpdb->update($table,array('is_program'=>1,'version'=>(int)$scene['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$scene['id'],'version'=>$scene['version']));
			if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene switch conflicted with another operator.',VWLB_TEXT_DOMAIN),409);
			VWLB_Helpers::audit('live',$event['id'],'program_scene_switched',$event['status'],$event['status'],'',array('scene_id'=>$scene['id']));
			do_action('vwlb_program_scene_switched',$event,self::public_row('production_scenes',$scene['id']));
			return self::public_row('production_scenes',$scene['id']);
		});
	}

	/** F10-FUT-002 — time-bounded guest/co-host delegation, never a new identity authority. */
	public static function invite_guest( $live_id, $user_id, $role='guest', $scope=array(), $ttl=7200 ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_invite_guest'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot invite broadcast guests.',VWLB_TEXT_DOMAIN),403);
		$user_id=absint($user_id);if(!$user_id||!get_userdata($user_id))return VWLB_Helpers::error('vwlb_guest_invalid',__('Guest account is invalid.',VWLB_TEXT_DOMAIN),422);
		$role=VWLB_Helpers::enum($role,array('guest','cohost','presenter'),'guest');$ttl=max(300,min(DAY_IN_SECONDS,(int)$ttl));
		global $wpdb;$table=VWLB_Helpers::table('broadcast_guests');$now=VWLB_Helpers::now();$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d",$event['id'],$user_id),ARRAY_A);
		$row=array('role_name'=>$role,'status'=>'invited','scope_json'=>VWLB_Helpers::json_encode(array_values(array_unique(array_map('sanitize_key',(array)$scope)))),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$ttl),'invited_by'=>get_current_user_id(),'accepted_at'=>null,'updated_at'=>$now);
		if($existing){$row['version']=(int)$existing['version']+1;$wpdb->update($table,$row,array('id'=>$existing['id'],'version'=>$existing['version']));$id=(int)$existing['id'];}
		else{$row+=array('public_id'=>VWLB_Helpers::public_id('guest'),'live_event_id'=>(int)$event['id'],'user_id'=>$user_id,'version'=>1,'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Guest invitation could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		VWLB_Helpers::outbox('BroadcastGuestInvited','live',$event['id'],array('guest_user_id'=>$user_id,'role'=>$role));return self::public_row('broadcast_guests',$id);
	}

	public static function accept_guest( $guest_public_id ) {
		$row=self::public_row('broadcast_guests',$guest_public_id);if(!$row||(int)$row['user_id']!==get_current_user_id())return VWLB_Helpers::error('vwlb_guest_invite_missing',__('Guest invitation not found.',VWLB_TEXT_DOMAIN),404);
		if('invited'!==$row['status']||strtotime($row['expires_at'].' UTC')<=time())return VWLB_Helpers::error('vwlb_guest_invite_expired',__('Guest invitation is no longer active.',VWLB_TEXT_DOMAIN),409);
		global $wpdb;$wpdb->update(VWLB_Helpers::table('broadcast_guests'),array('status'=>'accepted','accepted_at'=>VWLB_Helpers::now(),'version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$row['id'],'version'=>$row['version']));
		return self::public_row('broadcast_guests',$row['id']);
	}

	/** F10-FUT-004/005/006/008 — live DVR, latency, ingest protocols and redundancy policy. */
	public static function configure_live( $live_id, $data, $expected_version=0 ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_live_config'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot configure this broadcast.',VWLB_TEXT_DOMAIN),403);
		$latency=VWLB_Helpers::enum($data['latency_mode']??'standard',array('standard','low','ultra_low'),'standard');
		$dvr=max(0,min(6*HOUR_IN_SECONDS,(int)($data['dvr_window_seconds']??0)));$protocols=array_values(array_unique(array_intersect(array_map('strtolower',(array)($data['protocols']??array('rtmp'))),array('rtmp','srt','webrtc'))));if(!$protocols)$protocols=array('rtmp');
		$provider_caps=(array)apply_filters('vwlb_provider_future_capabilities',array(),$event['provider'],$event);foreach($protocols as $p){if('rtmp'!==$p&&!in_array($p,(array)($provider_caps['ingest_protocols']??array()),true))return VWLB_Helpers::error('vwlb_protocol_unavailable',__('Requested ingest protocol is not configured for this provider.',VWLB_TEXT_DOMAIN),503,array('protocol'=>$p));}
		$backup=VWLB_Helpers::text($data['backup_provider']??'',64);if($backup&&$backup===$event['provider'])return VWLB_Helpers::error('vwlb_backup_provider_invalid',__('Backup provider must differ from the primary provider.',VWLB_TEXT_DOMAIN),422);
		$languages=array_values(array_unique(array_filter(array_map(function($v){return VWLB_Helpers::text($v,32);},(array)($data['translation_languages']??array())))));
		global $wpdb;$table=VWLB_Helpers::table('future_live_config');$current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d",$event['id']),ARRAY_A);$now=VWLB_Helpers::now();
		$row=array('latency_mode'=>$latency,'dvr_window_seconds'=>$dvr,'backup_provider'=>$backup,'multicam_enabled'=>!empty($data['multicam_enabled'])?1:0,'simulcast_enabled'=>!empty($data['simulcast_enabled'])?1:0,'redundant_recording'=>!empty($data['redundant_recording'])?1:0,'protocols_json'=>VWLB_Helpers::json_encode($protocols),'translation_languages_json'=>VWLB_Helpers::json_encode($languages),'updated_by'=>get_current_user_id(),'updated_at'=>$now);
		if($current){if($expected_version&&(int)$current['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Broadcast configuration changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Broadcast configuration changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);$id=(int)$current['id'];}
		else{$row+=array('live_event_id'=>(int)$event['id'],'version'=>1,'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Broadcast configuration could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		VWLB_Helpers::audit('live',$event['id'],'future_live_config_saved',$event['status'],$event['status'],'',array('latency_mode'=>$latency,'dvr_window_seconds'=>$dvr,'protocols'=>$protocols));return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
	}

	/** F10-FUT-007 — outbound simulcast target references; raw external secrets are forbidden. */
	public static function upsert_simulcast_target( $live_id, $data ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_simulcast'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage simulcast targets.',VWLB_TEXT_DOMAIN),403);
		$platform=VWLB_Helpers::text($data['platform']??'',64);$target=VWLB_Helpers::text($data['provider_target_ref']??'',191);$credential=VWLB_Helpers::text($data['credential_ref']??'',191);if(!$platform||!$target)return VWLB_Helpers::error('vwlb_simulcast_fields_required',__('Platform and provider target reference are required.',VWLB_TEXT_DOMAIN),422);
		if(!empty($data['stream_key'])||!empty($data['secret']))return VWLB_Helpers::error('vwlb_simulcast_secret_forbidden',__('Raw destination secrets cannot be stored in File 10.',VWLB_TEXT_DOMAIN),422);
		$status=VWLB_Helpers::enum($data['status']??'disabled',array('disabled','ready','active','degraded','failed'),'disabled');global $wpdb;$table=VWLB_Helpers::table('simulcast_targets');$id=absint($data['id']??0);$now=VWLB_Helpers::now();
		$row=array('platform'=>$platform,'provider_target_ref'=>$target,'credential_ref'=>$credential,'status'=>$status,'config_json'=>VWLB_Helpers::json_encode((array)($data['config']??array())),'updated_at'=>$now);
		if($id){$current=self::public_row('simulcast_targets',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}
		else{$row+=array('public_id'=>VWLB_Helpers::public_id('sim'),'live_event_id'=>(int)$event['id'],'last_state_json'=>'{}','version'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Simulcast target could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		do_action('vwlb_simulcast_target_changed',$event,self::public_row('simulcast_targets',$id));return self::public_row('simulcast_targets',$id);
	}

	/** F10-FUT-009 — provider-fed health sample with bounded values and redacted DTO. */
	public static function record_health( $live_id, $sample ) {
		$event=self::live($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_health_sample'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot record broadcast health.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$row=array('live_event_id'=>(int)$event['id'],'source_public_id'=>VWLB_Helpers::text($sample['source_public_id']??'',64),'bitrate_kbps'=>max(0,min(200000,(int)($sample['bitrate_kbps']??0))),'packet_loss_bp'=>max(0,min(10000,(int)($sample['packet_loss_bp']??0))),'dropped_frames'=>max(0,(int)($sample['dropped_frames']??0)),'jitter_ms'=>max(0,min(60000,(int)($sample['jitter_ms']??0))),'latency_ms'=>max(0,min(300000,(int)($sample['latency_ms']??0))),'audio_peak_db'=>isset($sample['audio_peak_db'])?max(-120,min(12,(float)$sample['audio_peak_db'])):null,'state'=>VWLB_Helpers::enum($sample['state']??'unknown',array('healthy','warning','degraded','failed','unknown'),'unknown'),'captured_at'=>VWLB_Helpers::now());
		if(!$wpdb->insert(VWLB_Helpers::table('broadcast_health_samples'),$row))return VWLB_Helpers::error('vwlb_database_error',__('Health sample could not be recorded.',VWLB_TEXT_DOMAIN),500);return $row;
	}

	public static function health_snapshot( $live_id ) {
		$event=self::live($live_id);if(!$event||!VWLB_Security::can(VWLB_Contracts::CAP_OPERATE,$event,'future_health_dashboard'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot view broadcast health.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$table=VWLB_Helpers::table('broadcast_health_samples');$items=$wpdb->get_results($wpdb->prepare("SELECT source_public_id,bitrate_kbps,packet_loss_bp,dropped_frames,jitter_ms,latency_ms,audio_peak_db,state,captured_at FROM $table WHERE live_event_id=%d ORDER BY id DESC LIMIT 100",$event['id']),ARRAY_A);return array('live_event_id'=>(int)$event['id'],'items'=>$items,'generated_at'=>gmdate('c'));
	}

	/** F10-FUT-010/011 — deterministic rendition policy, provider may refine but not weaken safety. */
	public static function content_aware_profile( $profile, $asset, $context=array() ) {
		$profile=is_array($profile)?$profile:array();$kind=VWLB_Helpers::enum($context['content_kind']??'talking_head',array('talking_head','slides','screen','motion','mixed'),'talking_head');$height=max(0,(int)($context['height']??0));$hdr=!empty($context['hdr']);$av1=!empty($context['allow_av1']);
		$ladders=array('talking_head'=>array(360,540,720,1080),'slides'=>array(360,720,1080),'screen'=>array(540,720,1080,1440),'motion'=>array(360,540,720,1080,1440,2160),'mixed'=>array(360,540,720,1080,1440));$r=array_values(array_filter($ladders[$kind],function($v)use($height){return !$height||$v<=$height;}));if(!$r)$r=array(min(360,max(144,$height)));
		$profile['content_kind']=$kind;$profile['rendition_heights']=$r;$profile['hdr']=$hdr&&max($r)>=1080;$profile['preferred_codecs']=$av1?array('av1','h265','h264'):array('h264');$profile['low_bandwidth_required']=true;$profile['generated_by']='File10FutureProfile.v1';return $profile;
	}

	/** F10-FUT-012..015 — reviewed auxiliary tracks. */
	public static function create_track( $object_type, $object_id, $data ) {
		$object_type=VWLB_Helpers::enum($object_type,array('video','live'),'');$track_type=VWLB_Helpers::enum($data['track_type']??'',array('translation','dub','audio_description','sign_language'),'');if(!$object_type||!$track_type)return VWLB_Helpers::error('vwlb_track_type_invalid',__('Track type is invalid.',VWLB_TEXT_DOMAIN),422);
		$object='video'===$object_type?self::video($object_id):self::live($object_id);$cap='live'===$object_type?VWLB_Contracts::CAP_BROADCAST:VWLB_Contracts::CAP_PUBLISH;if(!$object||!VWLB_Security::can($cap,$object,'future_media_track'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage this media track.',VWLB_TEXT_DOMAIN),403);
		$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','provider','ai_assisted'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_review');$status='manual'===$source&&$can_review?'reviewed':'candidate';if(isset($data['status'])){$requested=VWLB_Helpers::enum($data['status'],array('candidate','reviewed','published','rejected','removed'),$status);if('candidate'!==$requested&&!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Human review permission is required to change a generated track review state.',VWLB_TEXT_DOMAIN),403);$status=$requested;}if('published'===$status&&!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$object,'future_track_publish'))return VWLB_Helpers::error('vwlb_review_required',__('Human review is required before publishing generated tracks.',VWLB_TEXT_DOMAIN),403);
		global $wpdb;$now=VWLB_Helpers::now();$row=array('public_id'=>VWLB_Helpers::public_id('track'),'object_type'=>$object_type,'object_id'=>(int)$object['id'],'track_type'=>$track_type,'language'=>VWLB_Helpers::text($data['language']??'',32),'source'=>$source,'status'=>$status,'file_ref'=>esc_url_raw($data['file_ref']??''),'provider_ref'=>VWLB_Helpers::text($data['provider_ref']??'',191),'metadata_json'=>VWLB_Helpers::json_encode((array)($data['metadata']??array())),'version'=>1,'created_by'=>get_current_user_id(),'reviewed_by'=>in_array($status,array('reviewed','published'),true)?get_current_user_id():0,'created_at'=>$now,'updated_at'=>$now);if(!$wpdb->insert(VWLB_Helpers::table('media_tracks'),$row))return VWLB_Helpers::error('vwlb_database_error',__('Media track could not be saved.',VWLB_TEXT_DOMAIN),500);return self::public_row('media_tracks',(int)$wpdb->insert_id);
	}

	/** F10-FUT-016/017/018/021/024 — reviewable timed annotations and knowledge links. */
	public static function create_annotation( $video_id, $data ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'future_annotation'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot annotate this video.',VWLB_TEXT_DOMAIN),403);
		$kind=VWLB_Helpers::enum($data['kind']??'',array('key_moment','citation','overlay','correction','knowledge_bridge'),'');if(!$kind)return VWLB_Helpers::error('vwlb_annotation_kind_invalid',__('Annotation type is invalid.',VWLB_TEXT_DOMAIN),422);
		$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','ai_assisted','imported'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_review');$status=($source==='manual'&&$can_review)?'reviewed':'candidate';if('correction'===$kind){if(!$can_review)return VWLB_Helpers::error('vwlb_review_required',__('Timestamp corrections require independent review permission.',VWLB_TEXT_DOMAIN),403);$status='reviewed';}
		$source_owner=VWLB_Helpers::text($data['source_owner']??'',64);$source_ref=VWLB_Helpers::text($data['source_ref']??'',191);if(in_array($kind,array('citation','knowledge_bridge'),true)&&(!$source_owner||!$source_ref))return VWLB_Helpers::error('vwlb_annotation_source_required',__('A canonical source owner and reference are required.',VWLB_TEXT_DOMAIN),422);
		global $wpdb;$now=VWLB_Helpers::now();$row=array('public_id'=>VWLB_Helpers::public_id('ann'),'video_id'=>(int)$video['id'],'kind'=>$kind,'start_ms'=>max(0,(int)($data['start_ms']??0)),'end_ms'=>max(0,(int)($data['end_ms']??0)),'title'=>VWLB_Helpers::text($data['title']??'',255),'body'=>VWLB_Helpers::textarea($data['body']??''),'source_owner'=>$source_owner,'source_ref'=>$source_ref,'status'=>$status,'metadata_json'=>VWLB_Helpers::json_encode(array_merge((array)($data['metadata']??array()),array('source_mode'=>$source))),'version'=>1,'created_by'=>get_current_user_id(),'reviewed_by'=>'candidate'===$status?0:get_current_user_id(),'created_at'=>$now,'updated_at'=>$now);if(!$wpdb->insert(VWLB_Helpers::table('video_annotations'),$row))return VWLB_Helpers::error('vwlb_database_error',__('Annotation could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;if('correction'===$kind)VWLB_Helpers::outbox('VideoTimestampCorrectionPublished','video',$video['id'],array('annotation_public_id'=>$row['public_id'],'start_ms'=>$row['start_ms']));return self::public_row('video_annotations',$id);
	}

	public static function annotations( $video_id, $include_candidates=false ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);global $wpdb;$table=VWLB_Helpers::table('video_annotations');$statuses=$include_candidates&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_list')?"('candidate','reviewed','published')":"('reviewed','published')";$items=$wpdb->get_results($wpdb->prepare("SELECT public_id,kind,start_ms,end_ms,title,body,source_owner,source_ref,status,metadata_json,version FROM $table WHERE video_id=%d AND status IN $statuses ORDER BY start_ms ASC,id ASC",$video['id']),ARRAY_A);foreach($items as &$i)$i['metadata']=VWLB_Helpers::json($i['metadata_json']);return array('items'=>$items);
	}

	/** F10-FUT-020 — transcript index write and bounded search. */
	public static function index_transcript_segment( $video_id, $data ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_transcript_index'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot index this transcript.',VWLB_TEXT_DOMAIN),403);$text=trim(wp_strip_all_tags((string)($data['text']??'')));if(!$text)return VWLB_Helpers::error('vwlb_segment_text_required',__('Transcript segment text is required.',VWLB_TEXT_DOMAIN),422);global $wpdb;$row=array('video_id'=>(int)$video['id'],'track_id'=>absint($data['track_id']??0),'language'=>VWLB_Helpers::text($data['language']??'',32),'start_ms'=>max(0,(int)($data['start_ms']??0)),'end_ms'=>max(0,(int)($data['end_ms']??0)),'segment_text'=>mb_substr($text,0,4000),'status'=>VWLB_Helpers::enum($data['status']??'reviewed',array('candidate','reviewed','removed'),'reviewed'),'created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now());if(!$wpdb->insert(VWLB_Helpers::table('transcript_segments'),$row))return VWLB_Helpers::error('vwlb_database_error',__('Transcript segment could not be indexed.',VWLB_TEXT_DOMAIN),500);return array('id'=>(int)$wpdb->insert_id)+$row;
	}

	public static function search_transcript( $video_id, $query, $language='' ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);$query=trim(wp_strip_all_tags((string)$query));if(mb_strlen($query)<2)return VWLB_Helpers::error('vwlb_search_too_short',__('Search query is too short.',VWLB_TEXT_DOMAIN),422);global $wpdb;$table=VWLB_Helpers::table('transcript_segments');$like='%'.$wpdb->esc_like($query).'%';$language=VWLB_Helpers::text($language,32);if($language)$items=$wpdb->get_results($wpdb->prepare("SELECT start_ms,end_ms,language,segment_text FROM $table WHERE video_id=%d AND status='reviewed' AND language=%s AND segment_text LIKE %s ORDER BY start_ms ASC LIMIT 50",$video['id'],$language,$like),ARRAY_A);else$items=$wpdb->get_results($wpdb->prepare("SELECT start_ms,end_ms,language,segment_text FROM $table WHERE video_id=%d AND status='reviewed' AND segment_text LIKE %s ORDER BY start_ms ASC LIMIT 50",$video['id'],$like),ARRAY_A);return array('query'=>$query,'items'=>$items);
	}

	/** F10-FUT-019 — educational poll/knowledge check, no diagnostic scoring. */
	public static function create_poll( $live_id, $data ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_live_poll'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot create this poll.',VWLB_TEXT_DOMAIN),403);$q=VWLB_Helpers::textarea($data['question']??'');$options=array_values(array_filter(array_map(function($v){return VWLB_Helpers::text(is_array($v)?($v['text']??''):$v,255);},(array)($data['options']??array()))));if(!$q||count($options)<2)return VWLB_Helpers::error('vwlb_poll_fields_required',__('A question and at least two options are required.',VWLB_TEXT_DOMAIN),422);global $wpdb;$now=VWLB_Helpers::now();return VWLB_DB::transaction(function()use($wpdb,$event,$data,$q,$options,$now){$public=VWLB_Helpers::public_id('poll');$wpdb->insert(VWLB_Helpers::table('live_polls'),array('public_id'=>$public,'live_event_id'=>$event['id'],'question'=>$q,'poll_type'=>VWLB_Helpers::enum($data['poll_type']??'single',array('single','multiple','knowledge_check'),'single'),'status'=>VWLB_Helpers::enum($data['status']??'draft',array('draft','open','closed','removed'),'draft'),'opens_at'=>!empty($data['opens_at'])?VWLB_Helpers::datetime_in_timezone($data['opens_at'],$data['timezone']??'UTC'):null,'closes_at'=>!empty($data['closes_at'])?VWLB_Helpers::datetime_in_timezone($data['closes_at'],$data['timezone']??'UTC'):null,'metadata_json'=>VWLB_Helpers::json_encode(array('educational_only'=>true,'not_diagnostic'=>true)),'version'=>1,'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));if(!$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Poll could not be saved.',VWLB_TEXT_DOMAIN),500);$poll_id=(int)$wpdb->insert_id;foreach($options as $idx=>$text){$raw=(array)($data['options'][$idx]??array());$wpdb->insert(VWLB_Helpers::table('live_poll_options'),array('poll_id'=>$poll_id,'public_id'=>VWLB_Helpers::public_id('opt'),'option_text'=>$text,'is_correct'=>!empty($raw['is_correct'])?1:0,'sort_order'=>$idx));}VWLB_Helpers::outbox('LivePollChanged','live',$event['id'],array('poll_public_id'=>$public));return self::poll($public,true);});
	}

	public static function poll( $poll_id, $include_answers=false ) {
		$poll=self::public_row('live_polls',$poll_id);if(!$poll)return null;$event=self::live($poll['live_event_id']);if(!$event||!VWLB_Security::can_view($event))return null;global $wpdb;$options=$wpdb->get_results($wpdb->prepare('SELECT id,public_id,option_text,is_correct,sort_order FROM '.VWLB_Helpers::table('live_poll_options').' WHERE poll_id=%d ORDER BY sort_order ASC,id ASC',$poll['id']),ARRAY_A);$can_answers=$include_answers&&VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'future_poll_answers');foreach($options as &$o){if(!$can_answers)unset($o['is_correct']);$o['responses']=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT user_id) FROM '.VWLB_Helpers::table('live_poll_responses').' WHERE poll_id=%d AND option_id=%d',$poll['id'],$o['id']));unset($o['id']);}unset($poll['metadata_json']);$poll['options']=$options;return $poll;
	}

	public static function answer_poll( $poll_id, $option_ids ) {
		if(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Login is required.',VWLB_TEXT_DOMAIN),401);$poll=self::public_row('live_polls',$poll_id);if(!$poll||'open'!==$poll['status'])return VWLB_Helpers::error('vwlb_poll_unavailable',__('Poll is not open.',VWLB_TEXT_DOMAIN),409);$event=self::live($poll['live_event_id']);if(!$event||!VWLB_Security::can_view($event))return VWLB_Helpers::error('vwlb_not_found',__('Poll not found.',VWLB_TEXT_DOMAIN),404);$raw_ids=array_values(array_unique(array_filter(array_map(function($v){return VWLB_Helpers::text($v,64);},(array)$option_ids))));if('single'===$poll['poll_type']&&count($raw_ids)>1)return VWLB_Helpers::error('vwlb_poll_single_choice',__('Only one option may be selected.',VWLB_TEXT_DOMAIN),422);global $wpdb;$option_table=VWLB_Helpers::table('live_poll_options');$ids=array();foreach($raw_ids as $raw){if(ctype_digit($raw))$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $option_table WHERE poll_id=%d AND id=%d",$poll['id'],(int)$raw));else$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $option_table WHERE poll_id=%d AND public_id=%s",$poll['id'],$raw));if($id)$ids[]=$id;}$ids=array_values(array_unique($ids));if(!$ids)return VWLB_Helpers::error('vwlb_poll_option_invalid',__('Selected option is invalid.',VWLB_TEXT_DOMAIN),422);return VWLB_DB::transaction(function()use($wpdb,$poll,$ids){$wpdb->delete(VWLB_Helpers::table('live_poll_responses'),array('poll_id'=>$poll['id'],'user_id'=>get_current_user_id()));foreach($ids as $id)$wpdb->insert(VWLB_Helpers::table('live_poll_responses'),array('poll_id'=>$poll['id'],'user_id'=>get_current_user_id(),'option_id'=>$id,'created_at'=>VWLB_Helpers::now()));return array('accepted'=>true,'poll_public_id'=>$poll['public_id']);});
	}

	/** F10-FUT-022 — consent lifecycle linked to a video. */
	public static function upsert_consent_link( $video_id, $data ) {
		$video=self::video($video_id);if(!$video||!VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_consent_link'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage consent for this video.',VWLB_TEXT_DOMAIN),403);$ref=VWLB_Helpers::text($data['consent_ref']??'',191);if(!$ref)return VWLB_Helpers::error('vwlb_consent_ref_required',__('Consent reference is required.',VWLB_TEXT_DOMAIN),422);$status=VWLB_Helpers::enum($data['status']??'active',array('active','expired','withdrawn','superseded'),'active');$expires=!empty($data['expires_at'])?VWLB_Helpers::datetime_in_timezone($data['expires_at'],$data['timezone']??'UTC'):null;global $wpdb;$table=VWLB_Helpers::table('consent_links');$current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE video_id=%d AND consent_ref=%s",$video['id'],$ref),ARRAY_A);$row=array('subject_ref'=>VWLB_Helpers::text($data['subject_ref']??'',191),'status'=>$status,'expires_at'=>$expires,'withdrawn_at'=>'withdrawn'===$status?VWLB_Helpers::now():null,'metadata_json'=>VWLB_Helpers::json_encode((array)($data['metadata']??array())),'updated_at'=>VWLB_Helpers::now());if($current){$row['version']=(int)$current['version']+1;$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$current['version']));$id=(int)$current['id'];}else{$row+=array('video_id'=>(int)$video['id'],'consent_ref'=>$ref,'version'=>1,'created_by'=>get_current_user_id(),'created_at'=>VWLB_Helpers::now());if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Consent link could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}if(in_array($status,array('expired','withdrawn'),true))self::restrict_video_for_consent($video,$status);return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
	}

	private static function restrict_video_for_consent( $video, $reason ) {
		if(in_array($video['status'],array('removed','restricted'),true))return;$updated=VWLB_Repository::update_versioned('videos',$video['id'],$video['version'],array('status'=>'restricted'));if(!is_wp_error($updated)){VWLB_Helpers::audit('video',$video['id'],'consent_auto_restrict',$video['status'],'restricted',$reason);VWLB_Helpers::outbox('VideoRestricted','video',$video['id'],array('reason'=>'consent_'.$reason));do_action('vwlb_purge_media_derivative_caches',$video['public_id'],'consent_'.$reason);}
	}

	public static function reconcile_consent_expiry() {
		global $wpdb;$table=VWLB_Helpers::table('consent_links');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='active' AND expires_at IS NOT NULL AND expires_at<=%s ORDER BY id ASC LIMIT 100",VWLB_Helpers::now()),ARRAY_A);foreach($rows as $row){$changed=$wpdb->update($table,array('status'=>'expired','version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$row['id'],'version'=>$row['version'],'status'=>'active'));if(1===$changed){$video=self::video($row['video_id']);if($video)self::restrict_video_for_consent($video,'expired');}}
	}

	/** F10-FUT-023 — optional session-aware forensic watermark payload; no false anti-piracy guarantee. */
	public static function set_watermark_policy( $object_type, $object_id, $data ) {
		$object_type=VWLB_Helpers::enum($object_type,array('video','live'),'');$object='video'===$object_type?self::video($object_id):self::live($object_id);$cap='video'===$object_type?VWLB_Contracts::CAP_PUBLISH:VWLB_Contracts::CAP_BROADCAST;if(!$object_type||!$object||!VWLB_Security::can($cap,$object,'future_watermark_policy'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot manage watermark policy.',VWLB_TEXT_DOMAIN),403);$mode=VWLB_Helpers::enum($data['mode']??'off',array('off','visible','forensic','both'),'off');global $wpdb;$table=VWLB_Helpers::table('watermark_policies');$current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE object_type=%s AND object_id=%d",$object_type,$object['id']),ARRAY_A);$row=array('mode'=>$mode,'status'=>VWLB_Helpers::enum($data['status']??'active',array('active','disabled'),'active'),'policy_json'=>VWLB_Helpers::json_encode((array)($data['policy']??array())),'updated_by'=>get_current_user_id(),'updated_at'=>VWLB_Helpers::now());if($current){$row['version']=(int)$current['version']+1;$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$current['version']));$id=(int)$current['id'];}else{$row+=array('object_type'=>$object_type,'object_id'=>(int)$object['id'],'version'=>1,'created_at'=>VWLB_Helpers::now());if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Watermark policy could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
	}

	public static function watermark_payload( $payload, $object_type, $object, $session=array() ) {
		if(!is_array($object)||empty($object['id']))return $payload;global $wpdb;$table=VWLB_Helpers::table('watermark_policies');$policy=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE object_type=%s AND object_id=%d AND status='active'",sanitize_key($object_type),(int)$object['id']),ARRAY_A);if(!$policy||'off'===$policy['mode'])return $payload;$nonce=wp_generate_uuid4();$fingerprint=hash_hmac('sha256',(int)get_current_user_id().'|'.($object['public_id']??$object['id']).'|'.$nonce,wp_salt('auth'));return array('mode'=>$policy['mode'],'token'=>substr($fingerprint,0,32),'visible_label'=>in_array($policy['mode'],array('visible','both'),true)?VWLB_Helpers::text(apply_filters('vwlb_visible_watermark_label',__('Protected educational media',VWLB_TEXT_DOMAIN),$object_type,$object),128):'','expires_at'=>gmdate('c',time()+15*MINUTE_IN_SECONDS),'non_guarantee'=>'Watermarking is deterrence/trace evidence, not an absolute copying-prevention guarantee.');
	}

	/** Provider redundancy reconciliation — F10-FUT-008. */
	public static function reconcile_live_redundancy() {
		global $wpdb;$cfg=VWLB_Helpers::table('future_live_config');$live=VWLB_Helpers::table('live_events');$rows=$wpdb->get_results("SELECT c.*,l.public_id,l.provider,l.status FROM $cfg c INNER JOIN $live l ON l.id=c.live_event_id WHERE l.status IN ('ready','live','interrupted') AND (c.backup_provider<>'' OR c.redundant_recording=1) LIMIT 100",ARRAY_A);foreach($rows as $row){do_action('vwlb_redundancy_reconcile',$row);if(!empty($row['redundant_recording']))do_action('vwlb_redundant_recording_required',$row);}
	}

	public static function cleanup() {
		global $wpdb;$health=VWLB_Helpers::table('broadcast_health_samples');$wpdb->query($wpdb->prepare("DELETE FROM $health WHERE captured_at<%s LIMIT 5000",gmdate('Y-m-d H:i:s',time()-7*DAY_IN_SECONDS)));$guests=VWLB_Helpers::table('broadcast_guests');$wpdb->query($wpdb->prepare("UPDATE $guests SET status='expired',updated_at=%s WHERE status IN ('invited','accepted') AND expires_at<=%s",VWLB_Helpers::now(),VWLB_Helpers::now()));
	}
}
