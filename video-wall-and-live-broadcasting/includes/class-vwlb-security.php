<?php
/** Native authorization, File 00 claim boundary, rate limits and step-up controls. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Security {
	public static function claims() {
		$user_id=get_current_user_id();
		$claims=array('user_id'=>$user_id,'authenticated'=>$user_id>0,'suspended'=>false,'verified_doctor'=>false,'founder'=>false,'identity_approved'=>false,'age_ok'=>false,'guardian_ok'=>false,'entitlements'=>array(),'claims_version'=>'missing','source'=>'none');
		$external=apply_filters('vwlb_identity_claims',null,$user_id,array('contract'=>'File00IdentityClaims.v1','consumer'=>'File 10'));
		if(is_array($external)){
			$claims=array_merge($claims,$external);$claims['user_id']=$user_id;$claims['authenticated']=$user_id>0;
			$claims['suspended']=!empty($claims['suspended']);$claims['founder']=!empty($claims['founder']);$claims['verified_doctor']=!empty($claims['verified_doctor']);
			$claims['identity_approved']=!empty($claims['identity_approved']);$claims['age_ok']=!empty($claims['age_ok']);$claims['guardian_ok']=!empty($claims['guardian_ok']);
			$claims['entitlements']=array_values(array_unique(array_map('sanitize_key',(array)($claims['entitlements']??array()))));
			$claims['source']=VWLB_Helpers::text($claims['source']??'File 00',64);$claims['claims_version']=VWLB_Helpers::text($claims['claims_version']??'v1',32);
		}
		// WordPress administrator status is never an institutional-Founder claim and never substitutes for File 00 assurance.
		$claims['wp_admin']=($user_id>0&&user_can($user_id,'manage_options'));
		return $claims;
	}
	private static function claims_ready($claims){return !empty($claims['authenticated'])&&!empty($claims['identity_approved'])&&!empty($claims['age_ok'])&&!empty($claims['guardian_ok'])&&empty($claims['suspended']);}
	public static function can($capability,$object=null,$purpose='platform_operation'){
		$claims=self::claims();if(!self::claims_ready($claims))return false;
		if(get_option('vwlb_safe_mode')&&!in_array($purpose,array('repair','emergency_end','moderation_decision','diagnostics'),true))return false;
		$allowed=current_user_can($capability);
		if($object&&!current_user_can(VWLB_Contracts::CAP_MANAGE)){
			$owner=isset($object['owner_id'])?absint($object['owner_id']):0;
			if($owner&&$owner!==get_current_user_id()){$scoped=(bool)apply_filters('vwlb_object_scope_authorized',false,$capability,$object,$purpose,$claims);$allowed=$allowed&&$scoped;}
		}
		return (bool)apply_filters('vwlb_authorize',$allowed,$capability,$object,$purpose,$claims);
	}
	public static function can_view($object,$purpose='playback'){
		if(!is_array($object)||in_array($object['status']??'',array('removed','restricted','failed','deleted'),true))return false;
		$is_video=array_key_exists('published_at',$object);$is_live=array_key_exists('scheduled_start',$object);
		if($is_video&&'published'!==($object['status']??''))return self::can(VWLB_Contracts::CAP_PUBLISH,$object,$purpose);
		if($is_live&&!in_array($object['status']??'',array('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published'),true))return self::can(VWLB_Contracts::CAP_BROADCAST,$object,$purpose);
		$visibility=$object['visibility']??'private';if(in_array($visibility,array('public','unlisted'),true))return true;
		$claims=self::claims();if(!self::claims_ready($claims))return false;
		if('member'===$visibility)return true;
		if('private'===$visibility)return self::can(VWLB_Contracts::CAP_MANAGE,$object,$purpose)||absint($object['owner_id']??0)===get_current_user_id();
		if('entitled'===$visibility){$required=VWLB_Helpers::json($object['access_policy_json']??'{}');$ent=sanitize_key($required['entitlement']??'');return $ent&&in_array($ent,$claims['entitlements'],true);}
		return false;
	}
	public static function require_step_up($action){
		$claims=self::claims();if(!self::claims_ready($claims))return VWLB_Helpers::error('vwlb_step_up_required',__('Additional identity verification is required.',VWLB_TEXT_DOMAIN),403);
		$ok=(bool)apply_filters('vwlb_step_up_verified',false,get_current_user_id(),$action,$claims);
		return $ok?true:VWLB_Helpers::error('vwlb_step_up_required',__('Additional identity verification is required.',VWLB_TEXT_DOMAIN),403);
	}
	public static function rate_limit($bucket,$limit,$window){
		global $wpdb;$key=hash_hmac('sha256',sanitize_key($bucket).'|'.get_current_user_id().'|'.VWLB_Helpers::ip_hash(),wp_salt('nonce'));$table=VWLB_Helpers::table('rate_limits');$now=time();
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE limit_key=%s",$key),ARRAY_A);
		if(!$row||strtotime($row['window_ends_at'].' UTC')<=$now){$wpdb->replace($table,array('limit_key'=>$key,'counter'=>1,'window_ends_at'=>gmdate('Y-m-d H:i:s',$now+$window),'updated_at'=>VWLB_Helpers::now()),array('%s','%d','%s','%s'));return true;}
		if((int)$row['counter']>=$limit)return VWLB_Helpers::error('vwlb_rate_limited',__('Too many requests. Please try again later.',VWLB_TEXT_DOMAIN),429,array('retry_after'=>max(1,strtotime($row['window_ends_at'].' UTC')-$now)));
		$changed=$wpdb->query($wpdb->prepare("UPDATE $table SET counter=counter+1,updated_at=%s WHERE limit_key=%s AND counter=%d",VWLB_Helpers::now(),$key,(int)$row['counter']));
		if(1!==$changed)return self::rate_limit($bucket,$limit,$window);return true;
	}
	private static function idem_scope($scope){return substr(sanitize_key($scope).':'.get_current_user_id(),0,100);}
	public static function idempotency_begin($key,$scope,$request_hash){
		global $wpdb;$key=VWLB_Helpers::text($key,128);if(!$key)return VWLB_Helpers::error('vwlb_idempotency_required',__('Idempotency-Key is required.',VWLB_TEXT_DOMAIN),400);$scope=self::idem_scope($scope);$table=VWLB_Helpers::table('idempotency');
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);
		if($row&&strtotime($row['expires_at'].' UTC')<=time()){$wpdb->delete($table,array('id'=>$row['id']),array('%d'));$row=null;}
		if($row){if(!hash_equals($row['request_hash'],$request_hash))return VWLB_Helpers::error('vwlb_idempotency_conflict',__('This key was used for another request.',VWLB_TEXT_DOMAIN),409);if('complete'!==$row['status'])return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is still in progress.',VWLB_TEXT_DOMAIN),409);return array('replay'=>true,'response'=>VWLB_Helpers::json($row['response_json']));}
		$inserted=$wpdb->insert($table,array('idempotency_key'=>$key,'scope'=>$scope,'request_hash'=>$request_hash,'status'=>'processing','response_json'=>'{}','created_at'=>VWLB_Helpers::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS)),array('%s','%s','%s','%s','%s','%s','%s'));
		if(false===$inserted){$race=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);if($race&&hash_equals($race['request_hash'],$request_hash)&&'complete'===$race['status'])return array('replay'=>true,'response'=>VWLB_Helpers::json($race['response_json']));return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is already running.',VWLB_TEXT_DOMAIN),409);}return array('replay'=>false);
	}
	public static function idempotency_finish($key,$scope,$response){global $wpdb;$scope=self::idem_scope($scope);$wpdb->update(VWLB_Helpers::table('idempotency'),array('status'=>'complete','response_json'=>VWLB_Helpers::json_encode($response)),array('idempotency_key'=>$key,'scope'=>$scope),array('%s','%s'),array('%s','%s'));}
}
