<?php
/** R20 sequential review: encrypted, expiring and erasable inbound retry evidence. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R20_Retry_Privacy {
	const TTL = DAY_IN_SECONDS;
	const AAD = 'vwlb-inbox-retry-v1';
	const ERASURE_CURSOR_PREFIX = 'vwlb_retry_erasure_cursor_';

	public static function register(){
		// Replace the earlier plain-payload retry fallback while preserving the same operational hook contract.
		remove_action('sabri_event_bus_consume_file10',array('VWLB_Review_Hardening','capture_inbox_retry'),1);
		remove_action('sabri_event_bus_consume_file10',array('VWLB_Review_Hardening','release_processed_inbox_retry'),100);
		remove_action('vwlb_reconcile_states',array('VWLB_Review_Hardening','reconcile_inbox_retries'),70);
		add_action('sabri_event_bus_consume_file10',array(__CLASS__,'capture'),1,3);
		add_action('sabri_event_bus_consume_file10',array(__CLASS__,'release'),100,3);
		add_action('vwlb_reconcile_states',array(__CLASS__,'reconcile'),70);
		add_action('vwlb_cleanup',array(__CLASS__,'cleanup'),55);
		add_filter('wp_privacy_personal_data_erasers',array(__CLASS__,'erasers'),40);
	}
	private static function option_key($event_id){return VWLB_Review_Hardening::INBOX_RETRY_PREFIX.hash('sha256',VWLB_Helpers::text($event_id,100));}
	private static function crypt_key(){return hash('sha256',wp_salt('auth').'|'.wp_salt('secure_auth').'|File10RetryEvidence',true);}
	private static function key_id(){return substr(hash('sha256',self::crypt_key()),0,16);}
	private static function encrypt_payload($payload){
		if(!function_exists('openssl_encrypt'))return VWLB_Helpers::error('vwlb_retry_crypto_unavailable',__('Secure retry storage is unavailable.',VWLB_TEXT_DOMAIN),503);
		try{$iv=random_bytes(12);}catch(Throwable $e){return VWLB_Helpers::error('vwlb_retry_crypto_unavailable',__('Secure retry nonce generation failed.',VWLB_TEXT_DOMAIN),503);}
		$tag='';$plain=VWLB_Helpers::json_encode($payload);$cipher=openssl_encrypt($plain,'aes-256-gcm',self::crypt_key(),OPENSSL_RAW_DATA,$iv,$tag,self::AAD,16);
		if(false===$cipher||16!==strlen($tag))return VWLB_Helpers::error('vwlb_retry_encrypt_failed',__('Inbound retry evidence could not be encrypted.',VWLB_TEXT_DOMAIN),503);
		return array('alg'=>'aes-256-gcm','key_id'=>self::key_id(),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'ciphertext'=>base64_encode($cipher));
	}
	private static function decrypt_payload($record){
		if(!is_array($record)||empty($record['payload_cipher'])||!is_array($record['payload_cipher']))return VWLB_Helpers::error('vwlb_retry_payload_missing',__('Encrypted retry payload is missing.',VWLB_TEXT_DOMAIN),503);
		$c=$record['payload_cipher'];if(($c['alg']??'')!=='aes-256-gcm'||($c['key_id']??'')!==self::key_id()||!function_exists('openssl_decrypt'))return VWLB_Helpers::error('vwlb_retry_key_unavailable',__('Inbound retry evidence cannot be decrypted with the active key.',VWLB_TEXT_DOMAIN),503);
		$iv=base64_decode((string)($c['iv']??''),true);$tag=base64_decode((string)($c['tag']??''),true);$cipher=base64_decode((string)($c['ciphertext']??''),true);if(false===$iv||false===$tag||false===$cipher)return VWLB_Helpers::error('vwlb_retry_cipher_invalid',__('Inbound retry evidence encoding is invalid.',VWLB_TEXT_DOMAIN),503);
		$plain=openssl_decrypt($cipher,'aes-256-gcm',self::crypt_key(),OPENSSL_RAW_DATA,$iv,$tag,self::AAD);if(false===$plain)return VWLB_Helpers::error('vwlb_retry_decrypt_failed',__('Inbound retry evidence failed authentication.',VWLB_TEXT_DOMAIN),503);
		$payload=json_decode($plain,true);if(!is_array($payload))return VWLB_Helpers::error('vwlb_retry_payload_invalid',__('Inbound retry payload is invalid.',VWLB_TEXT_DOMAIN),503);return $payload;
	}
	private static function records($after_id=0,$limit=100){
		global $wpdb;$like=$wpdb->esc_like(VWLB_Review_Hardening::INBOX_RETRY_PREFIX).'%';return $wpdb->get_results($wpdb->prepare("SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id ASC LIMIT %d",$like,max(0,(int)$after_id),max(1,min(500,(int)$limit))),ARRAY_A);
	}
	public static function capture($event_id,$event_name,$payload){
		$event_id=VWLB_Helpers::text($event_id,100);if(!$event_id)return;$cipher=self::encrypt_payload($payload);if(is_wp_error($cipher)){do_action('vwlb_operational_failure','inbox',$cipher->get_error_code(),array('event_id_hash'=>hash('sha256',$event_id)));throw new RuntimeException('VWLB secure inbound retry capture failed.');}
		$record=array('event_id'=>$event_id,'event_name'=>VWLB_Helpers::text($event_name,100),'payload_hash'=>hash('sha256',VWLB_Helpers::json_encode($payload)),'payload_cipher'=>$cipher,'captured_at'=>VWLB_Helpers::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+self::TTL));$key=self::option_key($event_id);
		$saved=update_option($key,$record,false);if(!$saved&&get_option($key,null)!==$record){do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_persist_failed',array('event_id_hash'=>hash('sha256',$event_id)));throw new RuntimeException('VWLB secure inbound retry evidence could not be persisted.');}
	}
	public static function release($event_id,$event_name,$payload){
		global $wpdb;$event_id=VWLB_Helpers::text($event_id,100);if(!$event_id)return;$status=$wpdb->get_var($wpdb->prepare('SELECT status FROM '.VWLB_Helpers::table('inbox').' WHERE event_id=%s LIMIT 1',$event_id));if('processed'!==$status)return;$key=self::option_key($event_id);if(false===get_option($key,false))return;$deleted=delete_option($key);if(!$deleted&&false!==get_option($key,false))do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_release_failed',array('event_id_hash'=>hash('sha256',$event_id)));
	}
	public static function reconcile(){
		global $wpdb;$table=VWLB_Helpers::table('inbox');foreach(self::records(0,25) as $option){$record=maybe_unserialize($option['option_value']);if(!is_array($record)||empty($record['event_id'])){delete_option($option['option_name']);continue;}if(!empty($record['expires_at'])&&strtotime($record['expires_at'].' UTC')<=time()){delete_option($option['option_name']);do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_expired',array('event_id_hash'=>hash('sha256',(string)$record['event_id'])));continue;}$payload=self::decrypt_payload($record);if(is_wp_error($payload)){do_action('vwlb_operational_failure','inbox',$payload->get_error_code(),array('event_id_hash'=>hash('sha256',(string)$record['event_id'])));continue;}if(!hash_equals((string)($record['payload_hash']??''),hash('sha256',VWLB_Helpers::json_encode($payload)))){do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_cipher_hash_conflict',array('event_id_hash'=>hash('sha256',(string)$record['event_id'])));continue;}$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_id=%s LIMIT 1",$record['event_id']),ARRAY_A);if($row&&'processed'===$row['status']){delete_option($option['option_name']);continue;}if($row&&'processing'===$row['status']&&strtotime($row['received_at'].' UTC')>time()-15*MINUTE_IN_SECONDS)continue;if($row&&!hash_equals((string)$row['payload_hash'],(string)$record['payload_hash'])){do_action('vwlb_operational_failure','inbox','vwlb_inbox_retry_hash_conflict',array('event_id_hash'=>hash('sha256',(string)$record['event_id'])));continue;}if($row){$deleted=$wpdb->delete($table,array('id'=>$row['id']),array('%d'));if(1!==$deleted)continue;}$consumer=new VWLB_Integrations();$result=$consumer->consume($record['event_id'],$record['event_name'],$payload);if(!is_wp_error($result)&&!empty($result['processed']))delete_option($option['option_name']);}
	}
	public static function cleanup(){foreach(self::records(0,200) as $option){$record=maybe_unserialize($option['option_value']);if(!is_array($record)||empty($record['expires_at'])||strtotime($record['expires_at'].' UTC')<=time()){$deleted=delete_option($option['option_name']);if(!$deleted&&false!==get_option($option['option_name'],false))do_action('vwlb_operational_failure','cleanup','vwlb_retry_cleanup_failed',array('option_hash'=>hash('sha256',$option['option_name'])));}}}
	private static function payload_mentions_user($payload,$uid){foreach((array)$payload as $key=>$value){$key=sanitize_key((string)$key);if(is_array($value)){if(self::payload_mentions_user($value,$uid))return true;continue;}if(!in_array($key,array('user_id','member_id','actor_id','owner_id','reporter_id','claimant_id','subject_user_id','blocked_user_id','created_by','updated_by'),true))continue;if((string)$value===(string)$uid)return true;}return false;}
	public static function erasers($erasers){$erasers['vwlb-retry-evidence']=array('eraser_friendly_name'=>__('Video Wall secure retry evidence',VWLB_TEXT_DOMAIN),'callback'=>array(__CLASS__,'erase_retry_evidence'));return $erasers;}
	public static function erase_retry_evidence($email,$page=1){
		$user=get_user_by('email',$email);if(!$user)return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);$uid=(int)$user->ID;$cursor_key=self::ERASURE_CURSOR_PREFIX.hash('sha256',(string)$uid);$cursor=max(0,(int)get_option($cursor_key,0));$rows=self::records($cursor,200);$removed=false;$last=$cursor;
		foreach($rows as $option){$last=max($last,(int)$option['option_id']);$record=maybe_unserialize($option['option_value']);if(!is_array($record))continue;$payload=self::decrypt_payload($record);if(is_wp_error($payload))continue;if(self::payload_mentions_user($payload,$uid)){$deleted=delete_option($option['option_name']);if(!$deleted&&false!==get_option($option['option_name'],false))return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>array(__('Secure retry evidence could not be erased safely.',VWLB_TEXT_DOMAIN)),'done'=>false);$removed=true;}}
		$done=count($rows)<200;if($done){delete_option($cursor_key);}else{$saved=update_option($cursor_key,$last,false);if(!$saved&&(int)get_option($cursor_key,0)!==$last)return array('items_removed'=>$removed,'items_retained'=>true,'messages'=>array(__('Retry-evidence erasure cursor could not be saved safely.',VWLB_TEXT_DOMAIN)),'done'=>false);}return array('items_removed'=>$removed,'items_retained'=>!$done,'messages'=>array(),'done'=>$done);
	}
}
