<?php
/** Versioned REST surface. Every mutation authorizes again in its command service. */
defined( 'ABSPATH' ) || exit;
final class VWLB_REST {
	public function register(){foreach(VWLB_Contracts::namespaces() as $n)$this->register_namespace($n);}
	private function register_namespace($n){
		$this->route($n,'/health','GET','health','public');
		$this->route($n,'/videos','GET','browse_videos','public');$this->route($n,'/videos','POST','create_video','submit');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)','GET','get_video','public');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/publish','POST','publish_video','publish');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/captions','POST','add_caption','submit');
		$this->route($n,'/captions/(?P<id>[A-Za-z0-9_-]+)','GET','caption','public');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/playback','GET','playback','public');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/progress','POST','progress','login');
		$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/interactions','POST','interact','login');
		$this->route($n,'/history','DELETE','clear_history','login');
		$this->route($n,'/channels','POST','create_channel','publish');$this->route($n,'/playlists','POST','create_playlist','publish');
		$this->route($n,'/playlists/(?P<id>[A-Za-z0-9_-]+)/items','PUT','playlist_items','publish');
		$this->route($n,'/media/initiate','POST','initiate_media','submit');$this->route($n,'/media/(?P<id>[A-Za-z0-9_-]+)/complete','POST','complete_media','submit');
		$this->route($n,'/live-events','GET','browse_live','public');$this->route($n,'/live-events','POST','schedule_live','broadcast');
		$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)','GET','get_live','public');
		$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/credentials','POST','issue_credential','broadcast');
		$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/transition','POST','transition_live','broadcast');
		$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/kill','POST','kill_live','moderate');
		$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/replay','POST','publish_replay','publish');
		$this->route($n,'/moderation/reports','POST','report','login');$this->route($n,'/moderation/reports/(?P<id>\d+)/decision','POST','decide_report','moderate');
		$this->route($n,'/takedowns','POST','file_takedown','public');$this->route($n,'/takedowns/(?P<id>[A-Za-z0-9_-]+)/transition','POST','transition_takedown','login');
		$this->route($n,'/webhooks/(?P<provider>[a-z0-9_-]+)','POST','webhook','public');
		$this->route($n,'/diagnostics','GET','diagnostics','diagnostics');$this->route($n,'/repair','POST','repair','diagnostics');
	}
	private function route($namespace,$path,$methods,$callback,$permission){
		$map=array('public'=>'__return_true','login'=>function(){return is_user_logged_in();},'submit'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT);},'publish'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH);},'broadcast'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST);},'moderate'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_MODERATE);},'diagnostics'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS);});
		register_rest_route($namespace,$path,array('methods'=>$methods,'callback'=>array($this,$callback),'permission_callback'=>$map[$permission]??'__return_false'));
	}
	private function body(WP_REST_Request $r){$data=$r->get_json_params();return is_array($data)?$data:array();}
	private function idem(WP_REST_Request $r){return VWLB_Helpers::text($r->get_header('Idempotency-Key'),128);}
	private function response($value,$status=200){if(is_wp_error($value))return $value;$response=rest_ensure_response($value);$response->set_status($status);$response->header('X-Sabri-File','10');$response->header('X-VWLB-Version',VWLB_VERSION);$response->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE);return $response;}
	public function health(){return $this->response(array_merge(VWLB_Diagnostics::public_health(),array('extensions'=>VWLB_Extensions::status())));}
	public function browse_videos(WP_REST_Request $r){return $this->response(VWLB_Repository::browse_videos(array('per_page'=>$r['per_page'],'cursor'=>$r['cursor'],'channel_id'=>$r['channel_id'],'language'=>$r['language'])));}
	public function get_video(WP_REST_Request $r){$bundle=VWLB_Repository::video_bundle($r['id']);$dto=VWLB_Repository::public_video_dto($bundle);if($dto)$dto['chapters']=VWLB_Extensions::chapters('video',$bundle['id']);$response=$dto?$this->response($dto):VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);if(!is_wp_error($response)&&$bundle&&'public'!==$bundle['visibility'])$response->header('Cache-Control','private, no-store');return $response;}
	public function create_video(WP_REST_Request $r){return $this->response(VWLB_Videos::create($this->body($r),$this->idem($r)),201);}
	public function publish_video(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Videos::publish($r['id'],absint($d['version']??0),$d['scheduled_at']??null));}
	public function add_caption(WP_REST_Request $r){return $this->response(VWLB_Videos::add_caption($r['id'],$this->body($r)),201);}
	public function caption(WP_REST_Request $r){$caption=VWLB_Repository::find('captions',$r['id']);if(!$caption||'published'!==$caption['status'])return VWLB_Helpers::error('vwlb_not_found',__('Caption not found.',VWLB_TEXT_DOMAIN),404);$video=VWLB_Repository::find('videos',$caption['video_id']);if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Caption not found.',VWLB_TEXT_DOMAIN),404);$response=new WP_REST_Response($caption['content'],200);$response->header('Content-Type','text/vtt; charset=UTF-8');$response->header('Cache-Control','public, max-age=300');return $response;}
	public function playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$payload['chapters']=VWLB_Extensions::chapters('video',$payload['video']['id']??0);$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=$this->response($payload);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}
	public function progress(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Videos::progress($r['id'],$d['progress_seconds']??0,$d['duration_seconds']??0));}
	public function interact(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Videos::interact($r['id'],$d['interaction']??''));}
	public function clear_history(){return $this->response(VWLB_Videos::clear_history());}
	public function create_channel(WP_REST_Request $r){return $this->response(VWLB_Videos::create_channel($this->body($r)),201);}
	public function create_playlist(WP_REST_Request $r){return $this->response(VWLB_Videos::create_playlist($this->body($r)),201);}
	public function playlist_items(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Videos::set_playlist_items($r['id'],$d['video_ids']??array(),absint($d['version']??0)));}
	public function initiate_media(WP_REST_Request $r){return $this->response(VWLB_Media::initiate($this->body($r)),201);}
	public function complete_media(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Media::complete($r['id'],$d,absint($d['version']??1)));}
	public function browse_live(WP_REST_Request $r){global $wpdb;$limit=max(1,min(24,absint($r['per_page']?:12)));$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".VWLB_Helpers::table('live_events')." WHERE visibility='public' AND status IN ('scheduled','live','interrupted','ended','recording_processing','replay_review','replay_published') AND deleted_at IS NULL ORDER BY scheduled_start DESC,id DESC LIMIT %d",$limit),ARRAY_A);$items=array();foreach($rows as $row){$dto=VWLB_Repository::public_live_dto($row);if($dto)$items[]=$dto;}return $this->response(array('items'=>$items));}
	public function schedule_live(WP_REST_Request $r){
		$d=$this->body($r);if(!array_key_exists('recording_policy',$d))$d['recording_policy']=array('record'=>false,'publish_replay'=>false,'consent_required'=>true);
		else{$d['recording_policy']=is_array($d['recording_policy'])?$d['recording_policy']:array();$d['recording_policy']['record']=!empty($d['recording_policy']['record']);$d['recording_policy']['consent_required']=array_key_exists('consent_required',$d['recording_policy'])?(bool)$d['recording_policy']['consent_required']:true;}
		$preferred=VWLB_Helpers::enum($d['provider']??'custom',array_keys(VWLB_Providers::all()),'custom');$selection=VWLB_Providers::select($preferred,'live',!empty($d['allow_provider_failover']));if(is_wp_error($selection))return $selection;$d['provider']=$selection['provider'];
		$result=VWLB_Live::schedule($d,$this->idem($r));if(!is_wp_error($result)&&!empty($result['id'])){VWLB_Extensions::schedule_live_extras($result['id'],$d);if(!empty($selection['failover'])){VWLB_Helpers::audit('live',$result['id'],'provider_failover',$preferred,$selection['provider'],'Explicit provider failover selected during scheduling.');$result['provider_failover']=$selection;}}return $this->response($result,201);
	}
	public function get_live(WP_REST_Request $r){$state=VWLB_Live::state($r['id']);if(!is_wp_error($state)){$event=VWLB_Repository::find('live_events',$r['id']);$state['experience']=VWLB_Extensions::live_extras($event);}return $this->response($state);}
	public function issue_credential(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Live::issue_credential($r['id'],$d['ttl']??21600),201);}
	public function transition_live(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Live::transition($r['id'],$d['status']??'',absint($d['version']??0),$d['note']??'',$d['provider_proof']??array()));}
	public function kill_live(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Live::kill($r['id'],absint($d['version']??0),$d['reason']??'emergency_end'));}
	public function publish_replay(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Live::publish_replay($r['id'],$d['video_id']??0,absint($d['version']??0)));}
	public function report(WP_REST_Request $r){return $this->response(VWLB_Moderation::report($this->body($r)),201);}
	public function decide_report(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Moderation::decide($r['id'],$d['action']??'',$d['note']??''));}
	public function file_takedown(WP_REST_Request $r){return $this->response(VWLB_Moderation::file_takedown($this->body($r)),201);}
	public function transition_takedown(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Moderation::transition_takedown($r['id'],$d['status']??'',absint($d['version']??0),$d['reason']??''));}
	private function header_value($headers,$name){foreach($headers as $k=>$v){if(strtolower((string)$k)===strtolower($name))return is_array($v)?(string)reset($v):(string)$v;}return '';}
	public function webhook(WP_REST_Request $r){
		$provider=VWLB_Providers::get($r['provider']);if(!$provider)return VWLB_Helpers::error('vwlb_provider_missing',__('Provider not found.',VWLB_TEXT_DOMAIN),404);
		$body=$r->get_body();if(strlen($body)>1048576)return VWLB_Helpers::error('vwlb_webhook_too_large',__('Webhook payload is too large.',VWLB_TEXT_DOMAIN),413);$headers=$r->get_headers();if(!$provider->verify_webhook($headers,$body))return VWLB_Helpers::error('vwlb_webhook_signature_invalid',__('Webhook signature verification failed.',VWLB_TEXT_DOMAIN),401);
		$data=json_decode($body,true);if(!is_array($data))return VWLB_Helpers::error('vwlb_webhook_invalid',__('Webhook payload is invalid.',VWLB_TEXT_DOMAIN));
		if('custom'===$provider->id()){
			$timestamp=$this->header_value($headers,'x-vwlb-timestamp');if(!$timestamp||!ctype_digit((string)$timestamp)||abs(time()-(int)$timestamp)>300)return VWLB_Helpers::error('vwlb_webhook_replay_window',__('Webhook timestamp is missing or outside the replay window.',VWLB_TEXT_DOMAIN),401);
		}
		$event_id=VWLB_Helpers::text($data['id']??hash('sha256',$body),191);global $wpdb;
		$inserted=$wpdb->insert(VWLB_Helpers::table('webhooks'),array('public_id'=>VWLB_Helpers::public_id('wh'),'provider'=>$provider->id(),'event_id'=>$event_id,'event_type'=>VWLB_Helpers::text($data['type']??'unknown',100),'signature_hash'=>hash('sha256',VWLB_Helpers::json_encode($headers)),'payload_hash'=>hash('sha256',$body),'payload_json'=>VWLB_Helpers::json_encode(apply_filters('vwlb_webhook_audit_payload',array('id'=>$event_id,'type'=>VWLB_Helpers::text($data['type']??'unknown',100)),$data,$provider->id())),'status'=>'received','received_at'=>VWLB_Helpers::now()));
		if(false===$inserted){return $this->response(array('accepted'=>true,'duplicate'=>true));}
		do_action('vwlb_verified_webhook',$provider->id(),$data,(int)$wpdb->insert_id);return $this->response(array('accepted'=>true),202);
	}
	public function diagnostics(){return $this->response(array_merge(VWLB_Diagnostics::full(),array('observability'=>VWLB_Observability::snapshot())));}
	public function repair(WP_REST_Request $r){return $this->response(VWLB_Diagnostics::repair($this->body($r)));}
}
