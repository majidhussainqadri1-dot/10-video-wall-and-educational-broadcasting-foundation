<?php
/** Native authorization, rate limits and step-up boundaries. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Security {
	public static function claims() {
		$user_id = get_current_user_id();
		$claims = array( 'user_id'=>$user_id,'authenticated'=>$user_id>0,'suspended'=>false,'verified_doctor'=>false,'founder'=>false,'identity_approved'=>false,'age_ok'=>false,'guardian_ok'=>false,'entitlements'=>array() );
		$external = apply_filters( 'vwlb_identity_claims', null, $user_id );
		if ( is_array( $external ) ) { $claims = array_merge( $claims, $external ); }
		if ( user_can( $user_id, 'manage_options' ) ) { $claims['founder'] = (bool) apply_filters( 'vwlb_admin_is_institutional_authority', true, $user_id ); $claims['identity_approved']=true; $claims['age_ok']=true; $claims['guardian_ok']=true; }
		return $claims;
	}
	public static function can( $capability, $object = null, $purpose = 'platform_operation' ) {
		$claims = self::claims();
		if ( ! $claims['authenticated'] || ! empty( $claims['suspended'] ) || empty($claims['identity_approved']) || empty($claims['age_ok']) || empty($claims['guardian_ok']) ) { return false; }
		if(get_option('vwlb_safe_mode')&&!in_array($purpose,array('repair','emergency_end','moderation_decision'),true)){return false;}
		$allowed = current_user_can( $capability );
		if ( $object && ! current_user_can( VWLB_Contracts::CAP_MANAGE ) ) {
			$owner = isset( $object['owner_id'] ) ? absint( $object['owner_id'] ) : 0;
			$allowed = $allowed && ( ! $owner || $owner === get_current_user_id() || current_user_can( VWLB_Contracts::CAP_MODERATE ) );
		}
		return (bool) apply_filters( 'vwlb_authorize', $allowed, $capability, $object, $purpose, $claims );
	}
	public static function can_view( $object, $purpose = 'playback' ) {
		if ( ! is_array( $object ) || in_array( $object['status'] ?? '', array( 'removed','restricted','failed' ), true ) ) { return false; }
		$is_video=array_key_exists('published_at',$object);$is_live=array_key_exists('scheduled_start',$object);
		if($is_video&&'published'!==($object['status']??'')){return self::can(VWLB_Contracts::CAP_PUBLISH,$object,$purpose);}
		if($is_live&&!in_array($object['status']??'',array('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published'),true)){return self::can(VWLB_Contracts::CAP_BROADCAST,$object,$purpose);}
		$visibility = $object['visibility'] ?? 'private';
		if ( 'public' === $visibility || 'unlisted' === $visibility ) { return true; }
		$claims = self::claims(); if ( ! $claims['authenticated'] || ! empty( $claims['suspended'] ) ) { return false; }
		if ( 'member' === $visibility ) { return true; }
		if ( 'private' === $visibility ) { return self::can( VWLB_Contracts::CAP_MANAGE, $object, $purpose ) || absint( $object['owner_id'] ?? 0 ) === get_current_user_id(); }
		if ( 'entitled' === $visibility ) { $required = VWLB_Helpers::json( $object['access_policy_json'] ?? '{}' ); return in_array( $required['entitlement'] ?? '', $claims['entitlements'] ?? array(), true ); }
		return false;
	}
	public static function require_step_up( $action ) {
		$ok = (bool) apply_filters( 'vwlb_step_up_verified', current_user_can( 'manage_options' ), get_current_user_id(), $action );
		return $ok ? true : VWLB_Helpers::error( 'vwlb_step_up_required', __( 'Additional identity verification is required.', VWLB_TEXT_DOMAIN ), 403 );
	}
	public static function rate_limit( $bucket, $limit, $window ) {
		global $wpdb; $key = hash_hmac( 'sha256', sanitize_key($bucket).'|'.get_current_user_id().'|'.VWLB_Helpers::ip_hash(), wp_salt('nonce') ); $table=VWLB_Helpers::table('rate_limits'); $now=time();
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE limit_key=%s",$key),ARRAY_A);
		if(!$row || strtotime($row['window_ends_at']) <= $now){ $wpdb->replace($table,array('limit_key'=>$key,'counter'=>1,'window_ends_at'=>gmdate('Y-m-d H:i:s',$now+$window),'updated_at'=>VWLB_Helpers::now()),array('%s','%d','%s','%s')); return true; }
		if((int)$row['counter'] >= $limit){ return VWLB_Helpers::error('vwlb_rate_limited',__('Too many requests. Please try again later.',VWLB_TEXT_DOMAIN),429,array('retry_after'=>max(1,strtotime($row['window_ends_at'])-$now))); }
		$wpdb->query($wpdb->prepare("UPDATE $table SET counter=counter+1,updated_at=%s WHERE limit_key=%s",VWLB_Helpers::now(),$key)); return true;
	}
	public static function idempotency_begin( $key, $scope, $request_hash ) {
		global $wpdb; $key=VWLB_Helpers::text($key,128); if(!$key){return VWLB_Helpers::error('vwlb_idempotency_required',__('Idempotency-Key is required.',VWLB_TEXT_DOMAIN),400);} $table=VWLB_Helpers::table('idempotency');
		$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);
		if($row){ if(!hash_equals($row['request_hash'],$request_hash)){return VWLB_Helpers::error('vwlb_idempotency_conflict',__('This key was used for another request.',VWLB_TEXT_DOMAIN),409);} if('complete'!==$row['status'])return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is still in progress.',VWLB_TEXT_DOMAIN),409); return array('replay'=>true,'response'=>VWLB_Helpers::json($row['response_json'])); }
		$inserted=$wpdb->insert($table,array('idempotency_key'=>$key,'scope'=>$scope,'request_hash'=>$request_hash,'status'=>'processing','response_json'=>'{}','created_at'=>VWLB_Helpers::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS)),array('%s','%s','%s','%s','%s','%s','%s')); if(false===$inserted){$race=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s",$key,$scope),ARRAY_A);if($race&&hash_equals($race['request_hash'],$request_hash)&&'complete'===$race['status'])return array('replay'=>true,'response'=>VWLB_Helpers::json($race['response_json']));return VWLB_Helpers::error('vwlb_idempotency_in_progress',__('An operation with this key is already running.',VWLB_TEXT_DOMAIN),409);} return array('replay'=>false);
	}
	public static function idempotency_finish( $key, $scope, $response ) { global $wpdb; $wpdb->update(VWLB_Helpers::table('idempotency'),array('status'=>'complete','response_json'=>VWLB_Helpers::json_encode($response)),array('idempotency_key'=>$key,'scope'=>$scope),array('%s','%s'),array('%s','%s')); }
}
