<?php
/** Canonical v1.1 REST additions; registered under canonical and compatibility namespaces. */
defined( 'ABSPATH' ) || exit;
final class VWLB_Extended_REST {
	public function register(){
		foreach(VWLB_Contracts::namespaces() as $n){
			$this->route($n,'/media/resumable','POST','upload_start','submit');
			$this->route($n,'/media/resumable/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/chunk','PUT','upload_chunk','login');
			$this->route($n,'/media/resumable/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/complete','POST','upload_complete','login');
			$this->route($n,'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/chapters','GET','video_chapters','public');
			$this->route($n,'/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/chapters','POST','video_chapter_add','publish');
			$this->route($n,'/media/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/contract','GET','media_contract','public');
			$this->route($n,'/podcasts/series','POST','podcast_series_create','publish');
			$this->route($n,'/podcasts/episodes','POST','podcast_episode_create','submit');
			$this->route($n,'/podcasts/episodes/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)','GET','podcast_episode','public');
			$this->route($n,'/podcasts/episodes/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/publish','POST','podcast_publish','publish');
			$this->route($n,'/podcasts/series/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/publish','POST','podcast_series_publish','publish');
			$this->route($n,'/podcasts/series/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/feed','GET','podcast_feed','public');
			$this->route($n,'/podcasts/series/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/rss','GET','podcast_rss','public');
			$this->route($n,'/premieres','POST','premiere_create','publish');
			$this->route($n,'/premieres/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)','GET','premiere_get','public');
			$this->route($n,'/live-events/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/waiting-room','POST','waiting_room','login');
			$this->route($n,'/live-events/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/recording-consent','POST','recording_consent','login');
			$this->route($n,'/live-events/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/questions','POST','live_question','login');
			$this->route($n,'/live-events/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/resources','POST','live_resource','broadcast');
			$this->route($n,'/live-questions/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/moderate','POST','live_question_moderate','moderate');
			$this->route($n,'/downloads','POST','download_token','login');
			$this->route($n,'/downloads/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/resolve','POST','download_resolve','login');
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
	private function internal_id_error($field){return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',sprintf(__('Internal identifier field %s is not accepted on the public API. Use the opaque public identifier.',VWLB_TEXT_DOMAIN),$field),422);}
	private function entity_id($entity,$public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;$row=VWLB_Repository::find($entity,(string)$public_id);return $row?(int)$row['id']:0;}
	private function series_id($public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;global $wpdb;$id=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',(string)$public_id),'r119_podcast_series_resolver');if(is_wp_error($id))return $id;return (int)$id;}
	private function attachment_id($public_id){$public_id=VWLB_Helpers::text($public_id,100);if(!$public_id)return 0;return absint(apply_filters('vwlb_attachment_id_from_public',0,$public_id,array('consumer'=>'File 10 extended REST')));}
	private function response($v,$status=200){
		if(is_wp_error($v))return $v;$r=rest_ensure_response($v);$r->set_status($status);
		$r->header('X-Sabri-File','10');$r->header('X-VWLB-Version',VWLB_VERSION);$r->header('X-VWLB-Canonical-API',VWLB_Contracts::CANONICAL_API_NAMESPACE);
		return $r;
	}
	private function token(WP_REST_Request $r){return VWLB_Helpers::text($r->get_header('X-VWLB-Upload-Token')?:($this->body($r)['upload_token']??''),200);}
	public function upload_start(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('source_object_id',$d))return $this->internal_id_error('source_object_id');unset($d['source_object_public_id']);return $this->response(VWLB_Extensions::initiate_resumable($d),201);}
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
	public function podcast_series_create(WP_REST_Request $r){$d=$this->body($r);foreach(array('channel_id','artwork_id') as $f)if(array_key_exists($f,$d))return $this->internal_id_error($f);if(!empty($d['channel_public_id'])){$d['channel_id']=$this->entity_id('channels',$d['channel_public_id']);if(!$d['channel_id'])return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['artwork_public_id'])){$d['artwork_id']=$this->attachment_id($d['artwork_public_id']);if(!$d['artwork_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Artwork reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['channel_public_id'],$d['artwork_public_id']);return $this->response(VWLB_Podcasts::create_series($d),201);}
	public function podcast_episode_create(WP_REST_Request $r){$d=$this->body($r);foreach(array('asset_id','series_id','transcript_caption_id') as $f)if(array_key_exists($f,$d))return $this->internal_id_error($f);$d['asset_id']=$this->entity_id('media_assets',$d['asset_public_id']??'');if(!$d['asset_id'])return VWLB_Helpers::error('vwlb_asset_missing',__('Audio asset not found.',VWLB_TEXT_DOMAIN),404);if(!empty($d['series_public_id'])){$d['series_id']=$this->series_id($d['series_public_id']);if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['transcript_caption_public_id'])){$d['transcript_caption_id']=$this->entity_id('captions',$d['transcript_caption_public_id']);if(!$d['transcript_caption_id'])return VWLB_Helpers::error('vwlb_not_found',__('Transcript caption not found.',VWLB_TEXT_DOMAIN),404);}unset($d['asset_public_id'],$d['series_public_id'],$d['transcript_caption_public_id']);return $this->response(VWLB_Podcasts::create_episode($d),201);}
	public function podcast_episode(WP_REST_Request $r){$dto=VWLB_Podcasts::public_episode_dto($r['id']);return $dto?$this->response($dto):VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);}
	public function podcast_publish(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Podcasts::publish_episode($r['id'],absint($d['version']??0)));}
	public function podcast_series_publish(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Podcasts::publish_series($r['id'],absint($d['version']??0)));}
	public function podcast_feed(WP_REST_Request $r){return $this->response(VWLB_Podcasts::feed($r['id']));}
	public function podcast_rss(WP_REST_Request $r){$xml=VWLB_Podcasts::rss_xml($r['id']);if(is_wp_error($xml))return $xml;$response=new WP_REST_Response($xml,200);$response->header('Content-Type','application/rss+xml; charset=UTF-8');$response->header('Cache-Control','public, max-age=300');return $response;}
	public function premiere_create(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('video_id',$d))return $this->internal_id_error('video_id');$d['video_id']=$this->entity_id('videos',$d['video_public_id']??'');if(!$d['video_id'])return VWLB_Helpers::error('vwlb_not_found',__('Premiere video not found.',VWLB_TEXT_DOMAIN),404);unset($d['video_public_id']);return $this->response(VWLB_Extensions::create_premiere($d,VWLB_Helpers::text($r->get_header('Idempotency-Key'),128)),201);}
	public function premiere_get(WP_REST_Request $r){$p=VWLB_Extensions::premiere($r['id']);return $p?$this->response($p):VWLB_Helpers::error('vwlb_not_found',__('Premiere not found.',VWLB_TEXT_DOMAIN),404);}
	public function waiting_room(WP_REST_Request $r){return $this->response(VWLB_Extensions::join_waiting_room($r['id'],$this->body($r)));}
	public function recording_consent(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::set_recording_consent($r['id'],!empty($d['consent']),$d['consent_version']??'v1'));}
	public function live_question(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::ask_question($r['id'],$d['question']??''),201);}
	public function live_resource(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('attachment_id',$d))return $this->internal_id_error('attachment_id');if(!empty($d['attachment_public_id'])){$d['attachment_id']=$this->attachment_id($d['attachment_public_id']);if(!$d['attachment_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Attachment reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['attachment_public_id']);return $this->response(VWLB_Extensions::add_live_resource($r['id'],$d),201);}
	public function live_question_moderate(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::moderate_question($r['id'],$d['status']??'',$d['answer']??''));}
	public function download_token(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('object_id',$d))return $this->internal_id_error('object_id');$public=$d['object_public_id']??'';if(!VWLB_Helpers::is_public_id((string)$public))return VWLB_Helpers::error('vwlb_public_identifier_required',__('A valid opaque media identifier is required.',VWLB_TEXT_DOMAIN),422);return $this->response(VWLB_Extensions::create_download_token($d['object_type']??'',$public,$d['ttl']??900,$d['max_downloads']??1),201);}
	public function download_resolve(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::resolve_download($r['id'],$d['download_token']??''));}
	public function creator_studio(){return $this->response(VWLB_Extensions::creator_studio());}
	public function creator_insights(WP_REST_Request $r){return $this->response(VWLB_Extensions::creator_insights($r['days']??30));}
	public function observability(){return $this->response(VWLB_Observability::snapshot());}
}
