<?php
/** R46: provider ingest issuance cannot be reported as a durable credential unless local persistence succeeds; failed persistence requires provider compensation/reconciliation. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R46_Stream_Credential_Durability {
	public static function register(){add_filter('rest_request_before_callbacks',array(__CLASS__,'intercept_issue'),21,3);}
	private static function route_matches($request){if(!$request instanceof WP_REST_Request||'POST'!==strtoupper((string)$request->get_method()))return false;$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n)if(preg_match('#^/'.preg_quote($n,'#').'/live-events/[A-Za-z0-9_-]+/credentials$#',$route))return true;return false;}
	private static function ttl($request){$d=$request->get_json_params();return max(300,min(DAY_IN_SECONDS,(int)(is_array($d)?($d['ttl']??21600):21600)));}
	private static function compensate($event,$ingest,$reason){
		$context=array('provider'=>$event['provider'],'live_public_id'=>$event['public_id'],'provider_ref'=>VWLB_Helpers::text($ingest['provider_ref']??'',191),'reason'=>sanitize_key($reason));
		do_action('vwlb_provider_ingest_compensation_requested',$context);
		try{$result=apply_filters('vwlb_provider_revoke_ingest_result',null,$context);}catch(Throwable $e){$result=null;do_action('vwlb_operational_failure','live','vwlb_provider_ingest_compensation_exception',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider']));}
		$confirmed=true===$result||(is_array($result)&&in_array($result['status']??'',array('revoked','deleted','disabled','rotated'),true));
		if(!$confirmed)do_action('vwlb_operational_failure','live','vwlb_provider_ingest_reconcile_required',array('live_public_id'=>$event['public_id'],'provider'=>$event['provider'],'provider_ref_hash'=>hash('sha256',(string)($ingest['provider_ref']??''))));
		return $confirmed;
	}
	private static function issue($event,$ttl){
		$provider=VWLB_Providers::get($event['provider']);if(!$provider)return VWLB_Helpers::error('vwlb_provider_missing',__('The configured live provider is unavailable.',VWLB_TEXT_DOMAIN),503);
		$ingest=$provider->issue_ingest($event);if(is_wp_error($ingest))return $ingest;if(!is_array($ingest))return VWLB_Helpers::error('vwlb_provider_ingest_invalid',__('The live provider returned an invalid ingest response.',VWLB_TEXT_DOMAIN),503);
		$secret=!empty($ingest['stream_key'])?(string)$ingest['stream_key']:VWLB_Providers::stream_secret();unset($ingest['stream_key']);$now=VWLB_Helpers::now();global $wpdb;
		$result=VWLB_DB::transaction(function()use($wpdb,$event,$ingest,$secret,$ttl,$now){
			$rotated=$wpdb->update(VWLB_Helpers::table('stream_credentials'),array('status'=>'rotated','rotated_at'=>$now),array('live_event_id'=>$event['id'],'status'=>'active'));if(false===$rotated)return VWLB_Helpers::error('vwlb_database_error',__('Existing stream credentials could not be rotated.',VWLB_TEXT_DOMAIN),500);
			$public=VWLB_Helpers::public_id('key');$expires=gmdate('Y-m-d H:i:s',time()+$ttl);$saved=$wpdb->insert(VWLB_Helpers::table('stream_credentials'),array('public_id'=>$public,'live_event_id'=>$event['id'],'provider'=>$event['provider'],'credential_hash'=>VWLB_Providers::hash_secret($secret),'ingest_url'=>esc_url_raw($ingest['ingest_url']??''),'scope'=>'broadcast','status'=>'active','expires_at'=>$expires,'created_by'=>get_current_user_id(),'created_at'=>$now));if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Stream credential could not be recorded.',VWLB_TEXT_DOMAIN),500);
			VWLB_Helpers::audit('live',$event['id'],'issue_stream_credential',$event['status'],$event['status'],'Credential issued; secret redacted from audit',array('credential_public_id'=>$public,'provider_ref_hash'=>hash('sha256',(string)($ingest['provider_ref']??''))));return array('public_id'=>$public,'ingest_url'=>esc_url_raw($ingest['ingest_url']??''),'stream_key'=>$secret,'display_once'=>true,'expires_at'=>gmdate('c',time()+$ttl));
		});
		if(!is_wp_error($result))return $result;
		$compensated=self::compensate($event,$ingest,$result->get_error_code());
		if(!$compensated)return VWLB_Helpers::error('vwlb_provider_ingest_reconcile_required',__('The provider created an ingest credential but local persistence failed and provider revocation could not be confirmed. The credential was not disclosed; reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('live_public_id'=>$event['public_id'],'reconcile_required'=>true));
		return $result;
	}
	public static function intercept_issue($response,$handler,$request){
		if(null!==$response||!self::route_matches($request))return $response;$event=VWLB_Repository::find('live_events',$request['id']);if(!$event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);if(!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'issue_stream_credential'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot issue credentials.',VWLB_TEXT_DOMAIN),403);$step=VWLB_Security::require_step_up('issue_stream_credential');if(is_wp_error($step))return $step;if(!in_array($event['status'],array('scheduled','rehearsal','ready'),true))return VWLB_Helpers::error('vwlb_live_state_invalid',__('Credentials cannot be issued in this state.',VWLB_TEXT_DOMAIN),409);$issued=self::issue($event,self::ttl($request));if(is_wp_error($issued))return $issued;$out=rest_ensure_response($issued);$out->set_status(201);$out->header('Cache-Control','private, no-store');$out->header('X-Sabri-File','10');$out->header('X-VWLB-Version',VWLB_VERSION);return $out;
	}
}
