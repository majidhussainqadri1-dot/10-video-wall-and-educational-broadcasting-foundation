<?php
/** Native authorization, File 00 claim boundary, rate limits and step-up controls. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Security {
	private static $rest_idempotency = array();
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
		$policy=(bool)apply_filters('vwlb_authorize',$allowed,$capability,$object,$purpose,$claims);
		return $allowed&&$policy; // R16: authorization filters may restrict native authority, never broaden it.
	}
	public static function can_view($object,$purpose='playback'){
		if(!is_array($object)||!empty($object['deleted_at'])||in_array($object['status']??'',array('removed','restricted','failed','deleted'),true))return false;
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
		global $wpdb;$limit=max(1,(int)$limit);$window=max(1,(int)$window);$key=hash_hmac('sha256',sanitize_key($bucket).'|'.get_current_user_id().'|'.VWLB_Helpers::ip_hash(),wp_salt('nonce'));$table=VWLB_Helpers::table('rate_limits');$now=VWLB_Helpers::now();$ends=gmdate('Y-m-d H:i:s',time()+$window);
		$changed=$wpdb->query($wpdb->prepare("INSERT INTO $table (limit_key,counter,window_ends_at,updated_at) VALUES (%s,1,%s,%s) ON DUPLICATE KEY UPDATE counter=IF(window_ends_at<=%s,1,counter+1),window_ends_at=IF(window_ends_at<=%s,%s,window_ends_at),updated_at=%s",$key,$ends,$now,$now,$now,$ends,$now));
		if(false===$changed)return VWLB_Helpers::error('vwlb_rate_limit_store_unavailable',__('Request throttling is temporarily unavailable. Please retry safely.',VWLB_TEXT_DOMAIN),503);
		$row=$wpdb->get_row($wpdb->prepare("SELECT counter,window_ends_at FROM $table WHERE limit_key=%s",$key),ARRAY_A);if(!$row)return VWLB_Helpers::error('vwlb_rate_limit_store_unavailable',__('Request throttling state could not be verified.',VWLB_TEXT_DOMAIN),503);
		if((int)$row['counter']>$limit)return VWLB_Helpers::error('vwlb_rate_limited',__('Too many requests. Please try again later.',VWLB_TEXT_DOMAIN),429,array('retry_after'=>max(1,strtotime($row['window_ends_at'].' UTC')-time())));return true;
	}

	private static function idem_scope($scope){$uid=get_current_user_id();$actor=$uid?'u'.$uid:'a'.substr(VWLB_Helpers::ip_hash(),0,32);return substr(sanitize_key($scope).':'.$actor,0,100);}
	public static function idempotency_begin($key,$scope,$request_hash,$replayable=true){
		global $wpdb;$key=VWLB_Helpers::text($key,128);if(!$key)return VWLB_Helpers::error('vwlb_idempotency_required',__('Idempotency-Key is required.',VWLB_TEXT_DOMAIN),400);$scope=self::idem_scope($scope);$table=VWLB_Helpers::table('idempotency');
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);
		if($row&&strtotime($row['expires_at'].' UTC')<=time()){
			$expired_id=absint($row['id']);$deleted=$wpdb->delete($table,array('id'=>$expired_id),array('%d'));
			if(false===$deleted)return VWLB_Helpers::error('vwlb_idempotency_expiry_cleanup_failed',__('Expired replay state could not be removed safely.',VWLB_TEXT_DOMAIN),503);
			$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);
			if($row&&absint($row['id'])===$expired_id&&strtotime($row['expires_at'].' UTC')<=time())return VWLB_Helpers::error('vwlb_idempotency_expiry_cleanup_failed',__('Expired replay state remained after cleanup and the request was stopped safely.',VWLB_TEXT_DOMAIN),503);
		}
		if($row){if(!hash_equals($row['request_hash'],$request_hash))return VWLB_Helpers::error('vwlb_idempotency_conflict',__('This key was used for another request.',VWLB_TEXT_DOMAIN),409);if('complete'!==$row['status'])return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is still in progress.',VWLB_TEXT_DOMAIN),409);if(!$replayable)return VWLB_Helpers::error('vwlb_idempotency_nonreplayable',__('This one-time response was already issued and cannot be replayed. Start a new authorized request.',VWLB_TEXT_DOMAIN),409);return array('replay'=>true,'response'=>VWLB_Helpers::json($row['response_json']));}
		$inserted=$wpdb->insert($table,array('idempotency_key'=>$key,'scope'=>$scope,'request_hash'=>$request_hash,'status'=>'processing','response_json'=>'{}','created_at'=>VWLB_Helpers::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS)),array('%s','%s','%s','%s','%s','%s','%s'));
		if(false===$inserted){$race=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);if($race&&hash_equals($race['request_hash'],$request_hash)&&'complete'===$race['status']){if(!$replayable)return VWLB_Helpers::error('vwlb_idempotency_nonreplayable',__('This one-time response was already issued and cannot be replayed. Start a new authorized request.',VWLB_TEXT_DOMAIN),409);return array('replay'=>true,'response'=>VWLB_Helpers::json($race['response_json']));}return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is already running.',VWLB_TEXT_DOMAIN),409);}return array('replay'=>false);
	}
	public static function idempotency_finish($key,$scope,$response,$replayable=true){
		global $wpdb;$key=VWLB_Helpers::text($key,128);$scope=self::idem_scope($scope);$table=VWLB_Helpers::table('idempotency');$encoded=VWLB_Helpers::json_encode($replayable?$response:array('nonreplayable'=>true));$changed=$wpdb->update($table,array('status'=>'complete','response_json'=>$encoded),array('idempotency_key'=>$key,'scope'=>$scope,'status'=>'processing'),array('%s','%s'),array('%s','%s','%s'));
		if(1===$changed)return true;if(false===$changed)return VWLB_Helpers::error('vwlb_idempotency_persist_failed',__('The operation completed but its replay record could not be stored safely.',VWLB_TEXT_DOMAIN),503);
		$row=$wpdb->get_row($wpdb->prepare("SELECT status,response_json FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);if($row&&'complete'===$row['status']&&hash_equals((string)$row['response_json'],$encoded))return true;return VWLB_Helpers::error('vwlb_idempotency_persist_failed',__('The operation replay state could not be verified.',VWLB_TEXT_DOMAIN),503);
	}

	public static function idempotency_abort($key,$scope){global $wpdb;$scope=self::idem_scope($scope);$deleted=$wpdb->delete(VWLB_Helpers::table('idempotency'),array('idempotency_key'=>VWLB_Helpers::text($key,128),'scope'=>$scope,'status'=>'processing'),array('%s','%s','%s'));return false===$deleted?VWLB_Helpers::error('vwlb_idempotency_abort_failed',__('The failed operation replay lock could not be released safely.',VWLB_TEXT_DOMAIN),503):true;}
	private static function rest_file10($request){$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n){if(str_starts_with($route,'/'.$n.'/'))return true;}return false;}
	private static function rest_callback_name($handler){$cb=$handler['callback']??null;if(is_array($cb)&&isset($cb[1]))return sanitize_key((string)$cb[1]);return 'mutation';}
	private static function rest_request_hash($request){$params=$request->get_params();$normal=function(&$v)use(&$normal){if(is_array($v)){ksort($v);foreach($v as &$x)$normal($x);}};$normal($params);$headers=array('content-range'=>(string)$request->get_header('Content-Range'),'content-type'=>(string)$request->get_header('Content-Type'));return hash('sha256',strtoupper((string)$request->get_method()).'|'.$request->get_route().'|'.VWLB_Helpers::json_encode($params).'|'.hash('sha256',(string)$request->get_body()).'|'.VWLB_Helpers::json_encode($headers));}
	/** Cross-surface mutation contract: rate-limit every mutation and require durable idempotency except signed provider webhooks, which have provider event dedupe/replay controls. */
	public static function rest_mutation_before($response,$handler,$request){
		if(null!==$response||!self::rest_file10($request))return $response;$method=strtoupper((string)$request->get_method());if(in_array($method,array('GET','HEAD','OPTIONS'),true))return $response;$name=self::rest_callback_name($handler);
		$limit=max(1,(int)apply_filters('vwlb_rest_mutation_rate_limit',600,$name,$request));$window=max(1,(int)apply_filters('vwlb_rest_mutation_rate_window',60,$name,$request));$rate=self::rate_limit('rest_mutation_'.$name,$limit,$window);if(is_wp_error($rate))return $rate;
		if('webhook'===$name)return $response;$key=VWLB_Helpers::text($request->get_header('Idempotency-Key'),128);$scope='rest_'.$method.'_'.$name;$replayable=!in_array($name,array('issue_credential','upload_start','download_token','download_resolve','watermark_grant'),true);$idem=self::idempotency_begin($key,$scope,self::rest_request_hash($request),$replayable);if(is_wp_error($idem))return $idem;
		if(!empty($idem['replay'])){$stored=(array)$idem['response'];$replay=new WP_REST_Response($stored['data']??null,absint($stored['status']??200)?:200);$replay->header('X-VWLB-Idempotent-Replay','true');return $replay;}self::$rest_idempotency[spl_object_hash($request)]=array('key'=>$key,'scope'=>$scope,'replayable'=>$replayable);return $response;
	}

	public static function rest_mutation_after($response,$handler,$request){
		$hash=spl_object_hash($request);if(empty(self::$rest_idempotency[$hash]))return $response;$ctx=self::$rest_idempotency[$hash];unset(self::$rest_idempotency[$hash]);
		if(is_wp_error($response)){$aborted=self::idempotency_abort($ctx['key'],$ctx['scope']);return is_wp_error($aborted)?$aborted:$response;}$wrapped=rest_ensure_response($response);$status=(int)$wrapped->get_status();if($status>=500){$aborted=self::idempotency_abort($ctx['key'],$ctx['scope']);return is_wp_error($aborted)?$aborted:$response;}$finished=self::idempotency_finish($ctx['key'],$ctx['scope'],array('status'=>$status,'data'=>$wrapped->get_data()),!empty($ctx['replayable']));return is_wp_error($finished)?$finished:$response;
	}
}
