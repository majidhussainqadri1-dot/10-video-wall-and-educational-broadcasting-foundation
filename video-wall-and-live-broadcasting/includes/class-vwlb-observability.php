<?php
/** Privacy-safe observability, provider circuit state and operational readiness. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Observability {
	const METRICS_OPTION='vwlb_operational_metrics';
	public static function register(){
		add_action('vwlb_domain_event',array(__CLASS__,'event_seen'),10,3);
		add_action('vwlb_operational_failure',array(__CLASS__,'operational_failure'),10,3);
		add_filter('vwlb_provider_process_asset',array(__CLASS__,'provider_failure_passthrough'),100,4);
	}
	private static function persist_metrics($metrics){
		$metrics=array_slice((array)$metrics,-31,31,true);$saved=update_option(self::METRICS_OPTION,$metrics,false);
		if(!$saved&&get_option(self::METRICS_OPTION,array())!==$metrics){error_log('VWLB operational metrics persistence failed.');return false;}return true;
	}
	public static function event_seen($event_name,$payload,$event){
		$metrics=(array)get_option(self::METRICS_OPTION,array());$day=gmdate('Y-m-d');if(!isset($metrics[$day]))$metrics[$day]=array('events'=>0,'errors'=>0,'jobs_dead'=>0,'last_event'=>'','last_error_code'=>'');
		$metrics[$day]['events']++;$metrics[$day]['last_event']=VWLB_Helpers::text($event_name,100);self::persist_metrics($metrics);
	}
	public static function operational_failure($area,$error_code,$context=array()){
		$metrics=(array)get_option(self::METRICS_OPTION,array());$day=gmdate('Y-m-d');if(!isset($metrics[$day]))$metrics[$day]=array('events'=>0,'errors'=>0,'jobs_dead'=>0,'last_event'=>'','last_error_code'=>'');
		$metrics[$day]['errors']++;$metrics[$day]['last_error_code']=VWLB_Helpers::text($error_code,128);$metrics[$day]['last_event']='failure:'.sanitize_key($area);self::persist_metrics($metrics);
	}
	public static function provider_failure_passthrough($result,$provider_id,$asset,$job){if(is_wp_error($result))self::record_provider($provider_id,'processing','degraded',$result->get_error_code(),0);return $result;}
	public static function record_provider($provider,$capability,$state,$error_code='',$latency_ms=0){
		global $wpdb;$table=VWLB_Helpers::table('provider_health');$provider=sanitize_key($provider);$capability=sanitize_key($capability);$state=VWLB_Helpers::enum($state,array('healthy','degraded','down','unknown'),'unknown');$now=VWLB_Helpers::now();
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE provider=%s AND capability=%s",$provider,$capability),ARRAY_A);$failures=in_array($state,array('degraded','down'),true)?((int)($row['failures']??0)+1):0;$open=$failures>=5?gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,$failures*60)):null;
		$saved=$wpdb->replace($table,array('id'=>(int)($row['id']??0),'provider'=>$provider,'capability'=>$capability,'state'=>$state,'failures'=>$failures,'last_latency_ms'=>max(0,min(600000,(int)$latency_ms)),'circuit_open_until'=>$open,'last_error_code'=>VWLB_Helpers::text($error_code,128),'checked_at'=>$now,'updated_at'=>$now));
		if(false===$saved)do_action('vwlb_operational_failure','provider_health','vwlb_provider_health_persist_failed',array('provider'=>$provider,'capability'=>$capability));return false!==$saved;
	}
	public static function provider_available($provider,$capability){global $wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('provider_health').' WHERE provider=%s AND capability=%s',sanitize_key($provider),sanitize_key($capability)),ARRAY_A);if(!$row)return true;if('down'===$row['state'])return false;if($row['circuit_open_until']&&strtotime($row['circuit_open_until'].' UTC')>time())return false;return true;}
	public static function snapshot(){
		global $wpdb;$jobs=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('processing_jobs')." WHERE status='dead'");$outbox=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".VWLB_Helpers::table('outbox')." WHERE status='dead'");
		$providers=$wpdb->get_results('SELECT provider,capability,state,failures,last_latency_ms,circuit_open_until,last_error_code,checked_at FROM '.VWLB_Helpers::table('provider_health').' ORDER BY provider,capability LIMIT 200',ARRAY_A);
		return array('version'=>VWLB_VERSION,'schema'=>VWLB_SCHEMA_VERSION,'dead_jobs'=>$jobs,'dead_outbox'=>$outbox,'providers'=>array_values((array)$providers),'provider_list_truncated'=>count((array)$providers)>=200,'extension'=>VWLB_Extensions::status(),'slo_targets'=>array('availability'=>'defined-per-provider','api_p95_ms'=>(int)apply_filters('vwlb_slo_api_p95_ms',750),'initial_rpo_hours'=>24,'initial_rto_hours'=>8),'evidence_boundary'=>'runtime measurements require staging/production traffic');
	}
}
