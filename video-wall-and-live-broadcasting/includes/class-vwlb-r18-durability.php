<?php
/** R18 sequential review: verify inbound event retry evidence before processing can proceed. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R18_Durability {
	public static function register(){
		add_action('sabri_event_bus_consume_file10',array(__CLASS__,'verify_retry_capture'),2,3);
		add_action('sabri_event_bus_consume_file10',array(__CLASS__,'verify_retry_release'),101,3);
	}
	private static function key($event_id){return VWLB_Review_Hardening::INBOX_RETRY_PREFIX.hash('sha256',VWLB_Helpers::text($event_id,100));}
	public static function verify_retry_capture($event_id,$event_name,$payload){
		$event_id=VWLB_Helpers::text($event_id,100);if(!$event_id)return;
		$key=self::key($event_id);$hash=hash('sha256',VWLB_Helpers::json_encode($payload));$record=get_option($key,null);
		if(is_array($record)&&($record['event_id']??'')===$event_id&&($record['event_name']??'')===VWLB_Helpers::text($event_name,100)&&hash_equals((string)($record['payload_hash']??''),$hash))return;
		$record=array('event_id'=>$event_id,'event_name'=>VWLB_Helpers::text($event_name,100),'payload'=>(array)$payload,'payload_hash'=>$hash,'captured_at'=>VWLB_Helpers::now());
		$saved=update_option($key,$record,false);if(!$saved&&get_option($key,null)!==$record){do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_persist_failed',array('event_id_hash'=>hash('sha256',$event_id)));throw new RuntimeException('VWLB inbound retry evidence could not be persisted.');}
	}
	public static function verify_retry_release($event_id,$event_name,$payload){
		global $wpdb;$event_id=VWLB_Helpers::text($event_id,100);if(!$event_id)return;$status=$wpdb->get_var($wpdb->prepare('SELECT status FROM '.VWLB_Helpers::table('inbox').' WHERE event_id=%s LIMIT 1',$event_id));if('processed'!==$status)return;$key=self::key($event_id);if(false===get_option($key,false))return;$deleted=delete_option($key);if(!$deleted&&false!==get_option($key,false))do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_release_failed',array('event_id_hash'=>hash('sha256',$event_id)));
	}
}
