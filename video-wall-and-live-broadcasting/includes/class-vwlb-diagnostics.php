<?php
/** Health, safe mode, operability and reversible repair. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Diagnostics {
	public static function public_health(){
		$providers=array();foreach(VWLB_Providers::all() as $p){$providers[]=array('id'=>$p->id(),'capabilities'=>$p->capabilities(),'available'=>VWLB_Observability::provider_available($p->id(),'general'));}
		return array('module'=>'File 10','status'=>get_option('vwlb_safe_mode')?'degraded':'ok','version'=>VWLB_VERSION,'schema'=>get_option('vwlb_schema_version','missing'),'extension_schema'=>get_option(VWLB_Extensions::OPTION,'missing'),'canonical_api'=>VWLB_Contracts::CANONICAL_API_NAMESPACE,'providers'=>$providers);
	}
	public static function full(){
		global $wpdb;$tables=array();$names=array(
			'channels','channel_members','playlists','playlist_items','media_assets','processing_jobs','videos','captions','live_events','stream_credentials',
			'playback_sessions','interactions','moderation','takedowns','audit','outbox','inbox','webhooks','idempotency','rate_limits','rollback_snapshots',
			'upload_sessions','chapters','podcast_series','podcast_episodes','live_attendees','live_questions','live_resources','download_tokens','creator_metrics_daily','provider_health','premieres'
		);
		foreach($names as $name){$table=VWLB_Helpers::table($name);$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));$tables[$name]=array('exists'=>(bool)$exists,'rows'=>$exists?(int)$wpdb->get_var("SELECT COUNT(*) FROM $table"):null);}
		$dead=!empty($tables['processing_jobs']['exists'])?(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('processing_jobs')." WHERE status='dead'"):0;
		$outbox_dead=!empty($tables['outbox']['exists'])?(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('outbox')." WHERE status='dead'"):0;
		$required_missing=array();foreach($tables as $name=>$row)if(!$row['exists'])$required_missing[]=$name;
		return array_merge(self::public_health(),array(
			'environment'=>array('wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'multisite'=>is_multisite(),'timezone'=>wp_timezone_string(),'wp_ok'=>version_compare(get_bloginfo('version'),'7.0','>='),'php_ok'=>version_compare(PHP_VERSION,'8.3','>=')),
			'tables'=>$tables,'missing_tables'=>$required_missing,
			'queues'=>array('processing_dead'=>$dead,'outbox_dead'=>$outbox_dead),
			'cron'=>array('jobs'=>wp_next_scheduled('vwlb_process_jobs'),'outbox'=>wp_next_scheduled('vwlb_publish_outbox'),'reconcile'=>wp_next_scheduled('vwlb_reconcile_states'),'cleanup'=>wp_next_scheduled('vwlb_cleanup')),
			'dependencies'=>apply_filters('vwlb_dependency_health',array()),
			'extensions'=>VWLB_Extensions::status(),'observability'=>VWLB_Observability::snapshot(),
			'requirements'=>VWLB_Contracts::REQUIREMENTS,'central_trace'=>VWLB_Contracts::CENTRAL_TRACE,
			'no_production_claim'=>'This report is runtime diagnostics only; staging/live/operational acceptance requires separate evidence.'
		));
	}
	public static function repair($data){
		if(!VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS,null,'repair'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot run repairs.',VWLB_TEXT_DOMAIN),403);
		$action=VWLB_Helpers::enum($data['action']??'',array('install_schema','install_extension_schema','reschedule_cron','retry_dead_jobs','retry_dead_outbox','enable_safe_mode','disable_safe_mode','recount_interactions','expire_ephemeral','reset_provider_circuit'),'');
		if(!$action)return VWLB_Helpers::error('vwlb_repair_invalid',__('Unknown repair action.',VWLB_TEXT_DOMAIN));
		$step=VWLB_Security::require_step_up('repair_'.$action);if(is_wp_error($step))return $step;
		VWLB_DB::snapshot('repair_before',self::full());global $wpdb;
		switch($action){
			case'install_schema':VWLB_DB::install_schema();VWLB_Extensions::install_schema();break;
			case'install_extension_schema':VWLB_Extensions::install_schema();break;
			case'reschedule_cron':VWLB_Activator::schedules();break;
			case'retry_dead_jobs':$wpdb->query("UPDATE ".VWLB_Helpers::table('processing_jobs')." SET status='retry',available_at=UTC_TIMESTAMP(),locked_at=NULL,locked_by=NULL WHERE status='dead'");break;
			case'retry_dead_outbox':$wpdb->query("UPDATE ".VWLB_Helpers::table('outbox')." SET status='retry',available_at=UTC_TIMESTAMP(),locked_at=NULL WHERE status='dead'");break;
			case'enable_safe_mode':update_option('vwlb_safe_mode',1,false);break;
			case'disable_safe_mode':update_option('vwlb_safe_mode',0,false);break;
			case'expire_ephemeral':VWLB_Jobs::cleanup();VWLB_Extensions::cleanup();break;
			case'reset_provider_circuit':$provider=sanitize_key($data['provider']??'');if(!$provider)return VWLB_Helpers::error('vwlb_provider_required',__('Provider is required.',VWLB_TEXT_DOMAIN),422);$wpdb->update(VWLB_Helpers::table('provider_health'),array('state'=>'unknown','failures'=>0,'circuit_open_until'=>null,'last_error_code'=>'','updated_at'=>VWLB_Helpers::now()),array('provider'=>$provider));break;
			case'recount_interactions':$videos=$wpdb->get_col('SELECT id FROM '.VWLB_Helpers::table('videos'));foreach($videos as $id){foreach(array('like','dislike') as $type){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('interactions').' WHERE video_id=%d AND interaction=%s',$id,$type));$wpdb->update(VWLB_Helpers::table('videos'),array($type.'_count'=>$count),array('id'=>$id));}}break;
		}
		VWLB_Helpers::audit('system',0,'repair','','',$action,array('purpose'=>'operational_repair'));return array('action'=>$action,'completed'=>true,'health'=>self::full());
	}
}
