<?php
/** Provider abstraction: ingest, playback, processing, webhook and recording. */
defined( 'ABSPATH' ) || exit;
interface VWLB_Provider_Interface {
	public function id(); public function capabilities(); public function normalize_source($source);
	public function create_live($event); public function issue_ingest($event); public function playback($object,$viewer);
	public function process_asset($asset,$job); public function verify_webhook($headers,$body); public function reconcile($object_type,$object);
}
abstract class VWLB_Provider_Base implements VWLB_Provider_Interface {
	public function create_live($event){return VWLB_Helpers::error('vwlb_provider_live_unavailable',__('The selected provider has no configured live-event adapter.',VWLB_TEXT_DOMAIN),503);}
	public function issue_ingest($event){return VWLB_Helpers::error('vwlb_provider_ingest_unavailable',__('The selected provider has no configured ingest endpoint.',VWLB_TEXT_DOMAIN),503);}
	public function process_asset($asset,$job){return apply_filters('vwlb_provider_process_asset',VWLB_Helpers::error('vwlb_processor_unavailable',__('No media processor is configured.',VWLB_TEXT_DOMAIN),503),$this->id(),$asset,$job);}
	public function verify_webhook($headers,$body){return false;}
	public function reconcile($object_type,$object){return array('state'=>$object['status']??'unknown');}
	protected function low_bandwidth(){
		$requested=false;
		if(isset($_GET['low_bandwidth']))$requested='1'===sanitize_text_field(wp_unslash($_GET['low_bandwidth']));
		if(isset($_COOKIE['vwlb_low_bandwidth']))$requested=$requested||'1'===sanitize_text_field(wp_unslash($_COOKIE['vwlb_low_bandwidth']));
		return (bool)apply_filters('vwlb_low_bandwidth_mode',$requested);
	}
	protected function header($headers,$name){foreach((array)$headers as $k=>$v){if(strtolower((string)$k)===strtolower($name))return is_array($v)?(string)reset($v):(string)$v;}return '';}
}
final class VWLB_Provider_Local extends VWLB_Provider_Base {
	public function id(){return 'local';}
	public function capabilities(){return array('upload'=>true,'playback'=>true,'processing'=>true,'live'=>false,'recording'=>false,'portable_metadata'=>true,'low_bandwidth'=>true,'audio_only'=>true);}
	public function normalize_source($source){$url=VWLB_Helpers::remote_url($source);return $url?array('provider'=>'local','source_url'=>$url,'embed_url'=>''):VWLB_Helpers::error('vwlb_invalid_local_url',__('A valid HTTPS media URL is required.',VWLB_TEXT_DOMAIN));}
	public function playback($object,$viewer){
		$asset=!empty($object['asset'])?$object['asset']:array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$state=VWLB_Helpers::json($object['provider_state_json']??'{}');
		if($this->low_bandwidth())$source=$derivatives['audio_only']??$derivatives['mp4_low']??$derivatives['hls_low']??$derivatives['hls']??$derivatives['mp4']??($object['source_url']??'');
		else $source=$state['playback_url']??$derivatives['hls']??$derivatives['mp4_high']??$derivatives['mp4']??($object['source_url']??'');
		$type=isset($derivatives['hls'])&&!$this->low_bandwidth()?'hls':(isset($derivatives['audio_only'])&&$source===$derivatives['audio_only']?'audio':'html5');
		return $source?array('type'=>$type,'url'=>esc_url_raw($source),'captions'=>$object['captions']??array(),'low_bandwidth'=>$this->low_bandwidth()):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);
	}
	public function process_asset($asset,$job){$result=apply_filters('vwlb_local_processor_result',null,$asset,$job);if(is_array($result)||is_wp_error($result))return $result;$source=$asset['source_url']??'';if($source){return array('status'=>'ready','derivatives'=>array('mp4'=>$source,'mp4_high'=>$source),'technical_note'=>'Pass-through external local source. No adaptive derivative is claimed unless a processor supplied it.');}return parent::process_asset($asset,$job);}
	public function issue_ingest($event){$endpoint=apply_filters('vwlb_local_live_ingest_endpoint','',$event);if(!$endpoint)return parent::issue_ingest($event);$endpoint=VWLB_Helpers::remote_url($endpoint);return $endpoint?array('ingest_url'=>$endpoint,'provider_ref'=>'local_'.$event['public_id']):VWLB_Helpers::error('vwlb_provider_ingest_invalid',__('Configured ingest endpoint is not a safe HTTPS remote URL.',VWLB_TEXT_DOMAIN),503);}
}
final class VWLB_Provider_YouTube extends VWLB_Provider_Base {
	public function id(){return 'youtube';}
	public function capabilities(){return array('upload'=>false,'playback'=>true,'processing'=>false,'live'=>true,'recording'=>true,'captions'=>'provider+file10','portable_metadata'=>true);}
	public function normalize_source($source){$source=esc_url_raw($source,array('https'));$host=strtolower((string)wp_parse_url($source,PHP_URL_HOST));$id='';parse_str((string)wp_parse_url($source,PHP_URL_QUERY),$q);if(in_array($host,array('youtube.com','www.youtube.com','m.youtube.com'),true))$id=sanitize_text_field($q['v']??'');elseif('youtu.be'===$host)$id=trim((string)wp_parse_url($source,PHP_URL_PATH),'/');if(!preg_match('/^[A-Za-z0-9_-]{11}$/',$id))return VWLB_Helpers::error('vwlb_invalid_youtube',__('Invalid YouTube URL.',VWLB_TEXT_DOMAIN));return array('provider'=>'youtube','source_url'=>'https://www.youtube.com/watch?v='.$id,'embed_url'=>'https://www.youtube-nocookie.com/embed/'.$id,'provider_ref'=>$id);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['embed_url']??''),array('www.youtube-nocookie.com'));return $url?array('type'=>'iframe','url'=>$url,'sandbox'=>'allow-scripts allow-same-origin allow-presentation','captions'=>$object['captions']??array(),'autoplay'=>false):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is unavailable.',VWLB_TEXT_DOMAIN),503);}
	public function create_live($event){return apply_filters('vwlb_youtube_create_live',parent::create_live($event),$event);}
	public function issue_ingest($event){return apply_filters('vwlb_youtube_issue_ingest',parent::issue_ingest($event),$event);}
	public function verify_webhook($headers,$body){return (bool)apply_filters('vwlb_youtube_verify_webhook',false,$headers,$body);}
}
final class VWLB_Provider_Vimeo extends VWLB_Provider_Base {
	public function id(){return 'vimeo';}
	public function capabilities(){return array('upload'=>false,'playback'=>true,'processing'=>false,'live'=>true,'recording'=>true,'portable_metadata'=>true);}
	public function normalize_source($source){$source=esc_url_raw($source,array('https'));$host=strtolower((string)wp_parse_url($source,PHP_URL_HOST));$path=trim((string)wp_parse_url($source,PHP_URL_PATH),'/');if(!in_array($host,array('vimeo.com','www.vimeo.com','player.vimeo.com'),true)||!preg_match('/(?:video\/)?(\d{6,12})$/',$path,$m))return VWLB_Helpers::error('vwlb_invalid_vimeo',__('Invalid Vimeo URL.',VWLB_TEXT_DOMAIN));$id=$m[1];return array('provider'=>'vimeo','source_url'=>'https://vimeo.com/'.$id,'embed_url'=>'https://player.vimeo.com/video/'.$id,'provider_ref'=>$id);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['embed_url']??''),array('player.vimeo.com'));return $url?array('type'=>'iframe','url'=>$url,'sandbox'=>'allow-scripts allow-same-origin allow-presentation','captions'=>$object['captions']??array(),'autoplay'=>false):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is unavailable.',VWLB_TEXT_DOMAIN),503);}
	public function create_live($event){return apply_filters('vwlb_vimeo_create_live',parent::create_live($event),$event);}
	public function issue_ingest($event){return apply_filters('vwlb_vimeo_issue_ingest',parent::issue_ingest($event),$event);}
	public function verify_webhook($headers,$body){return (bool)apply_filters('vwlb_vimeo_verify_webhook',false,$headers,$body);}
}
final class VWLB_Provider_Custom extends VWLB_Provider_Base {
	public function id(){return 'custom';}
	public function capabilities(){return array('upload'=>true,'playback'=>true,'processing'=>true,'live'=>true,'recording'=>true,'failover'=>true,'portable_metadata'=>true);}
	public function normalize_source($source){$url=VWLB_Helpers::remote_url($source);return $url?array('provider'=>'custom','source_url'=>$url,'embed_url'=>'','provider_ref'=>hash('sha256',$url)):VWLB_Helpers::error('vwlb_invalid_custom_url',__('A valid HTTPS source is required.',VWLB_TEXT_DOMAIN));}
	public function create_live($event){$result=apply_filters('vwlb_custom_create_live',null,$event);if(is_wp_error($result)||is_array($result))return $result;return parent::create_live($event);}
	public function issue_ingest($event){$base=defined('VWLB_CUSTOM_INGEST_BASE')?VWLB_CUSTOM_INGEST_BASE:'';$base=VWLB_Helpers::remote_url($base);if(!$base)return parent::issue_ingest($event);return array('ingest_url'=>trailingslashit($base).rawurlencode($event['public_id']),'stream_key'=>VWLB_Providers::stream_secret(),'provider_ref'=>'custom_'.$event['public_id']);}
	public function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::remote_url($state['playback_url']??($object['source_url']??''));return $url?array('type'=>!empty($state['iframe'])?'iframe':'hls','url'=>$url,'captions'=>$object['captions']??array(),'autoplay'=>false):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);}
	public function process_asset($asset,$job){$result=apply_filters('vwlb_custom_process_asset',null,$asset,$job);return is_array($result)||is_wp_error($result)?$result:parent::process_asset($asset,$job);}
	public function verify_webhook($headers,$body){
		if(!defined('VWLB_CUSTOM_WEBHOOK_SECRET')||!VWLB_CUSTOM_WEBHOOK_SECRET)return false;
		$sig=$this->header($headers,'x-vwlb-signature');$timestamp=$this->header($headers,'x-vwlb-timestamp');
		if(!$sig||!$timestamp||!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>300)return false;
		$expected=hash_hmac('sha256',$timestamp.'.'.$body,VWLB_CUSTOM_WEBHOOK_SECRET);
		return hash_equals($expected,(string)$sig);
	}
}
final class VWLB_Providers {
	private static $providers=array();
	public static function register_defaults(){self::register(new VWLB_Provider_Local());self::register(new VWLB_Provider_YouTube());self::register(new VWLB_Provider_Vimeo());self::register(new VWLB_Provider_Custom());do_action('vwlb_register_providers',__CLASS__);}
	public static function register($provider){if($provider instanceof VWLB_Provider_Interface)self::$providers[$provider->id()]=$provider;}
	public static function get($id){$id=sanitize_key($id);return self::$providers[$id]??null;}
	public static function all(){return self::$providers;}
	public static function normalize($provider,$source){$p=self::get($provider);return $p?$p->normalize_source($source):VWLB_Helpers::error('vwlb_unknown_provider',__('Unknown media provider.',VWLB_TEXT_DOMAIN));}
	public static function select($preferred,$capability,$allow_failover=false){
		$preferred=sanitize_key($preferred);$capability=sanitize_key($capability);$p=self::get($preferred);
		if($p){$caps=$p->capabilities();if(!empty($caps[$capability])&&VWLB_Observability::provider_available($preferred,$capability))return array('provider'=>$preferred,'failover'=>false);}
		if(!$allow_failover)return VWLB_Helpers::error('vwlb_provider_unavailable',__('The selected media provider is unavailable.',VWLB_TEXT_DOMAIN),503,array('provider'=>$preferred,'capability'=>$capability));
		$order=(array)apply_filters('vwlb_provider_failover_order',array_keys(self::$providers),$capability,$preferred);
		foreach($order as $id){$id=sanitize_key($id);if($id===$preferred)continue;$candidate=self::get($id);if(!$candidate)continue;$caps=$candidate->capabilities();if(!empty($caps[$capability])&&VWLB_Observability::provider_available($id,$capability))return array('provider'=>$id,'failover'=>true,'from'=>$preferred);}
		return VWLB_Helpers::error('vwlb_provider_failover_unavailable',__('No healthy provider is available for this operation.',VWLB_TEXT_DOMAIN),503,array('provider'=>$preferred,'capability'=>$capability));
	}
	public static function portable_metadata($object){return array('title'=>VWLB_Helpers::text($object['title']??'',255),'description'=>VWLB_Helpers::textarea($object['description']??''),'language'=>VWLB_Helpers::text($object['language']??'en-US',20),'visibility'=>VWLB_Helpers::enum($object['visibility']??'private',VWLB_Contracts::VISIBILITIES,'private'),'topics'=>VWLB_Helpers::json($object['topics_json']??'{}'),'rights_status'=>VWLB_Helpers::text($object['rights_status']??'',32));}
	public static function stream_secret(){return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
	public static function hash_secret($secret){return password_hash($secret,PASSWORD_DEFAULT);}
}
