<?php
/** R31: provider webhook dedupe is content-bound; event-ID collisions with changed payloads fail closed. */
defined('ABSPATH') || exit;
final class VWLB_R31_Webhook_Integrity {
	public static function register(){add_action('rest_api_init',array(__CLASS__,'routes'),40);}
	public static function routes(){foreach(VWLB_Contracts::namespaces() as $n)register_rest_route($n,'/webhooks/(?P<provider>[a-z0-9_-]+)',array('methods'=>'POST','callback'=>array(__CLASS__,'webhook'),'permission_callback'=>'__return_true'),true);}
	private static function response($value,$status=200){if(is_wp_error($value))return $value;$r=rest_ensure_response($value);$r->set_status($status);$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);return $r;}
	private static function header_value($headers,$name){foreach((array)$headers as $k=>$v)if(strtolower((string)$k)===strtolower($name))return is_array($v)?(string)reset($v):(string)$v;return '';}
	public static function webhook(WP_REST_Request $r){
		$provider=VWLB_Providers::get($r['provider']);if(!$provider)return VWLB_Helpers::error('vwlb_provider_missing',__('Provider not found.',VWLB_TEXT_DOMAIN),404);
		$body=(string)$r->get_body();if(strlen($body)>1048576)return VWLB_Helpers::error('vwlb_webhook_too_large',__('Webhook payload is too large.',VWLB_TEXT_DOMAIN),413);$headers=$r->get_headers();if(!$provider->verify_webhook($headers,$body))return VWLB_Helpers::error('vwlb_webhook_signature_invalid',__('Webhook signature verification failed.',VWLB_TEXT_DOMAIN),401);
		$data=json_decode($body,true);if(!is_array($data))return VWLB_Helpers::error('vwlb_webhook_invalid',__('Webhook payload is invalid.',VWLB_TEXT_DOMAIN),400);
		if('custom'===$provider->id()){$timestamp=self::header_value($headers,'x-vwlb-timestamp');if(!$timestamp||!ctype_digit((string)$timestamp)||abs(time()-(int)$timestamp)>300)return VWLB_Helpers::error('vwlb_webhook_replay_window',__('Webhook timestamp is missing or outside the replay window.',VWLB_TEXT_DOMAIN),401);}
		$event_id=VWLB_Helpers::text($data['id']??hash('sha256',$body),191);$event_type=VWLB_Helpers::text($data['type']??'unknown',100);$payload_hash=hash('sha256',$body);global $wpdb;$table=VWLB_Helpers::table('webhooks');
		$inserted=$wpdb->insert($table,array('public_id'=>VWLB_Helpers::public_id('wh'),'provider'=>$provider->id(),'event_id'=>$event_id,'event_type'=>$event_type,'signature_hash'=>hash('sha256',VWLB_Helpers::json_encode($headers)),'payload_hash'=>$payload_hash,'payload_json'=>VWLB_Helpers::json_encode(apply_filters('vwlb_webhook_audit_payload',array('id'=>$event_id,'type'=>$event_type),$data,$provider->id())),'status'=>'received','received_at'=>VWLB_Helpers::now()));
		if(false===$inserted){$existing=$wpdb->get_row($wpdb->prepare("SELECT id,event_type,payload_hash FROM $table WHERE provider=%s AND event_id=%s LIMIT 1",$provider->id(),$event_id),ARRAY_A);if($existing){if(!hash_equals((string)$existing['payload_hash'],$payload_hash)||(string)$existing['event_type']!==$event_type){do_action('vwlb_operational_failure','webhook','vwlb_webhook_event_conflict',array('provider'=>$provider->id(),'event_id_hash'=>hash('sha256',$event_id)));return VWLB_Helpers::error('vwlb_webhook_event_conflict',__('The same provider event identifier was received with different content.',VWLB_TEXT_DOMAIN),409);}return self::response(array('accepted'=>true,'duplicate'=>true));}return VWLB_Helpers::error('vwlb_webhook_persist_failed',__('Verified webhook could not be persisted for durable processing.',VWLB_TEXT_DOMAIN),503);}
		$webhook_id=(int)$wpdb->insert_id;do_action('vwlb_verified_webhook',$provider->id(),$data,$webhook_id);return self::response(array('accepted'=>true),202);
	}
}
