<?php
/** R10 sequential review: migration structural proof and private-storage fail-closed guard. */
defined( 'ABSPATH' ) || exit;

final class VWLB_R10_Integrity {
	private static $runtime_integrity_error = null;

	public static function register() {
		$integrity = self::verify_r10_runtime_integrity();
		if ( is_wp_error( $integrity ) ) self::fail_closed_runtime_integrity( $integrity );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_private_storage_before' ), 2, 3 );
	}
	/** R10: fail closed if the once-per-release structural migration proof is incomplete. */
	private static function fail_closed_runtime_integrity( $error ) {
		self::$runtime_integrity_error = $error;
		update_option( 'vwlb_safe_mode', 1, false );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'block_rest_on_integrity_failure' ), 0, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'block_frontend_on_integrity_failure' ), 0 );
		remove_action( 'vwlb_process_jobs', array( 'VWLB_Jobs', 'process' ) );
		remove_action( 'vwlb_publish_outbox', array( 'VWLB_Jobs', 'publish_outbox' ) );
		remove_action( 'vwlb_reconcile_states', array( 'VWLB_Jobs', 'reconcile' ) );
		add_action( 'admin_notices', static function() use ( $error ) { if ( current_user_can( 'manage_options' ) ) echo '<div class="notice notice-error"><p>' . esc_html( $error->get_error_message() ) . '</p></div>'; } );
	}

	public static function block_rest_on_integrity_failure( $result, $server, $request ) {
		if ( ! self::$runtime_integrity_error || ! $request instanceof WP_REST_Request ) return $result;
		$route = (string) $request->get_route();
		foreach ( VWLB_Contracts::namespaces() as $namespace ) if ( str_starts_with( $route, '/' . $namespace . '/' ) ) return self::$runtime_integrity_error;
		return $result;
	}

	public static function block_frontend_on_integrity_failure() {
		if ( ! self::$runtime_integrity_error ) return;
		foreach ( array( 'vwlb_video_id','vwlb_live_id','vwlb_channel_slug','vwlb_podcast_id','vwlb_route' ) as $key ) {
			if ( get_query_var( $key ) ) { status_header( 503 ); nocache_headers(); wp_die( esc_html( self::$runtime_integrity_error->get_error_message() ) ); }
		}
	}

	private static function r10_split_schema_parts( $body ) {
		$parts=array();$buffer='';$depth=0;$length=strlen((string)$body);
		for($i=0;$i<$length;$i++){ $char=$body[$i]; if('('===$char)$depth++; elseif(')'===$char&&$depth>0)$depth--; if(','===$char&&0===$depth){if(''!==trim($buffer))$parts[]=trim($buffer);$buffer='';continue;}$buffer.=$char; }
		if(''!==trim($buffer))$parts[]=trim($buffer); return $parts;
	}

	private static function r10_contract_from_create_sql( $statement ) {
		$statement=(string)$statement; if(!preg_match('/CREATE\s+TABLE\s+([^\s(]+)/i',$statement,$match,PREG_OFFSET_CAPTURE))return array();
		$table=trim($match[1][0],'`');$open=strpos($statement,'(',$match[1][1]+strlen($match[1][0]));$close=strrpos($statement,')');if(false===$open||false===$close||$close<=$open)return array();
		$columns=array();$indexes=array();
		foreach(self::r10_split_schema_parts(substr($statement,$open+1,$close-$open-1)) as $part){
			if(preg_match('/^PRIMARY\s+KEY\s*\(([^)]+)\)/i',$part,$index_match)){$name='PRIMARY';$raw=$index_match[1];}
			elseif(preg_match('/^(?:(?:UNIQUE|FULLTEXT)\s+)?KEY\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]+)\)/i',$part,$index_match)){$name=$index_match[1];$raw=$index_match[2];}
			else{if(preg_match('/^`?([A-Za-z0-9_]+)`?\s+/',$part,$column_match))$columns[]=$column_match[1];continue;}
			$index_columns=array();foreach(explode(',',$raw) as $column)if(preg_match('/`?([A-Za-z0-9_]+)`?/',trim($column),$column_match))$index_columns[]=$column_match[1];if($index_columns)$indexes[$name]=$index_columns;
		}
		return array('table'=>$table,'columns'=>$columns,'indexes'=>$indexes);
	}

	private static function r10_verify_table_contract( $table, $columns, $indexes ) {
		global $wpdb; $table=preg_replace('/[^A-Za-z0-9_]/','',(string)$table); if(!$table)return VWLB_Helpers::error('vwlb_schema_contract_invalid',__('A File 10 schema contract is invalid.',VWLB_TEXT_DOMAIN),500);
		$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if($found!==$table)return VWLB_Helpers::error('vwlb_schema_incomplete',__('A required File 10 database table is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));
		$actual_columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);if(!is_array($actual_columns))return VWLB_Helpers::error('vwlb_schema_columns_unreadable',__('File 10 could not verify required database columns.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));foreach((array)$columns as $column)if(!in_array($column,$actual_columns,true))return VWLB_Helpers::error('vwlb_schema_column_missing',__('A required File 10 database column is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'column'=>$column));
		$rows=$wpdb->get_results("SHOW INDEX FROM `{$table}`",ARRAY_A);if(!is_array($rows))return VWLB_Helpers::error('vwlb_schema_indexes_unreadable',__('File 10 could not verify required database indexes.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));$actual=array();foreach($rows as $row){$name=(string)$row['Key_name'];$sequence=(int)$row['Seq_in_index'];$actual[$name][$sequence]=(string)$row['Column_name'];}foreach($actual as &$cols){ksort($cols);$cols=array_values($cols);}unset($cols);foreach((array)$indexes as $name=>$expected){if(empty($actual[$name]))return VWLB_Helpers::error('vwlb_schema_index_missing',__('A required File 10 database index is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));if(array_values($expected)!==$actual[$name])return VWLB_Helpers::error('vwlb_schema_index_mismatch',__('A required File 10 database index has the wrong column order.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));}return true;
	}

	private static function r10_extension_future_contracts() {
		return array(
			'upload_sessions'=>array('columns'=>array('id','public_id','asset_id','owner_id','token_hash','expected_bytes','received_bytes','next_offset','chunk_size','checksum_expected','private_filename','status','expires_at','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'owner_status'=>array('owner_id','status'),'asset_id'=>array('asset_id'),'expires_at'=>array('expires_at'))),
			'chapters'=>array('columns'=>array('id','public_id','object_type','object_id','start_seconds','end_seconds','title','summary','source','status','version','created_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'object_start'=>array('object_type','object_id','start_seconds'),'object_status'=>array('object_type','object_id','status'))),
			'live_attendees'=>array('columns'=>array('id','public_id','live_event_id','user_id','state','reminder_minutes','recording_consent','consent_version','consented_at','joined_at','left_at','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_user'=>array('live_event_id','user_id'),'state_event'=>array('state','live_event_id'))),
			'live_questions'=>array('columns'=>array('id','public_id','live_event_id','user_id','question','status','answer','moderator_id','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_status'=>array('live_event_id','status'),'user_id'=>array('user_id'))),
			'live_resources'=>array('columns'=>array('id','public_id','live_event_id','title','resource_type','url','attachment_id','rights_status','status','version','created_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_status'=>array('live_event_id','status'))),
			'download_tokens'=>array('columns'=>array('id','public_id','token_hash','user_id','object_type','object_id','rights_snapshot','max_downloads','download_count','status','expires_at','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'object_user'=>array('object_type','object_id','user_id'),'expires_at'=>array('expires_at'))),
			'creator_metrics_daily'=>array('columns'=>array('id','metric_date','owner_id','object_type','object_id','views','completions','saves','source_opens','meaningful_comments','harm_reports','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'metric_object'=>array('metric_date','owner_id','object_type','object_id'),'owner_date'=>array('owner_id','metric_date'))),
			'provider_health'=>array('columns'=>array('id','provider','capability','state','failures','last_latency_ms','circuit_open_until','last_error_code','checked_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'provider_capability'=>array('provider','capability'),'state'=>array('state'))),
			'premieres'=>array('columns'=>array('id','public_id','video_id','live_event_id','owner_id','status','scheduled_at','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'live_event_id'=>array('live_event_id'),'video_schedule'=>array('video_id','scheduled_at'),'owner_status'=>array('owner_id','status'))),
			'future_live_config'=>array('columns'=>array('id','live_event_id','latency_mode','dvr_window_seconds','backup_provider','multicam_enabled','simulcast_enabled','redundant_recording','protocols_json','translation_languages_json','version','updated_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'live_event_id'=>array('live_event_id'))),
			'production_sources'=>array('columns'=>array('id','public_id','live_event_id','owner_id','source_type','label','provider_ref','state','config_json','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_state'=>array('live_event_id','state'),'owner_id'=>array('owner_id'))),
			'production_scenes'=>array('columns'=>array('id','public_id','live_event_id','owner_id','title','layout_json','source_ids_json','state','is_program','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_program'=>array('live_event_id','is_program'))),
			'broadcast_guests'=>array('columns'=>array('id','public_id','live_event_id','user_id','role_name','status','scope_json','expires_at','invited_by','accepted_at','version','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_user'=>array('live_event_id','user_id'),'status_expiry'=>array('status','expires_at'))),
			'simulcast_targets'=>array('columns'=>array('id','public_id','live_event_id','platform','provider_target_ref','credential_ref','status','config_json','last_state_json','version','created_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_platform_ref'=>array('live_event_id','platform','provider_target_ref'),'event_status'=>array('live_event_id','status'))),
			'broadcast_health_samples'=>array('columns'=>array('id','live_event_id','source_public_id','bitrate_kbps','packet_loss_bp','dropped_frames','jitter_ms','latency_ms','audio_peak_db','state','captured_at'),'indexes'=>array('PRIMARY'=>array('id'),'event_time'=>array('live_event_id','captured_at'))),
			'media_tracks'=>array('columns'=>array('id','public_id','object_type','object_id','track_type','language','source','status','file_ref','provider_ref','metadata_json','version','created_by','reviewed_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'object_track'=>array('object_type','object_id','track_type','status'),'language'=>array('language'))),
			'transcript_segments'=>array('columns'=>array('id','video_id','track_id','language','start_ms','end_ms','segment_text','status','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'video_language'=>array('video_id','language','status'),'segment_search'=>array('segment_text'))),
			'video_annotations'=>array('columns'=>array('id','public_id','video_id','kind','start_ms','end_ms','title','body','source_owner','source_ref','status','metadata_json','version','created_by','reviewed_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'video_kind_time'=>array('video_id','kind','start_ms','status'))),
			'live_polls'=>array('columns'=>array('id','public_id','live_event_id','question','poll_type','status','opens_at','closes_at','metadata_json','version','created_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'event_status'=>array('live_event_id','status'))),
			'live_poll_options'=>array('columns'=>array('id','poll_id','public_id','option_text','is_correct','sort_order'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'poll_sort'=>array('poll_id','sort_order'))),
			'live_poll_responses'=>array('columns'=>array('id','poll_id','user_id','option_id','created_at'),'indexes'=>array('PRIMARY'=>array('id'),'poll_user_option'=>array('poll_id','user_id','option_id'),'poll_id'=>array('poll_id'))),
			'consent_links'=>array('columns'=>array('id','video_id','consent_ref','subject_ref','status','expires_at','withdrawn_at','metadata_json','version','created_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'video_consent'=>array('video_id','consent_ref'),'consent_status_expiry'=>array('status','expires_at'))),
			'watermark_policies'=>array('columns'=>array('id','object_type','object_id','mode','status','policy_json','version','updated_by','created_at','updated_at'),'indexes'=>array('PRIMARY'=>array('id'),'object_policy'=>array('object_type','object_id'))),
		);
	}

	private static function verify_r10_runtime_integrity() {
		$marker=VWLB_VERSION . '|r10-structural-v1'; if((string)get_option('vwlb_r10_structural_verified_release','')===$marker)return true;
		foreach((array)VWLB_DB::schema_sql() as $statement){$contract=self::r10_contract_from_create_sql($statement);if(!$contract)continue;$verified=self::r10_verify_table_contract($contract['table'],$contract['columns'],$contract['indexes']);if(is_wp_error($verified))return $verified;}
		foreach(self::r10_extension_future_contracts() as $name=>$contract){$verified=self::r10_verify_table_contract(VWLB_Helpers::table($name),$contract['columns'],$contract['indexes']);if(is_wp_error($verified))return $verified;}
		$storage=self::r10_verify_private_storage();if(is_wp_error($storage))return $storage;
		$saved=update_option('vwlb_r10_structural_verified_release',$marker,false);if(!$saved&&(string)get_option('vwlb_r10_structural_verified_release','')!==$marker)return VWLB_Helpers::error('vwlb_schema_release_marker_failed',__('File 10 structural verification marker could not be recorded.',VWLB_TEXT_DOMAIN),500);return true;
	}

	private static function r10_verify_private_storage() {
		$base=trailingslashit(WP_CONTENT_DIR).VWLB_Extensions::PRIVATE_DIR;if(is_link($base))return VWLB_Helpers::error('vwlb_private_storage_symlink_forbidden',__('Private media storage cannot be a symbolic link.',VWLB_TEXT_DOMAIN),503);if(!is_dir($base)&&!wp_mkdir_p($base))return VWLB_Helpers::error('vwlb_private_storage_unavailable',__('Private media storage could not be created.',VWLB_TEXT_DOMAIN),503);
		$protect=array('index.php'=>"<?php\nhttp_response_code(404);\nexit;\n",'.htaccess'=>"Require all denied\nDeny from all\nOptions -Indexes\n",'web.config'=>'<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
		foreach($protect as $file=>$content){$path=trailingslashit($base).$file;if(is_link($path))return VWLB_Helpers::error('vwlb_private_storage_protection_symlink_forbidden',__('A private media protection file cannot be a symbolic link.',VWLB_TEXT_DOMAIN),503,array('file'=>$file));$actual=is_file($path)?file_get_contents($path):false;if(false===$actual||!hash_equals(hash('sha256',$content),hash('sha256',$actual))){$written=file_put_contents($path,$content,LOCK_EX);if(false===$written)return VWLB_Helpers::error('vwlb_private_storage_protection_failed',__('Private media storage protection could not be written.',VWLB_TEXT_DOMAIN),503,array('file'=>$file));$actual=file_get_contents($path);}if(false===$actual||!hash_equals(hash('sha256',$content),hash('sha256',$actual)))return VWLB_Helpers::error('vwlb_private_storage_protection_unverified',__('Private media storage protection could not be verified.',VWLB_TEXT_DOMAIN),503,array('file'=>$file));}return true;
	}

	/** R10: revalidate private upload protection on every resumable mutation, not only once per release. */
	public static function guard_private_storage_before( $response, $handler, $request ) {
		if(is_wp_error($response)||!$request instanceof WP_REST_Request)return $response;$route=(string)$request->get_route();if(!str_contains($route,'/media/resumable'))return $response;$storage=self::r10_verify_private_storage();return is_wp_error($storage)?$storage:$response;
	}

}
