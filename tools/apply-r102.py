#!/usr/bin/env python3
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'video-wall-and-live-broadcasting'

def read(path): return (ROOT/path).read_text()
def write(path,text): (ROOT/path).write_text(text)
def rep(path,old,new,n=1):
    text=read(path); c=text.count(old)
    if c!=n: raise SystemExit(f'{path}: expected {n} occurrences, found {c}: {old[:100]!r}')
    write(path,text.replace(old,new,n))
def replace_all(path,old,new,min_count=1):
    text=read(path); c=text.count(old)
    if c<min_count: raise SystemExit(f'{path}: expected >= {min_count}, found {c}: {old!r}')
    write(path,text.replace(old,new))

# Central File 10 opaque public-id syntax. Native numeric IDs remain valid only inside trusted services.
rep('video-wall-and-live-broadcasting/includes/class-vwlb-helpers.php',
    "\tpublic static function public_id( $prefix ) { return sanitize_key( $prefix ) . '_' . strtolower( wp_generate_password( 24, false, false ) ); }\n",
    "\tpublic static function public_id( $prefix ) { return sanitize_key( $prefix ) . '_' . strtolower( wp_generate_password( 24, false, false ) ); }\n\tpublic static function is_public_id( $value ) { return is_string($value) && (bool) preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/', $value); }\n")

# All public File 10 REST path identifiers must be opaque prefixed IDs, never all-digit DB keys.
for path in [
    'video-wall-and-live-broadcasting/includes/class-vwlb-rest.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-extended-rest.php',
    'video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php',
]:
    for name in ('id','scene','target'):
        replace_all(path, f'(?P<{name}>[A-Za-z0-9_-]+)', f'(?P<{name}>[a-z][a-z0-9]*_[a-z0-9]+)', 0 if name != 'id' else 1)

# Core REST: resolve foreign references from opaque public IDs and reject raw/internal IDs.
core='video-wall-and-live-broadcasting/includes/class-vwlb-rest.php'
rep(core,
"\tprivate function body(WP_REST_Request $r){$data=$r->get_json_params();return is_array($data)?$data:array();}\n",
"\tprivate function body(WP_REST_Request $r){$data=$r->get_json_params();return is_array($data)?$data:array();}\n\tprivate function internal_id_error($field){return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',sprintf(__('Internal identifier field %s is not accepted on the public API. Use the opaque public identifier.',VWLB_TEXT_DOMAIN),$field),422);}\n\tprivate function entity_id($entity,$public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;$row=VWLB_Repository::find($entity,(string)$public_id);return $row?(int)$row['id']:0;}\n\tprivate function attachment_id($public_id){$public_id=VWLB_Helpers::text($public_id,100);if(!$public_id)return 0;return absint(apply_filters('vwlb_attachment_id_from_public',0,$public_id,array('consumer'=>'File 10 REST')));}\n")
rep(core,
"\tpublic function browse_videos(WP_REST_Request $r){return $this->response(VWLB_Repository::browse_videos(array('per_page'=>$r['per_page'],'cursor'=>$r['cursor'],'channel_id'=>$r['channel_id'],'language'=>$r['language'])));}\n",
"\tpublic function browse_videos(WP_REST_Request $r){if(null!==$r->get_param('channel_id'))return $this->internal_id_error('channel_id');$channel=0;if($r['channel_public_id']){$channel=$this->entity_id('channels',$r['channel_public_id']);if(!$channel)return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}return $this->response(VWLB_Repository::browse_videos(array('per_page'=>$r['per_page'],'cursor'=>$r['cursor'],'channel_id'=>$channel,'language'=>$r['language'])));}\n")
rep(core,
"\tpublic function create_video(WP_REST_Request $r){$v=VWLB_Videos::create($this->body($r),$this->idem($r));if(!is_wp_error($v)&&is_array($v))unset($v['id']);return $this->response($v,201);}\n",
"\tpublic function create_video(WP_REST_Request $r){$d=$this->body($r);foreach(array('asset_id','channel_id','thumbnail_id') as $f)if(array_key_exists($f,$d))return $this->internal_id_error($f);if(!empty($d['asset_public_id'])){$d['asset_id']=$this->entity_id('media_assets',$d['asset_public_id']);if(!$d['asset_id'])return VWLB_Helpers::error('vwlb_asset_missing',__('Asset not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['channel_public_id'])){$d['channel_id']=$this->entity_id('channels',$d['channel_public_id']);if(!$d['channel_id'])return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['thumbnail_public_id'])){$d['thumbnail_id']=$this->attachment_id($d['thumbnail_public_id']);if(!$d['thumbnail_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Thumbnail reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['asset_public_id'],$d['channel_public_id'],$d['thumbnail_public_id']);$v=VWLB_Videos::create($d,$this->idem($r));if(!is_wp_error($v)&&is_array($v))unset($v['id']);return $this->response($v,201);}\n")
rep(core,
"\tpublic function create_playlist(WP_REST_Request $r){$v=VWLB_Videos::create_playlist($this->body($r));return $this->response(is_wp_error($v)?$v:VWLB_Repository::playlist_mutation_dto($v),201);}\n",
"\tpublic function create_playlist(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('channel_id',$d))return $this->internal_id_error('channel_id');if(!empty($d['channel_public_id'])){$d['channel_id']=$this->entity_id('channels',$d['channel_public_id']);if(!$d['channel_id'])return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}unset($d['channel_public_id']);$v=VWLB_Videos::create_playlist($d);return $this->response(is_wp_error($v)?$v:VWLB_Repository::playlist_mutation_dto($v),201);}\n")
rep(core,
"\tpublic function playlist_items(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Videos::set_playlist_items($r['id'],$d['video_ids']??array(),absint($d['version']??0)));}\n",
"\tpublic function playlist_items(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('video_ids',$d))return $this->internal_id_error('video_ids');$ids=array();foreach((array)($d['video_public_ids']??array()) as $public){$id=$this->entity_id('videos',$public);if(!$id)return VWLB_Helpers::error('vwlb_video_missing',__('A playlist video was not found.',VWLB_TEXT_DOMAIN),404);$ids[]=$id;}return $this->response(VWLB_Videos::set_playlist_items($r['id'],$ids,absint($d['version']??0)));}\n")
rep(core,
"\tpublic function initiate_media(WP_REST_Request $r){$v=VWLB_Media::initiate($this->body($r));if(!is_wp_error($v)&&is_array($v))unset($v['id']);return $this->response($v,201);}\n",
"\tpublic function initiate_media(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('source_object_id',$d))return $this->internal_id_error('source_object_id');unset($d['source_object_public_id']);$v=VWLB_Media::initiate($d);if(!is_wp_error($v)&&is_array($v))unset($v['id']);return $this->response($v,201);}\n")
rep(core,
"\tpublic function complete_media(WP_REST_Request $r){$d=$this->body($r);$v=VWLB_Media::complete($r['id'],$d,absint($d['version']??1));return $this->response(is_wp_error($v)?$v:VWLB_Repository::asset_mutation_dto($v));}\n",
"\tpublic function complete_media(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('attachment_id',$d))return $this->internal_id_error('attachment_id');if(!empty($d['attachment_public_id'])){$d['attachment_id']=$this->attachment_id($d['attachment_public_id']);if(!$d['attachment_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Attachment reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['attachment_public_id']);$v=VWLB_Media::complete($r['id'],$d,absint($d['version']??1));return $this->response(is_wp_error($v)?$v:VWLB_Repository::asset_mutation_dto($v));}\n")
rep(core,
"\tpublic function schedule_live(WP_REST_Request $r){\n\t\t$d=$this->body($r);if(!array_key_exists('recording_policy',$d))",
"\tpublic function schedule_live(WP_REST_Request $r){\n\t\t$d=$this->body($r);if(array_key_exists('channel_id',$d))return $this->internal_id_error('channel_id');if(!empty($d['channel_public_id'])){$d['channel_id']=$this->entity_id('channels',$d['channel_public_id']);if(!$d['channel_id'])return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}unset($d['channel_public_id']);if(!array_key_exists('recording_policy',$d))")
rep(core,
"\tpublic function publish_replay(WP_REST_Request $r){$d=$this->body($r);$v=VWLB_Live::publish_replay($r['id'],$d['video_id']??0,absint($d['version']??0));return $this->response(is_wp_error($v)?$v:VWLB_Repository::live_mutation_dto($v));}\n",
"\tpublic function publish_replay(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('video_id',$d))return $this->internal_id_error('video_id');$video=$this->entity_id('videos',$d['video_public_id']??'');if(!$video)return VWLB_Helpers::error('vwlb_video_missing',__('Replay video not found.',VWLB_TEXT_DOMAIN),404);$v=VWLB_Live::publish_replay($r['id'],$video,absint($d['version']??0));return $this->response(is_wp_error($v)?$v:VWLB_Repository::live_mutation_dto($v));}\n")
rep(core,
"\tpublic function playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$payload['chapters']=VWLB_Extensions::chapters('video',$payload['video']['id']??0);$payload['media_tracks']=VWLB_Future_Intelligence::published_tracks('video',$payload['video']['id']??0);$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=$this->response($payload);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}\n",
"\tpublic function playback(WP_REST_Request $r){$payload=VWLB_Videos::playback($r['id']);if(!is_wp_error($payload)&&is_array($payload)){$video=VWLB_Repository::find('videos',$r['id']);$internal=$video?(int)$video['id']:0;$payload['chapters']=$internal?VWLB_Extensions::chapters('video',$internal):array();$payload['media_tracks']=$internal?VWLB_Future_Intelligence::published_tracks('video',$internal):array();$payload['preferences']=array('autoplay'=>false,'low_bandwidth'=>(bool)($r['low_bandwidth']??false),'reduced_motion'=>false);}$response=$this->response($payload);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}\n")

# Caption creation response must be opaque.
rep('video-wall-and-live-broadcasting/includes/class-vwlb-videos.php',
"\t\t$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('caption',$id,'create','',$status,'',array('source'=>$source));return array('id'=>$id,'status'=>$status,'version'=>$version);}\n",
"\t\t$id=(int)$wpdb->insert_id;$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('captions').' WHERE id=%d',$id));VWLB_Helpers::audit('caption',$id,'create','',$status,'',array('source'=>$source));return array('public_id'=>$public,'status'=>$status,'version'=>$version);}\n")

# Extended REST foreign-key normalization.
ext='video-wall-and-live-broadcasting/includes/class-vwlb-extended-rest.php'
rep(ext,
"\tprivate function body(WP_REST_Request $r){$d=$r->get_json_params();return is_array($d)?$d:array();}\n",
"\tprivate function body(WP_REST_Request $r){$d=$r->get_json_params();return is_array($d)?$d:array();}\n\tprivate function internal_id_error($field){return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',sprintf(__('Internal identifier field %s is not accepted on the public API. Use the opaque public identifier.',VWLB_TEXT_DOMAIN),$field),422);}\n\tprivate function entity_id($entity,$public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;$row=VWLB_Repository::find($entity,(string)$public_id);return $row?(int)$row['id']:0;}\n\tprivate function series_id($public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;global $wpdb;return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',(string)$public_id));}\n\tprivate function attachment_id($public_id){$public_id=VWLB_Helpers::text($public_id,100);if(!$public_id)return 0;return absint(apply_filters('vwlb_attachment_id_from_public',0,$public_id,array('consumer'=>'File 10 extended REST')));}\n")
rep(ext,
"\tpublic function upload_start(WP_REST_Request $r){return $this->response(VWLB_Extensions::initiate_resumable($this->body($r)),201);}\n",
"\tpublic function upload_start(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('source_object_id',$d))return $this->internal_id_error('source_object_id');unset($d['source_object_public_id']);return $this->response(VWLB_Extensions::initiate_resumable($d),201);}\n")
rep(ext,
"\tpublic function podcast_series_create(WP_REST_Request $r){return $this->response(VWLB_Podcasts::create_series($this->body($r)),201);}\n",
"\tpublic function podcast_series_create(WP_REST_Request $r){$d=$this->body($r);foreach(array('channel_id','artwork_id') as $f)if(array_key_exists($f,$d))return $this->internal_id_error($f);if(!empty($d['channel_public_id'])){$d['channel_id']=$this->entity_id('channels',$d['channel_public_id']);if(!$d['channel_id'])return VWLB_Helpers::error('vwlb_channel_missing',__('Channel not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['artwork_public_id'])){$d['artwork_id']=$this->attachment_id($d['artwork_public_id']);if(!$d['artwork_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Artwork reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['channel_public_id'],$d['artwork_public_id']);return $this->response(VWLB_Podcasts::create_series($d),201);}\n")
rep(ext,
"\tpublic function podcast_episode_create(WP_REST_Request $r){return $this->response(VWLB_Podcasts::create_episode($this->body($r)),201);}\n",
"\tpublic function podcast_episode_create(WP_REST_Request $r){$d=$this->body($r);foreach(array('asset_id','series_id','transcript_caption_id') as $f)if(array_key_exists($f,$d))return $this->internal_id_error($f);$d['asset_id']=$this->entity_id('media_assets',$d['asset_public_id']??'');if(!$d['asset_id'])return VWLB_Helpers::error('vwlb_asset_missing',__('Audio asset not found.',VWLB_TEXT_DOMAIN),404);if(!empty($d['series_public_id'])){$d['series_id']=$this->series_id($d['series_public_id']);if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);}if(!empty($d['transcript_caption_public_id'])){$d['transcript_caption_id']=$this->entity_id('captions',$d['transcript_caption_public_id']);if(!$d['transcript_caption_id'])return VWLB_Helpers::error('vwlb_not_found',__('Transcript caption not found.',VWLB_TEXT_DOMAIN),404);}unset($d['asset_public_id'],$d['series_public_id'],$d['transcript_caption_public_id']);return $this->response(VWLB_Podcasts::create_episode($d),201);}\n")
rep(ext,
"\tpublic function premiere_create(WP_REST_Request $r){return $this->response(VWLB_Extensions::create_premiere($this->body($r),VWLB_Helpers::text($r->get_header('Idempotency-Key'),128)),201);}\n",
"\tpublic function premiere_create(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('video_id',$d))return $this->internal_id_error('video_id');$d['video_id']=$this->entity_id('videos',$d['video_public_id']??'');if(!$d['video_id'])return VWLB_Helpers::error('vwlb_not_found',__('Premiere video not found.',VWLB_TEXT_DOMAIN),404);unset($d['video_public_id']);return $this->response(VWLB_Extensions::create_premiere($d,VWLB_Helpers::text($r->get_header('Idempotency-Key'),128)),201);}\n")
rep(ext,
"\tpublic function live_resource(WP_REST_Request $r){return $this->response(VWLB_Extensions::add_live_resource($r['id'],$this->body($r)),201);}\n",
"\tpublic function live_resource(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('attachment_id',$d))return $this->internal_id_error('attachment_id');if(!empty($d['attachment_public_id'])){$d['attachment_id']=$this->attachment_id($d['attachment_public_id']);if(!$d['attachment_id'])return VWLB_Helpers::error('vwlb_attachment_unavailable',__('Attachment reference is unavailable.',VWLB_TEXT_DOMAIN),422);}unset($d['attachment_public_id']);return $this->response(VWLB_Extensions::add_live_resource($r['id'],$d),201);}\n")
rep(ext,
"\tpublic function download_token(WP_REST_Request $r){$d=$this->body($r);return $this->response(VWLB_Extensions::create_download_token($d['object_type']??'',$d['object_id']??0,$d['ttl']??900,$d['max_downloads']??1),201);}\n",
"\tpublic function download_token(WP_REST_Request $r){$d=$this->body($r);if(array_key_exists('object_id',$d))return $this->internal_id_error('object_id');$public=$d['object_public_id']??'';if(!VWLB_Helpers::is_public_id((string)$public))return VWLB_Helpers::error('vwlb_public_identifier_required',__('A valid opaque media identifier is required.',VWLB_TEXT_DOMAIN),422);return $this->response(VWLB_Extensions::create_download_token($d['object_type']??'',$public,$d['ttl']??900,$d['max_downloads']??1),201);}\n")

# Future body refs must also be true opaque File 10 IDs.
rep('video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php',
"\t\tglobal $wpdb;$t=VWLB_Helpers::table($table);$public_id=VWLB_Helpers::text($public_id,64);if(!$public_id)return 0;\n",
"\t\tglobal $wpdb;$t=VWLB_Helpers::table($table);$public_id=VWLB_Helpers::text($public_id,64);if(!$public_id||!VWLB_Helpers::is_public_id($public_id))return 0;\n")

# Public moderation inputs use target_public_id only; raw target_id is rejected.
mod='video-wall-and-live-broadcasting/includes/class-vwlb-moderation.php'
rep(mod,
"$target=self::resolve_public_target($target_type,$data['target_id']??($data['target_public_id']??''),'report');",
"if(array_key_exists('target_id',$data))return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',__('Use target_public_id on the public API.',VWLB_TEXT_DOMAIN),422);$target=self::resolve_public_target($target_type,$data['target_public_id']??'','report');")
rep(mod,
"$target=self::resolve_public_target($target_type,$data['target_id']??($data['target_public_id']??''),'takedown');",
"if(array_key_exists('target_id',$data))return VWLB_Helpers::error('vwlb_internal_identifier_forbidden',__('Use target_public_id on the public API.',VWLB_TEXT_DOMAIN),422);$target=self::resolve_public_target($target_type,$data['target_public_id']??'','takedown');")
rep('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php',
"($body['target_id']??0)",
"($body['target_public_id']??'')")

# Podcast mutation/public DTOs must not expose native table IDs.
pod='video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
rep(pod,"return array('id'=>$id,'public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);","return array('public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);")
rep(pod,"return array('id'=>$id,'public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);","return array('public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);")
rep(pod,
"\t\treturn array(\n\t\t\t'id'=>$ep['public_id'],'series_id'=>(int)$ep['series_id'],'title'=>$ep['title'],'slug'=>$ep['slug'],",
"\t\t$series_public='';if(!empty($ep['series_id']))$series_public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('podcast_series').' WHERE id=%d LIMIT 1',(int)$ep['series_id']));\n\t\treturn array(\n\t\t\t'id'=>$ep['public_id'],'series_public_id'=>$series_public,'title'=>$ep['title'],'slug'=>$ep['slug'],")

# Prevent the generic media initializer from accepting caller-controlled native object linkage.
rep('video-wall-and-live-broadcasting/includes/class-vwlb-media.php',
"'public_id'=>$public,'owner_id'=>get_current_user_id(),'source_object_type'=>sanitize_key($data['source_object_type']??'video'),\n\t\t\t'source_object_id'=>absint($data['source_object_id']??0),",
"'public_id'=>$public,'owner_id'=>get_current_user_id(),'source_object_type'=>sanitize_key($data['source_object_type']??'video'),\n\t\t\t'source_object_id'=>0,")

# R102 regression contracts.
test='tests/file10-r101-r120-contracts.sh'
text=read(test)
insert="""
# R102 — every public route/reference must stay on the opaque public-ID boundary.
grep -F "function is_public_id" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-extended-rest.php" >/dev/null
grep -F "(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "video_public_ids" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "video_public_id" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "object_public_id" "$P/includes/class-vwlb-extended-rest.php" >/dev/null
grep -F "target_public_id" "$P/includes/class-vwlb-moderation.php" >/dev/null
! grep -F "return array('id'=>\$id,'public_id'=>\$public,'slug'=>\$slug" "$P/includes/class-vwlb-podcasts.php" >/dev/null
! grep -F "'series_id'=>(int)\$ep['series_id']" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "public_id'=>\$public,'status'=>\$status,'version'=>\$version" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "\$internal=\$video?(int)\$video['id']:0" "$P/includes/class-vwlb-rest.php" >/dev/null
"""
text=text.replace("echo 'R101-R120 contracts PASS'",insert+"\necho 'R101-R120 contracts PASS'")
write(test,text)

# Update R101 final retest evidence and record the completed R102 review/frozen findings.
ledger='docs/FILE-10-R101-R120-REVIEW-2026-09-06.md'
text=read(ledger)
text=text.replace('**Retest:** pending exact-head CI at the time of this ledger commit; the next round may begin only after it is green.',
                  '**Retest:** exact-head File 10 Release QA run `34045547009` completed successfully on PHP 8.3 and 8.4 at reviewed HEAD `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`; complete suite, R101 gate, deterministic package, checksum/archive, package/source parity and artifact publication were green before R102 began.')
text += """

## R102 — Authorization, opaque identifiers and IDOR boundary

**Review completed before correction.** Core, extended and Future REST route registration, command-service authorization, File 10 repository lookup semantics, Future `public_row()` lookup, moderation target resolution, media/video/live/podcast foreign-key inputs and public DTOs were reviewed together from the R101-green exact head.

**Frozen findings:** (1) public REST path regexes accepted all-digit values while `VWLB_Repository::find()` and Future `public_row()` deliberately interpret numeric strings as native primary keys, so public callers could address File 10 records by database ID instead of the required opaque identifier; (2) several public mutation bodies accepted native foreign keys (`channel_id`, `asset_id`, `video_ids`, replay `video_id`, podcast/series/caption IDs, download `object_id`, attachment IDs and media `source_object_id`); (3) caption and podcast creation/public episode DTOs leaked native numeric IDs; and (4) the playback REST enrichment tried to read an internal `id` from the already-redacted public video DTO, so chapters and auxiliary media tracks were looked up with object ID zero.

**Correction:** File 10 now has a single opaque-public-ID syntax guard; all REST object path identifiers require prefixed opaque IDs; public foreign references are resolved to native keys only inside the REST boundary; raw/internal ID fields are rejected; attachment references require an external public-reference resolver; moderation uses `target_public_id`; podcast/caption public DTOs no longer disclose native IDs; generic media initiation cannot accept caller-controlled native source linkage; and playback enrichment re-resolves the authorized video internally before loading chapters/tracks.

**Regression gate:** `tests/file10-r101-r120-contracts.sh` now asserts route public-ID syntax, public foreign-reference names, DTO redaction and the playback internal re-resolution contract.

**Retest:** pending exact-head CI after the R102 correction. R103 must not begin until it is green.
"""
write(ledger,text)

print('R102 correction script completed')
