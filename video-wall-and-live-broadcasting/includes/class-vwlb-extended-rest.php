<?php
/** Canonical v1.1 REST additions; registered under canonical and compatibility namespaces. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Extended_REST {
	public function register(){
		foreach(VWLB_Contracts::namespaces() as $n){
			$this->route($n,'/media/resumable','POST','upload_start','submit');
			$this->route($n,'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/chunk','PUT','upload_chunk','login');
			$this->route($n,'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete','POST','upload_complete','login');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/chapters','GET','video_chapters','public');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/chapters','POST','video_chapter_add','publish');
			$this->route($n,'/media/(?P<id>[A-Za-z0-9_-]+)/contract','GET','media_contract','public');
			$this->route($n,'/podcasts/series','POST','podcast_series_create','publish');
			$this->route($n,'/podcasts/episodes','POST','podcast_episode_create','submit');
			$this->route($n,'/podcasts/episodes/(?P<id>[A-Za-z0-9_-]+)','GET','podcast_episode','public');
			$this->route($n,'/podcasts/episodes/(?P<id>[A-Za-z0-9_-]+)/publish','POST','podcast_publish','publish');
			$this->route($n,'/podcasts/series/(?P<id>[A-Za-z0-9_-]+)/publish','POST','podcast_series_publish','publish');
			$this->route($n,'/podcasts/series/(?P<id>[A-Za-z0-9_-]+)/feed','GET','podcast_feed','public');
			$this->route($n,'/podcasts/series/(?P<id>[A-Za-z0-9_-]+)/rss','GET','podcast_rss','public');
			$this->route($n,'/premieres','POST','premiere_create','publish');
			$this->route($n,'/premieres/(?P<id>[A-Za-z0-9_-]+)','GET','premiere_get','public');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/waiting-room','POST','waiting_room','login');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/recording-consent','POST','recording_consent','login');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/questions','POST','live_question','login');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/resources','POST','live_resource','broadcast');
			$this->route($n,'/live-questions/(?P<id>\d+)/moderate','POST','live_question_moderate','moderate');
			$this->route($n,'/downloads','POST','download_token','login');
			$this->route($n,'/downloads/(?P<id>[A-Za-z0-9_-]+)/resolve','POST','download_resolve','login');
			$this->route($n,'/creator/studio','GET','creator_studio','submit');
			$this->route($n,'/creator/insights','GET','creator_insights','submit');
			$this->route($n,'/operations/observability','GET','observability','diagnostics');
		}
	}
	private function route($namespace,$path,$methods,$callback,$permission){
		$map=array(
			'public'=>'__return_true',
			'login'=>function(){return is_user_logged_in();},
			'submit'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT);},
			'publish'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH);},
			'broadcast'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST);},
			'moderate'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_MODERATE);},
			'diagnostics'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_DIAGNOSTICS);},
		);
		register_rest_route($namespace,$path,array('methods'=>$methods,'callback'=>array($this,$callback),'permission_callback'=>$map[$permission]??'__return_false'));
	}
	private function body(WP_REST_Request $r){$d=$r->get_json_params();return is_array($d)?$d:array();}
	private function response($v,$status=200){
		if(is_wp_error($v))return $v;$r=rest_ensure_response($v);$r->set_status($status);
		$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);$r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE);
		return $r;
	}
	private function token(WP_REST_Request $r){return VWLB_Helpers::text($r->get_header('X-VWLB-Upload-Token')?:($this->body($r)['upload_token']??''),200);}
	public function upload_start(WP_REST_Request $r){return $this->response(VWLB_Extensions::initiate_resumable($this->body($r)),201);}
	public function upload_chunk(WP_REST_Request $r){
		$offset=(int)($r->get_header('Upload-Offset')?:0);$sha=VWLB_Helpers::text($r->get_header('X-Chunk-SHA256'),128);
		return $this->response(VWLB_Extensions::append_chunk($r['id'],$this->token($r),$offset,$r->get_body(),$sha));
	}
	public function upload_complete(WP_REST_Request $r){return $this->response(VWLB_Extensions::complete_resumable($r['id'],$this->token($r)));}
	public function video_chapters(WP_REST_Request $r){
		$v=VWLB_Repository::find('videos',$r['id']);if(!$v||!VWLB_Security::can_view($v))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);
		return $this->response(array('items'=>VWLB_Extensions::chapters('video',$v['id'])));
	}
	public function video_chapter_add(WP_REST_Request $r){$v=VWLB_Repository::find('videos',$r['id']);if(!$v)return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);return $this->response(VWLB_Extensions::add_chapter('video',$v['id'],$this->body($r)),201);}
	public function media_contract(WP_REST_Request $r){return $this->response(VWLB_Extensions::media_contract(null,$r['id'],VWLB_Helpers::text($r['consumer']??'public',64)));}
	public function podcast_series_create(WP_REST_Request $r){return $this->response(VWLB_Podcasts::create_series($this->body($r)),201);}
	public function podcast_episode_create(WP_REST_Request $r){return $this->response(VWLB_Podcasts::create_episode($this->body($r)),201);}
	public function podcast_episode(WP_REST_Request $r){$dto=VWLB_Podcasts::public_episode_dto($r['id']);return $dto?$this->response($dto):VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);}
	public function podcast_publish(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Podcasts::publish_episode($r['id'],absint($d['version']??0)));}
	public function podcast_series_publish(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Podcasts::publish_series($r['id'],absint($d['version']??0)));}
	public function podcast_feed(WP_REST_Request $r){return $this->response(VWLB_Podcasts::feed($r['id']));}
	public function podcast_rss(WP_REST_Request $r){$xml=VWLB_Podcasts::rss_xml($r['id']);if(is_wp_error($xml))return $xml;$response=new WP_REST_Response($xml,200);$response->header('Content-Type','application/rss+xml; charset=UTF-8');$response->header('Cache-Control','public, max-age=300');return $response;}
	public function premiere_create(WP_REST_Request $r){return $this->response(VWLB_Extensions::create_premiere($this->body($r),VWLB_Helpers::text($r->get_header('Idempotency-Key'),128)),201);}
	public function premiere_get(WP_REST_Request $r){$p=VWLB_Extensions::premiere($r['id']);return $p?$this->response($p):VWLB_Helpers::error('vwlb_not_found',__('Premiere not found.',VWLB_TEXT_DOMAIN),404);}
	public function waiting_room(WP_REST_Request $r){return $this->response(VWLB_Extensions::join_waiting_room($r['id'],$this->body($r)));}
	public function recording_consent(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::set_recording_consent($r['id'],!empty($d['consent']),$d['consent_version']??'v1'));}
	public function live_question(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::ask_question($r['id'],$d['question']??''),201);}
	public function live_resource(WP_REST_Request $r){return $this->response(VWLB_Extensions::add_live_resource($r['id'],$this->body($r)),201);}
	public function live_question_moderate(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::moderate_question($r['id'],$d['status']??'',$d['answer']??''));}
	public function download_token(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::create_download_token($d['object_type']??'',$d['object_id']??0,$d['ttl']??900,$d['max_downloads']??1),201);}
	public function download_resolve(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::resolve_download($r['id'],$d['download_token']??''));}
	public function creator_studio(){return $this->response(VWLB_Extensions::creator_studio());}
	public function creator_insights(WP_REST_Request $r){return $this->response(VWLB_Extensions::creator_insights($r['days']??30));}
	public function observability(){return $this->response(VWLB_Observability::snapshot());}
}
