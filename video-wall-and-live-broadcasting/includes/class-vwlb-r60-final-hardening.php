<?php
/** R60 final sequential-review hardening: activation compensation, external-effect retry safety, DB-failure truth and guarded repair. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R60_Final_Hardening {
	const ACTIVATION_SNAPSHOT_OPTION = 'vwlb_r60_activation_snapshot';
	const EXTERNAL_GUARD_PREFIX = 'vwlb_r60_external_guard_';
	const ACTIVATION_STALE_SECONDS = 900;
	private static $activation_committed = false;
	private static $request_guards = array();
	private static $shutdown_registered = false;

	private static function caps(){return array(VWLB_Contracts::CAP_SUBMIT,VWLB_Contracts::CAP_PUBLISH,VWLB_Contracts::CAP_BROADCAST,VWLB_Contracts::CAP_MODERATE,VWLB_Contracts::CAP_REVIEW,VWLB_Contracts::CAP_OPERATE,VWLB_Contracts::CAP_MANAGE,VWLB_Contracts::CAP_DIAGNOSTICS);}
	private static function cron_hooks(){return array('vwlb_process_jobs','vwlb_publish_outbox','vwlb_reconcile_states','vwlb_cleanup');}
	private static function option_state($name){$sentinel='__vwlb_r60_missing_'.wp_generate_uuid4();$value=get_option($name,$sentinel);return array('exists'=>$value!==$sentinel,'value'=>$value!==$sentinel?$value:null);}
	private static function restore_option($name,$state){
		if(!empty($state['exists'])){$saved=update_option($name,$state['value'],false);return $saved||get_option($name,null)===$state['value'];}
		$deleted=delete_option($name);return $deleted||false===get_option($name,false);
	}
	private static function cron_state($hook){$event=function_exists('wp_get_scheduled_event')?wp_get_scheduled_event($hook):false;if(!$event)return array('exists'=>false);return array('exists'=>true,'timestamp'=>(int)$event->timestamp,'schedule'=>(string)$event->schedule,'args'=>(array)$event->args);}

	public static function activation_begin(){
		$existing=get_option(self::ACTIVATION_SNAPSHOT_OPTION,false);
		if(is_array($existing)){
			$age=time()-absint($existing['started_at']??0);
			if($age>0&&$age<self::ACTIVATION_STALE_SECONDS)wp_die(esc_html__('File 10 activation compensation is already in progress. Retry shortly.',VWLB_TEXT_DOMAIN));
			if(!self::rollback_activation_snapshot($existing))wp_die(esc_html__('A stale File 10 activation could not be rolled back safely.',VWLB_TEXT_DOMAIN));
		}
		$role=get_role('administrator');$caps=array();foreach(self::caps() as $cap)$caps[$cap]=$role?(bool)$role->has_cap($cap):false;
		$cron=array();foreach(self::cron_hooks() as $hook)$cron[$hook]=self::cron_state($hook);
		$snapshot=array('token'=>wp_generate_uuid4(),'started_at'=>time(),'page_map'=>self::option_state('vwlb_page_map'),'version'=>self::option_state('vwlb_version'),'safe_mode'=>self::option_state('vwlb_safe_mode'),'caps'=>$caps,'cron'=>$cron);
		$created=add_option(self::ACTIVATION_SNAPSHOT_OPTION,$snapshot,'',false);
		if(!$created&&get_option(self::ACTIVATION_SNAPSHOT_OPTION,null)!==$snapshot)wp_die(esc_html__('File 10 activation rollback evidence could not be persisted.',VWLB_TEXT_DOMAIN));
		register_shutdown_function(array(__CLASS__,'activation_shutdown'));
	}

	public static function activation_commit(){
		$current=get_option(self::ACTIVATION_SNAPSHOT_OPTION,false);
		if(!is_array($current))wp_die(esc_html__('File 10 activation rollback evidence disappeared before commit.',VWLB_TEXT_DOMAIN));
		$deleted=delete_option(self::ACTIVATION_SNAPSHOT_OPTION);
		if(!$deleted&&false!==get_option(self::ACTIVATION_SNAPSHOT_OPTION,false))wp_die(esc_html__('File 10 activation rollback evidence could not be closed safely.',VWLB_TEXT_DOMAIN));
		self::$activation_committed=true;
	}

	public static function activation_shutdown(){if(self::$activation_committed)return;$snapshot=get_option(self::ACTIVATION_SNAPSHOT_OPTION,false);if(is_array($snapshot))self::rollback_activation_snapshot($snapshot);}

	private static function rollback_activation_snapshot($snapshot){
		$ok=true;
		$before_map=!empty($snapshot['page_map']['exists'])&&is_array($snapshot['page_map']['value'])?$snapshot['page_map']['value']:array();$before_ids=array_values(array_unique(array_filter(array_map('absint',$before_map))));$current_map=(array)get_option('vwlb_page_map',array());
		foreach(array_unique(array_filter(array_map('absint',$current_map))) as $page_id){if(in_array($page_id,$before_ids,true))continue;$post=get_post($page_id);if($post&&'page'===$post->post_type&&str_contains((string)$post->post_content,'[vwlb_')){if(!wp_delete_post($page_id,true))$ok=false;}}
		$ok=self::restore_option('vwlb_page_map',$snapshot['page_map']??array('exists'=>false))&&$ok;
		$ok=self::restore_option('vwlb_version',$snapshot['version']??array('exists'=>false))&&$ok;
		$ok=self::restore_option('vwlb_safe_mode',$snapshot['safe_mode']??array('exists'=>false))&&$ok;
		$role=get_role('administrator');if($role){foreach(self::caps() as $cap){$had=!empty($snapshot['caps'][$cap]);if($had&&!$role->has_cap($cap))$role->add_cap($cap);elseif(!$had&&$role->has_cap($cap))$role->remove_cap($cap);}}
		add_filter('cron_schedules',array('VWLB_Activator','cron_schedules'));
		foreach(self::cron_hooks() as $hook){$before=$snapshot['cron'][$hook]??array('exists'=>false);$now=wp_next_scheduled($hook);if(empty($before['exists'])){if($now)wp_clear_scheduled_hook($hook);continue;}if(!$now&&!empty($before['schedule'])){$scheduled=wp_schedule_event(max(time()+60,absint($before['timestamp']??0)),(string)$before['schedule'],$hook,(array)($before['args']??array()),true);if(is_wp_error($scheduled)||false===$scheduled)$ok=false;}}
		$deleted=delete_option(self::ACTIVATION_SNAPSHOT_OPTION);if(!$deleted&&false!==get_option(self::ACTIVATION_SNAPSHOT_OPTION,false))$ok=false;
		if(function_exists('do_action')){try{do_action('vwlb_operational_failure','activation','vwlb_activation_compensated',array('rollback_verified'=>$ok));}catch(Throwable $e){}}
		return $ok;
	}

	public static function register(){
		remove_filter('rest_request_after_callbacks',array('VWLB_Sequential_Review_Hardening','enforce_command_idempotency_after'),9);
		add_filter('rest_request_before_callbacks',array(__CLASS__,'external_guard_before'),8,3);
		add_filter('rest_request_after_callbacks',array(__CLASS__,'rest_db_failure_guard'),7,3);
		add_filter('rest_request_after_callbacks',array(__CLASS__,'command_idempotency_after'),8,3);
		add_filter('rest_request_after_callbacks',array(__CLASS__,'external_guard_after'),9,3);
		add_filter('rest_request_before_callbacks',array(__CLASS__,'repair_override_before'),30,3);
		add_filter('do_shortcode_tag',array(__CLASS__,'frontend_db_failure_guard'),99,4);
		add_filter('vwlb_dependency_health',array(__CLASS__,'dependency_health'));
	}

	private static function external_route($request){
		if(!$request instanceof WP_REST_Request||'POST'!==strtoupper((string)$request->get_method()))return false;$route=(string)$request->get_route();
		$patterns=array('#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/live-events$#','#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/premieres$#','#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/live-events/[^/]+/(?:credentials|kill|future-config/apply)$#','#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/live-events/[^/]+/simulcast-targets/[^/]+/transition$#','#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/media-tracks/(?:video|live)/[^/]+/generate$#');
		foreach($patterns as $pattern)if(preg_match($pattern,$route))return true;return false;
	}
	private static function actor(){return get_current_user_id()?'u'.get_current_user_id():'a'.substr(VWLB_Helpers::ip_hash(),0,32);}
	private static function request_hash($request){$query=$request->get_query_params();if(is_array($query))ksort($query);return hash('sha256',strtoupper((string)$request->get_method()).'|'.$request->get_route().'|'.VWLB_Helpers::json_encode($query).'|'.hash('sha256',(string)$request->get_body()));}
	private static function guard_key($request,$key){return self::EXTERNAL_GUARD_PREFIX.hash('sha256',$request->get_route().'|'.self::actor().'|'.$key);}

	public static function external_guard_before($response,$handler,$request){
		if(null!==$response||!self::external_route($request))return $response;$key=VWLB_Helpers::text($request->get_header('Idempotency-Key'),128);if(!$key)return $response;
		$option=self::guard_key($request,$key);$hash=self::request_hash($request);$existing=get_option($option,false);
		if(is_array($existing)){
			if(!hash_equals((string)($existing['request_hash']??''),$hash))return VWLB_Helpers::error('vwlb_external_guard_conflict',__('This idempotency key is bound to a different external-effect request.',VWLB_TEXT_DOMAIN),409,array('guard_id'=>$existing['guard_id']??''));
			if('reconcile_required'===($existing['status']??''))return VWLB_Helpers::error('vwlb_external_reconcile_required',__('A previous provider-side attempt has an unverified outcome. Reconcile it before retrying this idempotency key.',VWLB_TEXT_DOMAIN),503,array('guard_id'=>$existing['guard_id']??'','reconcile_required'=>true));
			return VWLB_Helpers::error('vwlb_external_operation_in_progress',__('An external-effect operation with this idempotency key is still in progress or its outcome is not yet verified.',VWLB_TEXT_DOMAIN),409,array('guard_id'=>$existing['guard_id']??''));
		}
		$record=array('guard_id'=>VWLB_Helpers::public_id('reconcile'),'status'=>'processing','request_hash'=>$hash,'route'=>VWLB_Helpers::text($request->get_route(),191),'actor'=>self::actor(),'created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now());
		$created=add_option($option,$record,'',false);if(!$created){$race=get_option($option,false);if(is_array($race))return VWLB_Helpers::error('vwlb_external_operation_in_progress',__('An external-effect operation with this idempotency key is already running.',VWLB_TEXT_DOMAIN),409,array('guard_id'=>$race['guard_id']??''));return VWLB_Helpers::error('vwlb_external_guard_persist_failed',__('External-effect retry protection could not be persisted safely.',VWLB_TEXT_DOMAIN),503);}
		self::$request_guards[spl_object_hash($request)]=array('option'=>$option,'record'=>$record);
		if(!self::$shutdown_registered){register_shutdown_function(array(__CLASS__,'request_shutdown'));self::$shutdown_registered=true;}
		return $response;
	}

	private static function response_status($response){if(is_wp_error($response)){ $data=$response->get_error_data();return is_array($data)?absint($data['status']??500):500;}if($response instanceof WP_REST_Response)return (int)$response->get_status();return 200;}
	private static function response_uncertain($response,$route=''){
		$unsafe=array('vwlb_provider_live_reconcile_required','vwlb_provider_ingest_reconcile_required','vwlb_provider_emergency_end_reconcile_required','vwlb_simulcast_reconcile_required','vwlb_track_generation_reconcile_required','vwlb_external_guard_release_failed','vwlb_idempotency_persist_failed');
		if(is_wp_error($response)&&in_array($response->get_error_code(),$unsafe,true))return true;$status=self::response_status($response);if($status<500)return false;
		return str_contains($route,'/premieres')||str_contains($route,'/future-config/apply')||str_contains($route,'/simulcast-targets/')||str_contains($route,'/media-tracks/');
	}
	private static function mark_reconcile($option,$record,$reason){$record['status']='reconcile_required';$record['reason']=sanitize_key($reason);$record['updated_at']=VWLB_Helpers::now();$saved=update_option($option,$record,false);if(!$saved&&get_option($option,null)!==$record){do_action('vwlb_operational_failure','external_effect','vwlb_external_guard_update_failed',array('guard_id'=>$record['guard_id']??''));return false;}do_action('vwlb_operational_failure','external_effect','vwlb_external_reconcile_required',array('guard_id'=>$record['guard_id']??'','route'=>$record['route']??'','reason'=>$record['reason']));return true;}
	private static function release_guard($option){$deleted=delete_option($option);return $deleted||false===get_option($option,false);}

	public static function external_guard_after($response,$handler,$request){
		$hash=spl_object_hash($request);if(empty(self::$request_guards[$hash]))return $response;$ctx=self::$request_guards[$hash];unset(self::$request_guards[$hash]);$route=(string)$request->get_route();
		if(self::response_uncertain($response,$route)){self::mark_reconcile($ctx['option'],$ctx['record'],is_wp_error($response)?$response->get_error_code():'http_'.self::response_status($response));if(is_wp_error($response)){$data=(array)$response->get_error_data();$data['guard_id']=$ctx['record']['guard_id'];$data['reconcile_required']=true;$response->add_data($data,$response->get_error_code());}return $response;}
		if(!self::release_guard($ctx['option'])){self::mark_reconcile($ctx['option'],$ctx['record'],'guard_release_failed');return VWLB_Helpers::error('vwlb_external_guard_release_failed',__('The operation finished but external-effect retry protection could not be released safely. Reconciliation is required before retry.',VWLB_TEXT_DOMAIN),503,array('guard_id'=>$ctx['record']['guard_id'],'reconcile_required'=>true));}
		return $response;
	}

	public static function request_shutdown(){if(!self::$request_guards)return;foreach(self::$request_guards as $ctx)self::mark_reconcile($ctx['option'],$ctx['record'],'request_terminated_before_completion');self::$request_guards=array();}

	public static function command_idempotency_after($response,$handler,$request){
		if(!$request instanceof WP_REST_Request||in_array(strtoupper((string)$request->get_method()),array('GET','HEAD','OPTIONS'),true))return $response;$callback=$handler['callback']??null;$name=is_array($callback)&&isset($callback[1])?sanitize_key((string)$callback[1]):'';$scope='';if('create_video'===$name)$scope='create_video';elseif(in_array($name,array('schedule_live','premiere_create'),true))$scope='schedule_live';if(!$scope)return $response;$key=VWLB_Helpers::text($request->get_header('Idempotency-Key'),128);if(!$key)return $response;
		global $wpdb;$actor=self::actor();$db_scope=substr(sanitize_key($scope).':'.$actor,0,100);$table=VWLB_Helpers::table('idempotency');$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare("SELECT status FROM $table WHERE idempotency_key=%s AND scope=%s LIMIT 1",$key,$db_scope),ARRAY_A);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_idempotency_state_unreadable',__('Command replay state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
		$failed=is_wp_error($response)||self::response_status($response)>=500;if($failed){if(self::response_uncertain($response,(string)$request->get_route()))return $response;if($row&&'processing'===($row['status']??'')){$aborted=VWLB_Security::idempotency_abort($key,$scope);if(is_wp_error($aborted))return $aborted;}return $response;}
		if(!$row||'complete'!==($row['status']??''))return VWLB_Helpers::error('vwlb_idempotency_persist_failed',__('The operation completed but its command replay state could not be verified safely.',VWLB_TEXT_DOMAIN),503);
		return $response;
	}

	public static function rest_db_failure_guard($response,$handler,$request){
		if(is_wp_error($response)||!$request instanceof WP_REST_Request)return $response;$route=(string)$request->get_route();$data=$response instanceof WP_REST_Response?$response->get_data():$response;if(is_array($data)&&!empty($data['error_code'])&&'vwlb_video_browse_read_failed'===$data['error_code'])return VWLB_Helpers::error('vwlb_video_browse_read_failed',__('The public video catalogue could not be read safely.',VWLB_TEXT_DOMAIN),503);
		if(''!==(string)($GLOBALS['wpdb']->last_error??'')&&(preg_match('#/(?:video-wall-live-broadcasting/v1|vwlb/v1)/(?:videos|live-events)$#',$route)||str_ends_with($route,'/repair')))return VWLB_Helpers::error('vwlb_database_read_failed',__('File 10 could not verify the requested database state safely.',VWLB_TEXT_DOMAIN),503);
		return $response;
	}

	public static function frontend_db_failure_guard($output,$tag,$attr,$m){if(!in_array($tag,array('vwlb_wall','vwlb_channel','vwlb_podcasts'),true))return $output;if(''===(string)($GLOBALS['wpdb']->last_error??''))return $output;do_action('vwlb_operational_failure','frontend','vwlb_public_surface_read_failed',array('surface'=>$tag));return '<section class="vwlb-empty" role="alert"><h2>'.esc_html__('Video service temporarily unavailable',VWLB_TEXT_DOMAIN).'</h2><p>'.esc_html__('This public media view could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN).'</p></section>';}

	public static function repair_override_before($response,$handler,$request){if(null!==$response||!$request instanceof WP_REST_Request||'POST'!==strtoupper((string)$request->get_method())||!str_ends_with((string)$request->get_route(),'/repair'))return $response;$data=$request->get_json_params();$data=is_array($data)?$data:array();$action=sanitize_key((string)($data['action']??''));if('recount_interactions'===$action)return self::rest_response(self::safe_recount($data));if('resolve_external_guard'===$action)return self::rest_response(self::resolve_external_guard($data));return $response;}
	private static function rest_response($value){if(is_wp_error($value))return $value;$r=rest_ensure_response($value);$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);return $r;}

	private static function safe_recount($data){
		if(!VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS,null,'repair'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot run repairs.',VWLB_TEXT_DOMAIN),403);$step=VWLB_Security::require_step_up('repair_recount_interactions');if(is_wp_error($step))return $step;$preflight=VWLB_Diagnostics::full();if(empty($preflight['database_verified']))return VWLB_Helpers::error('vwlb_repair_preflight_unverified',__('Repair was blocked because the database preflight could not be verified safely.',VWLB_TEXT_DOMAIN),503,array('checks'=>(array)($preflight['database_errors']??array())));$snapshot=VWLB_DB::snapshot('repair_before',$preflight);if(is_wp_error($snapshot))return $snapshot;$batch=max(1,min(500,absint($data['batch_size']??100)));$after=absint($data['after_id']??0);
		$result=VWLB_DB::transaction(function()use($batch,$after){global $wpdb;$wpdb->last_error='';$ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE id>%d ORDER BY id ASC LIMIT %d',$after,$batch+1));if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_repair_database_failed',__('Video recount batch could not be read safely.',VWLB_TEXT_DOMAIN),503);$ids=is_array($ids)?$ids:array();$more=count($ids)>$batch;if($more)array_pop($ids);foreach($ids as $id){foreach(array('like','dislike') as $type){$wpdb->last_error='';$count=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('interactions').' WHERE video_id=%d AND interaction=%s',$id,$type));if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_repair_database_failed',__('Interaction counters could not be read safely.',VWLB_TEXT_DOMAIN),503);$changed=$wpdb->update(VWLB_Helpers::table('videos'),array($type.'_count'=>(int)$count),array('id'=>$id));if(false===$changed)return VWLB_Helpers::error('vwlb_repair_database_failed',__('Interaction counters could not be reconciled.',VWLB_TEXT_DOMAIN),500);}}$last=$ids?(int)end($ids):$after;return array('processed'=>count($ids),'next_after_id'=>$more?$last:0,'remaining'=>$more?'more':'none','completed'=>!$more);});
		if(is_wp_error($result))return $result;try{VWLB_Helpers::audit('system',0,'repair','','','recount_interactions',array('purpose'=>'operational_repair','completed'=>!empty($result['completed']),'details'=>$result));}catch(Throwable $e){return VWLB_Helpers::error('vwlb_repair_audit_failed',__('Repair completed locally but its audit evidence could not be persisted safely.',VWLB_TEXT_DOMAIN),503);}$health=VWLB_Diagnostics::full();if(empty($health['database_verified']))return VWLB_Helpers::error('vwlb_repair_postcheck_unverified',__('Repair changed data but the database post-check could not be verified safely.',VWLB_TEXT_DOMAIN),503,array('checks'=>(array)($health['database_errors']??array())));return array('action'=>'recount_interactions','completed'=>!empty($result['completed']),'details'=>$result,'health'=>$health);
	}

	private static function resolve_external_guard($data){
		if(!VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS,null,'repair'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot resolve provider reconciliation guards.',VWLB_TEXT_DOMAIN),403);$step=VWLB_Security::require_step_up('repair_resolve_external_guard');if(is_wp_error($step))return $step;if(empty($data['provider_reconciled']))return VWLB_Helpers::error('vwlb_reconciliation_confirmation_required',__('Explicit provider reconciliation confirmation is required before releasing this retry guard.',VWLB_TEXT_DOMAIN),422);$guard_id=VWLB_Helpers::text($data['guard_id']??'',80);if(!$guard_id)return VWLB_Helpers::error('vwlb_guard_id_required',__('A reconciliation guard identifier is required.',VWLB_TEXT_DOMAIN),422);global $wpdb;$like=$wpdb->esc_like(self::EXTERNAL_GUARD_PREFIX).'%';$after=0;
		for($page=0;$page<10;$page++){$wpdb->last_error='';$rows=$wpdb->get_results($wpdb->prepare("SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id ASC LIMIT 100",$like,$after),ARRAY_A);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_external_guard_read_failed',__('External reconciliation guards could not be enumerated safely.',VWLB_TEXT_DOMAIN),503);$rows=is_array($rows)?$rows:array();if(!$rows)break;foreach($rows as $row){$after=max($after,absint($row['option_id']??0));$record=maybe_unserialize($row['option_value']);if(is_array($record)&&hash_equals((string)($record['guard_id']??''),$guard_id)){$deleted=delete_option($row['option_name']);if(!$deleted&&false!==get_option($row['option_name'],false))return VWLB_Helpers::error('vwlb_external_guard_release_failed',__('The reconciliation guard could not be released safely.',VWLB_TEXT_DOMAIN),503);VWLB_Helpers::audit('system',0,'external_guard_resolved','','','Provider reconciliation confirmed before retry guard release.',array('guard_id'=>$guard_id,'route'=>$record['route']??''));return array('action'=>'resolve_external_guard','completed'=>true,'guard_id'=>$guard_id);}}if(count($rows)<100)break;}
		return VWLB_Helpers::error('vwlb_external_guard_missing',__('The requested reconciliation guard was not found in the bounded guard registry.',VWLB_TEXT_DOMAIN),404);
	}

	public static function dependency_health($health){$health=is_array($health)?$health:array();global $wpdb;$like=$wpdb->esc_like(self::EXTERNAL_GUARD_PREFIX).'%';$wpdb->last_error='';$count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",$like));$health['file10_external_effect_guards']=array('status'=>''!==(string)$wpdb->last_error?'unknown':((int)$count>0?'degraded':'ok'),'pending'=>''!==(string)$wpdb->last_error?null:(int)$count);return $health;}
}
