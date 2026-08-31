<?php
/** R91/R108: signed/noindex/no-store access contract with fail-closed secure delivery for unlisted media. */
defined('ABSPATH') || exit;
final class VWLB_R91_Unlisted_Access_Guard {
	const MAX_TTL=DAY_IN_SECONDS;
	public static function register(){
		add_filter('vwlb_unlisted_access_authorized',array(__CLASS__,'authorize'),10,4);
		add_action('template_redirect',array(__CLASS__,'private_route_headers'),2);
		add_filter('rest_request_after_callbacks',array(__CLASS__,'secure_unlisted_delivery'),88,3);
	}
	private static function key(){return hash('sha256',wp_salt('auth').'|'.wp_salt('secure_auth').'|File10UnlistedAccess',true);}
	private static function object_ref($object){return VWLB_Helpers::text($object['public_id']??($object['id']??''),80);}
	private static function signature($ref,$exp){return hash_hmac('sha256','v1|'.$ref.'|'.(int)$exp,self::key());}
	public static function signed_query($object,$ttl=3600){$ref=self::object_ref($object);if(!$ref)return VWLB_Helpers::error('vwlb_unlisted_object_invalid',__('Unlisted media reference is unavailable.',VWLB_TEXT_DOMAIN),500);$ttl=max(60,min(self::MAX_TTL,(int)$ttl));$exp=time()+$ttl;return array('vwlb_exp'=>$exp,'vwlb_sig'=>self::signature($ref,$exp));}
	public static function authorize($allowed,$object,$purpose,$claims){
		if($allowed)return true;$ref=self::object_ref($object);if(!$ref)return false;
		$exp=isset($_GET['vwlb_exp'])?absint(wp_unslash($_GET['vwlb_exp'])):0;$sig=isset($_GET['vwlb_sig'])?VWLB_Helpers::text(wp_unslash($_GET['vwlb_sig']),128):'';
		if(!$exp||!$sig||$exp<time()||$exp>time()+self::MAX_TTL)return false;
		return hash_equals(self::signature($ref,$exp),$sig);
	}
	public static function private_route_headers(){
		if(get_query_var('vwlb_channel_slug')){global $wpdb;$slug=VWLB_Helpers::text(get_query_var('vwlb_channel_slug'),191);$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare('SELECT visibility FROM '.VWLB_Helpers::table('channels').' WHERE slug=%s LIMIT 1',$slug),ARRAY_A);if(''!==(string)$wpdb->last_error||($row&&'public'!==($row['visibility']??'')))VWLB_Helpers::no_cache_private();}
		if(get_query_var('vwlb_podcast_id')){global $wpdb;$id=VWLB_Helpers::text(get_query_var('vwlb_podcast_id'),64);$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare('SELECT visibility FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE public_id=%s LIMIT 1',$id),ARRAY_A);if(''!==(string)$wpdb->last_error||($row&&'public'!==($row['visibility']??'')))VWLB_Helpers::no_cache_private();}
	}
	private static function route_object($request){
		if(!$request instanceof WP_REST_Request)return array('matched'=>false);$route=(string)$request->get_route();
		foreach(VWLB_Contracts::namespaces() as $n){
			$q=preg_quote($n,'#');
			if(preg_match('#^/'.$q.'/videos/([A-Za-z0-9_-]+)/playback$#',$route,$m)){VWLB_Repository::reset_read_failure();$row=VWLB_Repository::find('videos',$m[1]);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_unlisted_state_unreadable',__('Unlisted video visibility could not be reverified safely.',VWLB_TEXT_DOMAIN),503);return array('matched'=>true,'type'=>'video','row'=>$row);}
			if(preg_match('#^/'.$q.'/live-events/([A-Za-z0-9_-]+)$#',$route,$m)){VWLB_Repository::reset_read_failure();$row=VWLB_Repository::find('live_events',$m[1]);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_unlisted_state_unreadable',__('Unlisted live visibility could not be reverified safely.',VWLB_TEXT_DOMAIN),503);return array('matched'=>true,'type'=>'live','row'=>$row);}
			if(preg_match('#^/'.$q.'/podcasts/episodes/([A-Za-z0-9_-]+)$#',$route,$m)){global $wpdb;$wpdb->last_error='';$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',VWLB_Helpers::text($m[1],64)),ARRAY_A);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_unlisted_state_unreadable',__('Unlisted podcast visibility could not be reverified safely.',VWLB_TEXT_DOMAIN),503);return array('matched'=>true,'type'=>'podcast','row'=>$row);}
		}
		return array('matched'=>false);
	}
	private static function grant($hook,$args,$code,$message,$context=array()){
		try{$value=apply_filters($hook,'',...$args);}catch(Throwable $e){do_action('vwlb_operational_failure','delivery',$code.'_exception',array_merge($context,array('exception'=>sanitize_key(get_class($e)))));return VWLB_Helpers::error($code,$message,503);}$url=esc_url_raw((string)$value,array('https'));return $url?$url:VWLB_Helpers::error($code,$message,503);
	}
	public static function secure_unlisted_delivery($response,$handler,$request){
		if(is_wp_error($response)||!$request instanceof WP_REST_Request)return $response;$ctx=self::route_object($request);if(is_wp_error($ctx))return $ctx;if(empty($ctx['matched']))return $response;$row=$ctx['row']??null;if(!$row)return VWLB_Helpers::error('vwlb_not_found',__('The requested unlisted media is no longer available.',VWLB_TEXT_DOMAIN),404);if('unlisted'!==($row['visibility']??''))return $response;
		$wrapped=rest_ensure_response($response);$wrapped->header('Cache-Control','private, no-store');$wrapped->header('X-Robots-Tag','noindex, nofollow, noarchive');$data=$wrapped->get_data();if(!is_array($data))return $wrapped;
		if('video'===($ctx['type']??'')&&!empty($data['playback'])&&is_array($data['playback'])){$session=(array)($data['session']??array());$url=self::grant('vwlb_secure_playback_grant',array($row,$data['playback'],$session,VWLB_Security::claims()),'vwlb_unlisted_secure_delivery_required',__('Unlisted video requires a short-lived secure playback grant.',VWLB_TEXT_DOMAIN),array('type'=>'video','public_id'=>$row['public_id']??''));if(is_wp_error($url))return $url;$data['playback']['url']=$url;$wrapped->set_data($data);}
		elseif('live'===($ctx['type']??'')&&!empty($data['playback']['url'])){$url=self::grant('vwlb_secure_live_playback_grant',array($row,$data['playback'],VWLB_Security::claims()),'vwlb_unlisted_secure_delivery_required',__('Unlisted live media requires a short-lived secure playback grant.',VWLB_TEXT_DOMAIN),array('type'=>'live','public_id'=>$row['public_id']??''));if(is_wp_error($url))return $url;$data['playback']['url']=$url;$wrapped->set_data($data);}
		elseif('podcast'===($ctx['type']??'')&&!empty($data['audio_url'])){VWLB_Repository::reset_read_failure();$asset=VWLB_Repository::find('media_assets',$row['asset_id']??0);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_unlisted_state_unreadable',__('Unlisted podcast media state could not be reverified safely.',VWLB_TEXT_DOMAIN),503);if(!$asset)return VWLB_Helpers::error('vwlb_unlisted_secure_delivery_required',__('Unlisted podcast media is unavailable for secure delivery.',VWLB_TEXT_DOMAIN),503);$series=array();if(!empty($row['series_id'])){global $wpdb;$wpdb->last_error='';$series=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE id=%d LIMIT 1',(int)$row['series_id']),ARRAY_A)?:array();if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_unlisted_state_unreadable',__('Unlisted podcast series state could not be reverified safely.',VWLB_TEXT_DOMAIN),503);}$url=self::grant('vwlb_public_podcast_feed_grant',array($asset,$row,$series),'vwlb_unlisted_secure_delivery_required',__('Unlisted podcast media requires a short-lived secure delivery grant.',VWLB_TEXT_DOMAIN),array('type'=>'podcast','public_id'=>$row['public_id']??''));if(is_wp_error($url))return $url;$data['audio_url']=$url;$wrapped->set_data($data);}
		return $wrapped;
	}
}
