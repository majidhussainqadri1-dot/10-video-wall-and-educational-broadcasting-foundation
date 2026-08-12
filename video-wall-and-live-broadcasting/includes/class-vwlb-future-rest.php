<?php
/** REST surface for File 10 Future Video & Broadcasting Intelligence — 24 enhancements. */
defined( 'ABSPATH' ) || exit;

final class VWLB_Future_REST {
	public function register() {
		foreach ( VWLB_Contracts::namespaces() as $n ) {
			$this->route($n,'/future/capabilities','GET','capabilities','public');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/production/sources','POST','source_save','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/production/scenes','POST','scene_save','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/production/scenes/(?P<scene>[A-Za-z0-9_-]+)/program','POST','scene_program','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/guests','POST','guest_invite','broadcast');
			$this->route($n,'/broadcast-guests/(?P<id>[A-Za-z0-9_-]+)/accept','POST','guest_accept','login');
			$this->route($n,'/broadcast-guests/(?P<id>[A-Za-z0-9_-]+)/revoke','POST','guest_revoke','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/future-config','POST','live_config','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/future-config/apply','POST','live_config_apply','operate');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/simulcast-targets','POST','simulcast_save','broadcast');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/simulcast-targets/(?P<target>[A-Za-z0-9_-]+)/transition','POST','simulcast_transition','operate');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/health','GET','health','operate');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/health','POST','health_record','operate');
			$this->route($n,'/media-tracks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)','POST','track_create','publish_or_broadcast');
			$this->route($n,'/media-tracks/(?P<id>[A-Za-z0-9_-]+)/transition','POST','track_transition','review');
			$this->route($n,'/media-tracks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)/generate','POST','track_generate','publish_or_broadcast');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/annotations','GET','annotations','public');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/annotations','POST','annotation_create','publish');
			$this->route($n,'/video-annotations/(?P<id>[A-Za-z0-9_-]+)/transition','POST','annotation_transition','review');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/intelligence/suggest','POST','annotation_suggest','publish');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/transcript-index','POST','transcript_index','review');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/search-inside','GET','transcript_search','public');
			$this->route($n,'/live-events/(?P<id>[A-Za-z0-9_-]+)/polls','POST','poll_create','broadcast');
			$this->route($n,'/live-polls/(?P<id>[A-Za-z0-9_-]+)','GET','poll_get','public');
			$this->route($n,'/live-polls/(?P<id>[A-Za-z0-9_-]+)/answers','POST','poll_answer','login');
			$this->route($n,'/videos/(?P<id>[A-Za-z0-9_-]+)/consent-links','POST','consent_save','review');
			$this->route($n,'/watermarks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)','POST','watermark_save','publish_or_broadcast');
			$this->route($n,'/watermarks/(?P<object_type>video|live)/(?P<id>[A-Za-z0-9_-]+)/grant','POST','watermark_grant','public');
		}
	}

	private function route($namespace,$path,$methods,$callback,$permission){
		$map=array(
			'public'=>'__return_true',
			'login'=>function(){return is_user_logged_in();},
			'broadcast'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST);},
			'publish'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH);},
			'review'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_REVIEW);},
			'operate'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_OPERATE);},
			'publish_or_broadcast'=>function(){return VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH)||VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST);},
		);
		register_rest_route($namespace,$path,array('methods'=>$methods,'callback'=>array($this,$callback),'permission_callback'=>$map[$permission]??'__return_false'));
	}
	private function body(WP_REST_Request $r){$v=$r->get_json_params();return is_array($v)?$v:array();}
	private function response($v,$status=200){if(is_wp_error($v))return $v;$r=rest_ensure_response($v);$r->set_status($status);$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);$r->header('X-VWLB-Future','24');return $r;}
	private function version($d){return absint($d['version']??0);}

	public function capabilities(){return $this->response(array('requirements'=>VWLB_Future_Intelligence::REQUIREMENTS,'capabilities'=>VWLB_Future_Intelligence::capabilities(array()),'schema'=>VWLB_Future_Intelligence::SCHEMA));}
	public function source_save(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::upsert_source($r['id'],$this->body($r)),201);}
	public function scene_save(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::upsert_scene($r['id'],$this->body($r)),201);}
	public function scene_program(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::switch_program_scene($r['id'],$r['scene'],$this->version($d)));}
	public function guest_invite(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::invite_guest($r['id'],$d['user_id']??0,$d['role']??'guest',$d['scope']??array(),$d['ttl']??7200,$d['version']??0),201);}
	public function guest_accept(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::accept_guest($r['id']));}
	public function guest_revoke(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::revoke_guest($r['id']));}
	public function live_config(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::configure_live($r['id'],$d,$this->version($d)));}
	public function live_config_apply(WP_REST_Request $r){return $this->response(VWLB_Future_Adapters::apply_live_policy($r['id']));}
	public function simulcast_save(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::upsert_simulcast_target($r['id'],$this->body($r)),201);}
	public function simulcast_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Adapters::transition_simulcast($r['id'],$r['target'],$d['action']??'',$this->version($d)));}
	public function health(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::health_snapshot($r['id']));}
	public function health_record(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::record_health($r['id'],$this->body($r)),201);}
	public function track_create(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::create_track($r['object_type'],$r['id'],$this->body($r)),201);}
	public function track_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::transition_track($r['id'],$d['action']??'',$this->version($d)));}
	public function track_generate(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Adapters::request_track_generation($r['object_type'],$r['id'],$d['track_type']??'',$d['language']??'',$d['options']??array()),202);}
	public function annotations(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::annotations($r['id'],!empty($r['include_candidates'])));}
	public function annotation_create(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::create_annotation($r['id'],$this->body($r)),201);}
	public function annotation_transition(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::transition_annotation($r['id'],$d['action']??'',$this->version($d)));}
	public function annotation_suggest(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Adapters::suggest_annotations($r['id'],$d['kinds']??array('key_moment')),202);}
	public function transcript_index(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::index_transcript_segment($r['id'],$this->body($r)),201);}
	public function transcript_search(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::search_transcript($r['id'],$r['q']??'',$r['language']??''));}
	public function poll_create(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::create_poll($r['id'],$this->body($r)),201);}
	public function poll_get(WP_REST_Request $r){$v=VWLB_Future_Intelligence::poll($r['id'],false);return $v?$this->response($v):VWLB_Helpers::error('vwlb_not_found',__('Poll not found.',VWLB_TEXT_DOMAIN),404);}
	public function poll_answer(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Future_Intelligence::answer_poll($r['id'],$d['option_ids']??array()));}
	public function consent_save(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::upsert_consent_link($r['id'],$this->body($r)),201);}
	public function watermark_save(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::set_watermark_policy($r['object_type'],$r['id'],$this->body($r)),201);}
	public function watermark_grant(WP_REST_Request $r){$type=$r['object_type'];$object='video'===$type?VWLB_Repository::find('videos',$r['id']):VWLB_Repository::find('live_events',$r['id']);if(!$object||!VWLB_Security::can_view($object))return VWLB_Helpers::error('vwlb_not_found',__('Media not found.',VWLB_TEXT_DOMAIN),404);$response=$this->response(VWLB_Future_Intelligence::watermark_payload(array('mode'=>'off'),$type,$object,array('claims'=>VWLB_Security::claims())));if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}
}
