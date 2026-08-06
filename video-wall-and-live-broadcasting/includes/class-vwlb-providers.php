<?php
/** Provider abstraction: ingest, playback, processing, webhook and recording. */
defined( 'ABSPATH' ) || exit;
interface VWLB_Provider_Interface {
	public function id();
	public function capabilities();
	public function normalize_source( $source );
	public function create_live( $event );
	public function issue_ingest( $event );
	public function playback( $object, $viewer );
	public function process_asset( $asset, $job );
	public function verify_webhook( $headers, $body );
	public function reconcile( $object_type, $object );
}
abstract class VWLB_Provider_Base implements VWLB_Provider_Interface {
	public function create_live( $event ) { return array('provider_event_ref'=>'','state'=>'configured'); }
	public function issue_ingest( $event ) { return VWLB_Helpers::error('vwlb_provider_ingest_unavailable',__('The selected provider has no configured ingest endpoint.',VWLB_TEXT_DOMAIN),503); }
	public function process_asset( $asset, $job ) { return apply_filters('vwlb_provider_process_asset',VWLB_Helpers::error('vwlb_processor_unavailable',__('No media processor is configured.',VWLB_TEXT_DOMAIN),503),$this->id(),$asset,$job); }
	public function verify_webhook( $headers, $body ) { return false; }
	public function reconcile( $object_type, $object ) { return array('state'=>$object['status'] ?? 'unknown'); }
}
final class VWLB_Provider_Local extends VWLB_Provider_Base {
	public function id(){return 'local';}
	public function capabilities(){return array('upload'=>true,'playback'=>true,'processing'=>true,'live'=>false,'recording'=>false);}
	public function normalize_source($source){$url=VWLB_Helpers::remote_url($source);return $url?array('provider'=>'local','source_url'=>$url,'embed_url'=>''):VWLB_Helpers::error('vwlb_invalid_local_url',__('A valid HTTPS media URL is required.',VWLB_TEXT_DOMAIN));}
	public function playback($object,$viewer){$asset=!empty($object['asset'])?$object['asset']:array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$source=$state['playback_url']??$derivatives['hls']??$derivatives['mp4']??($object['source_url']??'');return $source?array('type'=>isset($derivatives['hls'])?'hls':'html5','url'=>esc_url_raw($source),'captions'=>$object['captions']??array()):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);}
	public function process_asset($asset,$job){$result=apply_filters('vwlb_local_processor_result',null,$asset,$job);if(is_array($result))return $result;$source=$asset['source_url']??'';if($source){return array('status'=>'ready','derivatives'=>array('mp4'=>$source),'technical_note'=>'Pass-through local source; production transcoder may replace this derivative.');}return parent::process_asset($asset,$job);}
	public function issue_ingest($event){$endpoint=apply_filters('vwlb_local_live_ingest_endpoint','',$event);if(!$endpoint)return parent::issue_ingest($event);return array('ingest_url'=>esc_url_raw($endpoint),'provider_ref'=>'local_'.$event['public_id']);}
}
final class VWLB_Provider_YouTube extends VWLB_Provider_Base {
	public function id(){return 'youtube';}
	public function capabilities(){return array('upload'=>false,'playback'=>true,'processing'=>false,'live'=>true,'recording'=>true);}
	public function normalize_source($source){$source=esc_url_raw($source,array('https'));$host=strtolower((string)wp_parse_url($source,PHP_URL_HOST));$id='';parse_str((string)wp_parse_url($source,PHP_URL_QUERY),$q);if(in_array($host,array('youtube.com','www.youtube.com','m.youtube.com'),true))$id=sanitize_text_field($q['v']??'');elseif('youtu.be'===$host)$id=trim((string)wp_parse_url($source,PHP_URL_PATH),'/');if(!preg_match('/^[A-Za-z0-9_-]{11}$/',$id))return VWLB_Helpers::error('vwlb_invalid_youtube',__('Invalid YouTube URL.',VWLB_TEXT_DOMAIN));return array('provider'=>'youtube','source_url'=>'https://www.youtube.com/watch?v='.$id,'embed_url'=>'https://www.youtube-nocookie.com/embed/'.$id,'provider_ref'=>$id);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['embed_url']??''),array('www.youtube-nocookie.com'));return $url?array('type'=>'iframe','url'=>$url,'sandbox'=>'allow-scripts allow-same-origin allow-presentation','captions'=>$object['captions']??array()):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is unavailable.',VWLB_TEXT_DOMAIN),503);}
	public function create_live($event){return apply_filters('vwlb_youtube_create_live',parent::create_live($event),$event);}
	public function issue_ingest($event){return apply_filters('vwlb_youtube_issue_ingest',parent::issue_ingest($event),$event);}
	public function verify_webhook($headers,$body){return (bool)apply_filters('vwlb_youtube_verify_webhook',false,$headers,$body);}
}
final class VWLB_Provider_Vimeo extends VWLB_Provider_Base {
	public function id(){return 'vimeo';}
	public function capabilities(){return array('upload'=>false,'playback'=>true,'processing'=>false,'live'=>true,'recording'=>true);}
	public function normalize_source($source){$source=esc_url_raw($source,array('https'));$host=strtolower((string)wp_parse_url($source,PHP_URL_HOST));$path=trim((string)wp_parse_url($source,PHP_URL_PATH),'/');if(!in_array($host,array('vimeo.com','www.vimeo.com','player.vimeo.com'),true)||!preg_match('/(?:video\/)?(\d{6,12})$/',$path,$m))return VWLB_Helpers::error('vwlb_invalid_vimeo',__('Invalid Vimeo URL.',VWLB_TEXT_DOMAIN));$id=$m[1];return array('provider'=>'vimeo','source_url'=>'https://vimeo.com/'.$id,'embed_url'=>'https://player.vimeo.com/video/'.$id,'provider_ref'=>$id);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['embed_url']??''),array('player.vimeo.com'));return $url?array('type'=>'iframe','url'=>$url,'sandbox'=>'allow-scripts allow-same-origin allow-presentation','captions'=>$object['captions']??array()):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is unavailable.',VWLB_TEXT_DOMAIN),503);}
	public function create_live($event){return apply_filters('vwlb_vimeo_create_live',parent::create_live($event),$event);}
	public function issue_ingest($event){return apply_filters('vwlb_vimeo_issue_ingest',parent::issue_ingest($event),$event);}
	public function verify_webhook($headers,$body){return (bool)apply_filters('vwlb_vimeo_verify_webhook',false,$headers,$body);}
}
final class VWLB_Provider_Custom extends VWLB_Provider_Base {
	public function id(){return 'custom';}
	public function capabilities(){return array('upload'=>true,'playback'=>true,'processing'=>true,'live'=>true,'recording'=>true);}
	public function normalize_source($source){$url=VWLB_Helpers::remote_url($source);return $url?array('provider'=>'custom','source_url'=>$url,'embed_url'=>'','provider_ref'=>hash('sha256',$url)):VWLB_Helpers::error('vwlb_invalid_custom_url',__('A valid HTTPS source is required.',VWLB_TEXT_DOMAIN));}
	public function create_live($event){$result=apply_filters('vwlb_custom_create_live',null,$event);return is_array($result)?$result:array('provider_event_ref'=>'custom_'.$event['public_id'],'state'=>'configured');}
	public function issue_ingest($event){$base=defined('VWLB_CUSTOM_INGEST_BASE')?VWLB_CUSTOM_INGEST_BASE:'';$base=VWLB_Helpers::safe_url($base);if(!$base)return parent::issue_ingest($event);return array('ingest_url'=>trailingslashit($base).rawurlencode($event['public_id']),'stream_key'=>VWLB_Providers::stream_secret(),'provider_ref'=>'custom_'.$event['public_id']);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['source_url']??''));return $url?array('type'=>!empty($state['iframe'])?'iframe':'hls','url'=>$url,'captions'=>$object['captions']??array()):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);}
	public function process_asset($asset,$job){$result=apply_filters('vwlb_custom_process_asset',null,$asset,$job);return is_array($result)?$result:parent::process_asset($asset,$job);}
	public function verify_webhook($headers,$body){if(!defined('VWLB_CUSTOM_WEBHOOK_SECRET')||!VWLB_CUSTOM_WEBHOOK_SECRET)return false;$sig='';foreach($headers as $k=>$v){if(strtolower($k)==='x-vwlb-signature')$sig=is_array($v)?reset($v):$v;}return $sig&&hash_equals(hash_hmac('sha256',$body,VWLB_CUSTOM_WEBHOOK_SECRET),(string)$sig);}
}
final class VWLB_Providers {
	private static $providers=array();
	public static function register_defaults(){self::register(new VWLB_Provider_Local());self::register(new VWLB_Provider_YouTube());self::register(new VWLB_Provider_Vimeo());self::register(new VWLB_Provider_Custom());do_action('vwlb_register_providers',__CLASS__);}
	public static function register($provider){if($provider instanceof VWLB_Provider_Interface)self::$providers[$provider->id()]=$provider;}
	public static function get($id){$id=sanitize_key($id);return self::$providers[$id]??null;}
	public static function all(){return self::$providers;}
	public static function normalize($provider,$source){$p=self::get($provider);return $p?$p->normalize_source($source):VWLB_Helpers::error('vwlb_unknown_provider',__('Unknown media provider.',VWLB_TEXT_DOMAIN));}
	public static function stream_secret(){return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
	public static function hash_secret($secret){return password_hash($secret,PASSWORD_DEFAULT);}
}
