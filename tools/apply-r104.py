#!/usr/bin/env python3
from pathlib import Path
import json, re

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'video-wall-and-live-broadcasting'
OLD='1.2.14-rc1'; NEW='1.2.15-rc1'

def read(path): return (ROOT/path).read_text()
def write(path,text): (ROOT/path).write_text(text)
def must_replace(text, old, new, label):
    if old not in text:
        raise SystemExit(f'missing replacement anchor: {label}')
    return text.replace(old,new,1)
def replace_function(text, name, body, label):
    pattern = rf"\tpublic static function {re.escape(name)}\s*\([^\n]*\)\s*\{{.*?\n\t\}}(?=\n\n\t(?:public|private) static function|\n\}})"
    new, n = re.subn(pattern, body.rstrip(), text, count=1, flags=re.S)
    if n != 1:
        raise SystemExit(f'function replacement failed {label}: {n}')
    return new

# 1) Typed, immediate DB-read truth helpers: callers can propagate a 503 before a later query erases wpdb->last_error.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-db.php'); text=read(path)
anchor="\tpublic static function schema_sql() {"
helpers=r'''\tprivate static function read_error( $context ) {
\t\t$context=sanitize_key((string)$context);do_action('vwlb_operational_failure','database','vwlb_database_read_failed',array('context'=>$context));
\t\treturn VWLB_Helpers::error('vwlb_database_read_failed',__('File 10 could not verify the requested database state safely.',VWLB_TEXT_DOMAIN),503,array('context'=>$context));
\t}
\tpublic static function read_results( $query, $context='database_read' ) {
\t\tglobal $wpdb;$wpdb->last_error='';$rows=$wpdb->get_results((string)$query,ARRAY_A);if(''!==(string)$wpdb->last_error)return self::read_error($context);return is_array($rows)?$rows:array();
\t}
\tpublic static function read_row( $query, $context='database_read' ) {
\t\tglobal $wpdb;$wpdb->last_error='';$row=$wpdb->get_row((string)$query,ARRAY_A);if(''!==(string)$wpdb->last_error)return self::read_error($context);return is_array($row)?$row:null;
\t}
\tpublic static function read_var( $query, $context='database_read' ) {
\t\tglobal $wpdb;$wpdb->last_error='';$value=$wpdb->get_var((string)$query);if(''!==(string)$wpdb->last_error)return self::read_error($context);return $value;
\t}

'''
if 'public static function read_results' not in text:
    text=must_replace(text,anchor,helpers+anchor,'db-read-helpers')
write(path,text)

# 2) Public/read helper surfaces used outside REST must propagate read errors instead of empty/404 partial state.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'); text=read(path)
chapters=r'''\tpublic static function chapters( $object_type, $object_id ) {
\t\tglobal $wpdb;$query=$wpdb->prepare(
\t\t\t'SELECT public_id,start_seconds,end_seconds,title,summary,source FROM '.VWLB_Helpers::table('chapters').' WHERE object_type=%s AND object_id=%d AND status=%s ORDER BY start_seconds ASC,id ASC LIMIT 500',
\t\t\tsanitize_key($object_type),absint($object_id),'published'
\t\t);return VWLB_DB::read_results($query,'chapters');
\t}'''
text=replace_function(text,'chapters',chapters,'chapters')
live_extras=r'''\tpublic static function live_extras( $event ) {
\t\tif(!$event)return array();global $wpdb;$eid=(int)$event['id'];
\t\t$resources=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,title,resource_type,url,rights_status FROM '.VWLB_Helpers::table('live_resources').' WHERE live_event_id=%d AND status=%s ORDER BY id ASC LIMIT 100',$eid,'published'),'live_extras_resources');if(is_wp_error($resources))return $resources;
\t\t$questions=array();if(VWLB_Security::can(VWLB_Contracts::CAP_MODERATE,$event,'view_live_questions')){$questions=VWLB_DB::read_results($wpdb->prepare("SELECT public_id,question,status,answer,created_at FROM ".VWLB_Helpers::table('live_questions')." WHERE live_event_id=%d ORDER BY id ASC LIMIT 200",$eid),'live_extras_questions');if(is_wp_error($questions))return $questions;}
\t\t$attendee=null;if(is_user_logged_in()){$attendee=VWLB_DB::read_row($wpdb->prepare('SELECT public_id,state,reminder_minutes,recording_consent,consent_version,consented_at FROM '.VWLB_Helpers::table('live_attendees').' WHERE live_event_id=%d AND user_id=%d',$eid,get_current_user_id()),'live_extras_viewer');if(is_wp_error($attendee))return $attendee;}
\t\treturn array('capacity'=>self::event_capacity($event),'resources'=>$resources,'questions'=>$questions,'viewer'=>$attendee);
\t}'''
text=replace_function(text,'live_extras',live_extras,'live-extras')
premiere=r'''\tpublic static function premiere( $id ) {
\t\tglobal $wpdb;$row=VWLB_DB::read_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('premieres').' WHERE public_id=%s LIMIT 1',VWLB_Helpers::text($id,64)),'premiere');if(is_wp_error($row))return $row;if(!$row)return null;
\t\t$video=VWLB_Repository::find('videos',$row['video_id']);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Premiere video state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$live=VWLB_Live::state($row['live_event_id']);if(!$video||is_wp_error($live)||!VWLB_Security::can_view($video,'premiere'))return is_wp_error($live)?$live:null;
\t\treturn array('id'=>$row['public_id'],'status'=>$row['status'],'scheduled_at'=>VWLB_Helpers::iso_utc($row['scheduled_at']),'video'=>VWLB_Repository::public_video_dto(VWLB_Repository::video_bundle($video['id'])),'live'=>$live,'discussion_owner'=>'File 17 contextual bridge / File 10 moderation policy');
\t}'''
text=replace_function(text,'premiere',premiere,'premiere')
insights=r'''\tpublic static function creator_insights( $days=30 ) {
\t\tif(!VWLB_Security::can(VWLB_Contracts::CAP_SUBMIT,null,'creator_insights'))return VWLB_Helpers::error('vwlb_forbidden',__('Creator insights are restricted.',VWLB_TEXT_DOMAIN),403);
\t\t$days=max(1,min(365,(int)$days));global $wpdb;$table=VWLB_Helpers::table('creator_metrics_daily');$since=gmdate('Y-m-d',time()-($days-1)*DAY_IN_SECONDS);
\t\t$rows=VWLB_DB::read_results($wpdb->prepare("SELECT metric_date,SUM(views) views,SUM(completions) completions,SUM(saves) saves,SUM(source_opens) source_opens,SUM(meaningful_comments) meaningful_comments,SUM(harm_reports) harm_reports FROM $table WHERE owner_id=%d AND metric_date>=%s GROUP BY metric_date ORDER BY metric_date ASC",get_current_user_id(),$since),'creator_insights');if(is_wp_error($rows))return $rows;
\t\t$totals=array('views'=>0,'completions'=>0,'saves'=>0,'source_opens'=>0,'meaningful_comments'=>0,'harm_reports'=>0);foreach($rows as $r)foreach($totals as $k=>$v)$totals[$k]+=(int)$r[$k];$totals['completion_rate']=$totals['views']?round($totals['completions']/$totals['views'],4):0;
\t\treturn array('days'=>$days,'totals'=>$totals,'series'=>$rows,'privacy'=>'aggregate-only','ranking_guardrail'=>'watch-time-alone-is-not-a-quality-score');
\t}'''
text=replace_function(text,'creator_insights',insights,'creator-insights')
old="\t\t$asset=$video['asset']??array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');\n\t\treturn array("
new="\t\t$asset=$video['asset']??array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$chapters=self::chapters('video',$video['id']);if(is_wp_error($chapters))return $chapters;\n\t\treturn array("
text=must_replace(text,old,new,'media-contract-chapter-preflight')
text=must_replace(text,"'chapters'=>self::chapters('video',$video['id']),","'chapters'=>$chapters,",'media-contract-chapters')
write(path,text)

# 3) Podcast DTO/feed: immediate DB truth plus the same unlisted can_view policy as individual reads.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'); text=read(path)
public_dto=r'''\tpublic static function public_episode_dto($id){
\t\tglobal $wpdb;if(!VWLB_Helpers::is_public_id((string)$id))return null;$wpdb->last_error='';$ep=self::episode($id,false);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast episode state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$ep)return null;
\t\t$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast media state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');
\t\t$series_public='';if(!empty($ep['series_id'])){$series_public=VWLB_DB::read_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('podcast_series').' WHERE id=%d LIMIT 1',(int)$ep['series_id']),'podcast_series_projection');if(is_wp_error($series_public))return $series_public;$series_public=(string)$series_public;}
\t\t$chapters=VWLB_Extensions::chapters('podcast',$ep['id']);if(is_wp_error($chapters))return $chapters;
\t\treturn array('id'=>$ep['public_id'],'series_public_id'=>$series_public,'title'=>$ep['title'],'slug'=>$ep['slug'],'description'=>$ep['description'],'language'=>$ep['language'],'duration_seconds'=>(int)$ep['duration_seconds'],'rights_status'=>$ep['rights_status'],'visibility'=>$ep['visibility'],'published_at'=>VWLB_Helpers::iso_utc($ep['published_at']),'audio_url'=>esc_url_raw($der['audio_only']??$der['mp3']??$der['mp4_low']??''),'download_available'=>(bool)$ep['download_allowed'],'transcript'=>($ep['transcript_status']??'')==='published'?(string)($ep['transcript_text']??''):'','chapters'=>$chapters);
\t}'''
text=replace_function(text,'public_episode_dto',public_dto,'podcast-public-dto')
feed=r'''\tpublic static function feed($series_id){
\t\tglobal $wpdb;$series=VWLB_DB::read_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE (id=%d OR public_id=%s) AND status=%s AND deleted_at IS NULL LIMIT 1',absint($series_id),VWLB_Helpers::text($series_id,64),'published'),'podcast_feed_series');if(is_wp_error($series))return $series;
\t\tif(!$series||!VWLB_Security::can_view($series,'podcast_feed'))return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);
\t\t$episodes=VWLB_DB::read_results($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE series_id=%d AND status=%s AND deleted_at IS NULL ORDER BY published_at DESC,id DESC LIMIT 200',$series['id'],'published'),'podcast_feed_episodes');if(is_wp_error($episodes))return $episodes;
\t\t$items=array();foreach($episodes as $ep){if(!VWLB_Security::can_view($ep,'podcast_feed'))continue;$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);if(VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast media state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$asset)continue;$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');$items[]=array('id'=>$ep['public_id'],'title'=>$ep['title'],'description'=>$ep['description'],'published_at'=>VWLB_Helpers::iso_utc($ep['published_at']),'duration_seconds'=>(int)$ep['duration_seconds'],'audio_url'=>esc_url_raw($der['audio_only']??$der['mp3']??''));}
\t\treturn array('contract'=>'File10PodcastFeed.v1','series'=>array('id'=>$series['public_id'],'title'=>$series['title'],'description'=>$series['description'],'language'=>$series['language'],'visibility'=>$series['visibility']),'episodes'=>$items,'rss_ready'=>true,'canonical_owner'=>'File 10');
\t}'''
text=replace_function(text,'feed',feed,'podcast-feed')
write(path,text)

# 4) Existing R78 delivery layer is canonical for caption cache privacy; strengthen only its podcast unlisted authorization path.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-r78-public-delivery-guard.php'); text=read(path)
old="if(!$series)return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);$wpdb->last_error='';"
new="if(!$series||!VWLB_Security::can_view($series,'podcast_feed'))return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);$wpdb->last_error='';"
text=must_replace(text,old,new,'r78-series-auth')
old="$items=array();foreach((array)$episodes as $ep){$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);"
new="$items=array();foreach((array)$episodes as $ep){if(!VWLB_Security::can_view($ep,'podcast_feed'))continue;$asset=VWLB_Repository::find('media_assets',$ep['asset_id']);"
text=must_replace(text,old,new,'r78-episode-auth')
write(path,text)

# 5) Frontend route rendering must distinguish DB failure from genuine empty/not-found state.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php'); text=read(path)
text=must_replace(text,"\t\t$this->enqueue();$data=VWLB_Repository::browse_videos(array('per_page'=>12));","\t\t$this->enqueue();VWLB_Repository::reset_read_failure();$data=VWLB_Repository::browse_videos(array('per_page'=>12));if(VWLB_Repository::read_failed())return $this->state('restricted',__('Video service unavailable',VWLB_TEXT_DOMAIN),__('The Video Wall could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);",'frontend-wall-repo')
old="$lives=$wpdb->get_results(\"SELECT public_id,title,scheduled_start,status FROM \".VWLB_Helpers::table('live_events').\" WHERE visibility='public' AND status IN ('scheduled','live') AND deleted_at IS NULL ORDER BY scheduled_start ASC LIMIT 8\",ARRAY_A);"
new="$lives=VWLB_DB::read_results(\"SELECT public_id,title,scheduled_start,status FROM \".VWLB_Helpers::table('live_events').\" WHERE visibility='public' AND status IN ('scheduled','live') AND deleted_at IS NULL ORDER BY scheduled_start ASC LIMIT 8\",'frontend_wall_live');if(is_wp_error($lives))return $this->state('restricted',__('Video service unavailable',VWLB_TEXT_DOMAIN),__('Live listings could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);"
text=must_replace(text,old,new,'frontend-wall-live')
text=must_replace(text,"$chapters=$raw?VWLB_Extensions::chapters('video',$raw['id']):array();$low='';","$chapters=$raw?VWLB_Extensions::chapters('video',$raw['id']):array();if(is_wp_error($chapters))return $this->state('restricted',__('Video service unavailable',VWLB_TEXT_DOMAIN),__('Video chapter state could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);$low='';",'frontend-video-chapters')
text=must_replace(text,"$raw=VWLB_Repository::find('live_events',$id);$extras=VWLB_Extensions::live_extras($raw);","$raw=VWLB_Repository::find('live_events',$id);if(VWLB_Repository::read_failed())return $this->state('restricted',__('Live service unavailable',VWLB_TEXT_DOMAIN),__('Live event state could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);$extras=VWLB_Extensions::live_extras($raw);if(is_wp_error($extras))return $this->state('restricted',__('Live service unavailable',VWLB_TEXT_DOMAIN),__('Live participation state could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);",'frontend-live-extras')
old="$channel=$slug?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('channels').' WHERE slug=%s AND status=%s LIMIT 1',$slug,'active'),ARRAY_A):null;"
new="$channel=$slug?VWLB_DB::read_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('channels').' WHERE slug=%s AND status=%s LIMIT 1',$slug,'active'),'frontend_channel'):null;if(is_wp_error($channel))return $this->state('restricted',__('Channel service unavailable',VWLB_TEXT_DOMAIN),__('Channel state could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);"
text=must_replace(text,old,new,'frontend-channel-read')
text=must_replace(text,"$videos=VWLB_Repository::browse_videos(array('per_page'=>24,'channel_id'=>$channel['id']));","VWLB_Repository::reset_read_failure();$videos=VWLB_Repository::browse_videos(array('per_page'=>24,'channel_id'=>$channel['id']));if(VWLB_Repository::read_failed())return $this->state('restricted',__('Channel service unavailable',VWLB_TEXT_DOMAIN),__('Channel videos could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);",'frontend-channel-videos')
old="$this->enqueue();global $wpdb;$rows=$wpdb->get_results(\"SELECT public_id,title,slug,description,language FROM \".VWLB_Helpers::table('podcast_series').\" WHERE status='published' AND visibility='public' AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 100\",ARRAY_A);"
new="$this->enqueue();global $wpdb;$rows=VWLB_DB::read_results(\"SELECT public_id,title,slug,description,language FROM \".VWLB_Helpers::table('podcast_series').\" WHERE status='published' AND visibility='public' AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 100\",'frontend_podcast_series');if(is_wp_error($rows))return $this->state('restricted',__('Podcast service unavailable',VWLB_TEXT_DOMAIN),__('Podcast listings could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);"
text=must_replace(text,old,new,'frontend-podcasts')
text=must_replace(text,"$ep=$id?VWLB_Podcasts::public_episode_dto($id):null;if(!$ep)return $this->state('restricted'","$ep=$id?VWLB_Podcasts::public_episode_dto($id):null;if(is_wp_error($ep))return $this->state('restricted',__('Podcast service unavailable',VWLB_TEXT_DOMAIN),__('Podcast state could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);if(!$ep)return $this->state('restricted'",'frontend-podcast-error')
old="$rows=$wpdb->get_results($wpdb->prepare('SELECT s.progress_seconds,s.completed,s.updated_at,v.public_id,v.slug,v.title FROM '.VWLB_Helpers::table('playback_sessions').' s JOIN '.VWLB_Helpers::table('videos').' v ON v.id=s.object_id WHERE s.user_id=%d AND s.object_type=%s ORDER BY s.updated_at DESC LIMIT 100',get_current_user_id(),'video'),ARRAY_A);"
new="$rows=VWLB_DB::read_results($wpdb->prepare('SELECT s.progress_seconds,s.completed,s.updated_at,v.public_id,v.slug,v.title FROM '.VWLB_Helpers::table('playback_sessions').' s JOIN '.VWLB_Helpers::table('videos').' v ON v.id=s.object_id WHERE s.user_id=%d AND s.object_type=%s ORDER BY s.updated_at DESC LIMIT 100',get_current_user_id(),'video'),'frontend_history');if(is_wp_error($rows))return $this->state('restricted',__('History unavailable',VWLB_TEXT_DOMAIN),__('Watch history could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN),503);"
text=must_replace(text,old,new,'frontend-history')
write(path,text)

# 6) Future frontend and reviewed intelligence reads must also expose unavailable state, not an empty candidate/poll list.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-safety.php'); text=read(path)
old="$payload['chapters']=VWLB_Extensions::chapters('video',$payload['video']['id']??0);$payload['media_tracks']=self::published_tracks('video',$payload['video']['id']??0);$payload['preferences']="
new="$chapters=VWLB_Extensions::chapters('video',$payload['video']['id']??0);if(is_wp_error($chapters))return $chapters;$tracks=self::published_tracks('video',$payload['video']['id']??0);if(is_wp_error($tracks))return $tracks;$payload['chapters']=$chapters;$payload['media_tracks']=$tracks;$payload['preferences']="
text=must_replace(text,old,new,'future-rest-playback-read-propagation')
old="$state['experience']=VWLB_Extensions::live_extras($event);$state['media_tracks']=$event?self::published_tracks('live',$event['id']):array();"
new="$experience=VWLB_Extensions::live_extras($event);if(is_wp_error($experience))return $experience;$tracks=$event?self::published_tracks('live',$event['id']):array();if(is_wp_error($tracks))return $tracks;$state['experience']=$experience;$state['media_tracks']=$tracks;"
text=must_replace(text,old,new,'future-rest-live-read-propagation')
published_tracks=r'''\tpublic static function published_tracks($object_type,$object_id){
\t\t$object_type=VWLB_Helpers::enum($object_type,array('video','live'),'');$object='video'===$object_type?self::video($object_id):('live'===$object_type?self::live($object_id):null);if(!$object||!VWLB_Security::can_view($object))return array();
\t\tglobal $wpdb;$rows=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,track_type,language,source,file_ref,provider_ref,version FROM '.VWLB_Helpers::table('media_tracks').' WHERE object_type=%s AND object_id=%d AND status=%s ORDER BY track_type ASC, language ASC, id ASC LIMIT 100',$object_type,(int)$object['id'],'published'),'future_published_tracks');if(is_wp_error($rows))return $rows;$out=array();
\t\tforeach($rows as $row){$resolved=apply_filters('vwlb_public_media_track_ref','',$row,$object);$src=esc_url_raw(is_string($resolved)?$resolved:'');$out[]=array('public_id'=>$row['public_id'],'track_type'=>$row['track_type'],'language'=>$row['language'],'source'=>$row['source'],'src'=>$src,'available'=>(bool)$src,'version'=>(int)$row['version']);}return $out;
\t}'''
text=replace_function(text,'published_tracks',published_tracks,'future-published-tracks')
annotations=r'''\tpublic static function annotations($video_id,$include_candidates=false){
\t\t$video=self::video($video_id);if(!$video||!VWLB_Security::can_view($video))return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);global $wpdb;$table=VWLB_Helpers::table('video_annotations');$can_internal=$include_candidates&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_list');$statuses=$can_internal?"('candidate','reviewed','published')":"('published')";$items=VWLB_DB::read_results($wpdb->prepare("SELECT public_id,kind,start_ms,end_ms,title,body,source_owner,source_ref,status,metadata_json,version FROM $table WHERE video_id=%d AND status IN $statuses ORDER BY start_ms ASC,id ASC LIMIT 500",$video['id']),'future_annotations');if(is_wp_error($items))return $items;foreach($items as &$i){if($can_internal)$i['metadata']=VWLB_Helpers::json($i['metadata_json']);unset($i['metadata_json']);}return array('items'=>$items,'truncated'=>count($items)>=500);
\t}'''
text=replace_function(text,'annotations',annotations,'future-annotations')
write(path,text)

path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'); text=read(path)
old="$options=$wpdb->get_results($wpdb->prepare('SELECT id,public_id,option_text,is_correct,sort_order FROM '.VWLB_Helpers::table('live_poll_options').' WHERE poll_id=%d ORDER BY sort_order ASC,id ASC',$poll['id']),ARRAY_A);$can_answers="
new="$options=VWLB_DB::read_results($wpdb->prepare('SELECT id,public_id,option_text,is_correct,sort_order FROM '.VWLB_Helpers::table('live_poll_options').' WHERE poll_id=%d ORDER BY sort_order ASC,id ASC',$poll['id']),'future_poll_options');if(is_wp_error($options))return $options;$can_answers="
text=must_replace(text,old,new,'future-poll-options')
old="$o['responses']=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT user_id) FROM '.VWLB_Helpers::table('live_poll_responses').' WHERE poll_id=%d AND option_id=%d',$poll['id'],$o['id']));unset($o['id']);"
new="$responses=VWLB_DB::read_var($wpdb->prepare('SELECT COUNT(DISTINCT user_id) FROM '.VWLB_Helpers::table('live_poll_responses').' WHERE poll_id=%d AND option_id=%d',$poll['id'],$o['id']),'future_poll_responses');if(is_wp_error($responses))return $responses;$o['responses']=(int)$responses;unset($o['id']);"
text=must_replace(text,old,new,'future-poll-responses')
write(path,text)

path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-frontend.php'); text=read(path)
text=must_replace(text,"$annotations=VWLB_Future_Safety::annotations($id,false);$items=is_wp_error($annotations)?array():($annotations['items']??array());","$annotations=VWLB_Future_Safety::annotations($id,false);if(is_wp_error($annotations))return '<section class=\"vwlb-warning\" role=\"alert\">'.esc_html__('Video knowledge tools are temporarily unavailable.',VWLB_TEXT_DOMAIN).'</section>';$items=$annotations['items']??array();",'future-frontend-annotations')
old="$poll=$wpdb->get_row($wpdb->prepare(\"SELECT public_id FROM \".VWLB_Helpers::table('live_polls').\" WHERE live_event_id=%d AND status='open' ORDER BY id DESC LIMIT 1\",$event['id']),ARRAY_A);$dto=$poll?VWLB_Future_Intelligence::poll($poll['public_id'],false):null;"
new="$poll=VWLB_DB::read_row($wpdb->prepare(\"SELECT public_id FROM \".VWLB_Helpers::table('live_polls').\" WHERE live_event_id=%d AND status='open' ORDER BY id DESC LIMIT 1\",$event['id']),'future_frontend_poll');if(is_wp_error($poll))return '<section class=\"vwlb-warning\" role=\"alert\">'.esc_html__('Live learning tools are temporarily unavailable.',VWLB_TEXT_DOMAIN).'</section>';$dto=$poll?VWLB_Future_Intelligence::poll($poll['public_id'],false):null;if(is_wp_error($dto))return '<section class=\"vwlb-warning\" role=\"alert\">'.esc_html__('Live learning tools are temporarily unavailable.',VWLB_TEXT_DOMAIN).'</section>';"
text=must_replace(text,old,new,'future-frontend-poll')
write(path,text)

# 7) Frontend route preflight must recognize typed podcast read errors.
path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-plugin.php'); text=read(path)
old="$dto=VWLB_Podcasts::public_episode_dto($id);if(VWLB_Repository::read_failed())return $this->unavailable_route_error();return (bool)$dto;"
new="$dto=VWLB_Podcasts::public_episode_dto($id);if(is_wp_error($dto)||VWLB_Repository::read_failed())return $this->unavailable_route_error();return (bool)$dto;"
text=must_replace(text,old,new,'plugin-podcast-route-error')
write(path,text)

# 8) R104 regression contract: prove no patch-stacking on caption cache and enforce direct-read + podcast auth corrections.
path=Path('tests/file10-r101-r120-contracts.sh'); text=read(path)
section=r'''

# R104 — public/read truth, cache privacy and podcast unlisted authorization.
grep -F "public static function read_results" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "public static function read_row" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "public static function read_var" "$P/includes/class-vwlb-db.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "VWLB_DB::read_results" "$P/includes/class-vwlb-future-safety.php" >/dev/null
grep -F "is_wp_error(\$chapters)" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F "is_wp_error(\$extras)" "$P/includes/class-vwlb-frontend.php" >/dev/null
# R78 already owned caption cache privacy before R104; keep that canonical guard rather than stacking a duplicate response filter.
grep -F "function caption_cache" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "Cache-Control','private, no-store" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
# Feed/RSS must apply the same object-level unlisted policy as normal podcast reads.
grep -F "can_view(\$series,'podcast_feed')" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "can_view(\$ep,'podcast_feed')" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "can_view(\$series,'podcast_feed')" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "can_view(\$ep,'podcast_feed')" "$P/includes/class-vwlb-podcasts.php" >/dev/null
'''
if '# R104 — public/read truth' not in text:
    text=text.replace("\necho 'R101-R120 contracts PASS'",section+"\necho 'R101-R120 contracts PASS'")
write(path,text)

# 9) Candidate identity and human-readable evidence. Workflow identity is updated separately through the GitHub contents API.
path=Path('video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'); text=read(path).replace(f'Version: {OLD}',f'Version: {NEW}',1).replace(f"define( 'VWLB_VERSION', '{OLD}' );",f"define( 'VWLB_VERSION', '{NEW}' );",1);write(path,text)
path=Path('video-wall-and-live-broadcasting/readme.txt'); text=read(path).replace(f'Stable tag: {OLD}',f'Stable tag: {NEW}',1).replace(f'video-wall-and-live-broadcasting-{OLD}.zip',f'video-wall-and-live-broadcasting-{NEW}.zip',1)
changelog=f"= {NEW} =\n* R104: propagate immediate database-read failures on frontend/cross-file read helpers instead of rendering empty/404 partial state.\n* Enforce the normal object-level unlisted authorization policy on podcast feed/RSS series and episode projections while preserving private/no-store delivery.\n* Correction validation confirmed the pre-existing R78 caption-cache guard already makes non-public captions private/no-store; no duplicate cache patch was stacked.\n\n"
text=text.replace('== Changelog ==\n\n','== Changelog ==\n\n'+changelog,1);write(path,text)
path=Path('tests/run-all.sh'); text=read(path).replace(f"CURRENT_VERSION='{OLD}'",f"CURRENT_VERSION='{NEW}'",1);write(path,text)

# Evidence docs.
manifest=f'''# File 10 Release Candidate Manifest — {NEW}\n\n- Plugin folder: `video-wall-and-live-broadcasting`\n- Plugin version: `{NEW}`\n- Base schema: `1.1.0`\n- Extension schema: `1.1.0`\n- Future schema: `1.2.0`\n- WordPress baseline: `7.0+`\n- PHP baseline: `8.3+`\n- Canonical API: `video-wall-live-broadcasting/v1`\n- Compatibility API: `vwlb/v1`\n- Text domain: `video-wall-live-broadcasting`\n- Package target: `packages/video-wall-and-live-broadcasting-{NEW}.zip`\n- Checksum target: same filename plus `.sha256`\n- SBOM: `SBOM-{NEW}.json`\n- Prior review boundary: R81–R100 closed at `1.2.11-rc1` on exact reviewed HEAD `9a2c317d664b3c0d56797afbf1934f6c55479aaa`.\n- Current review boundary: R101–R120 sequential cycle; R101–R103 are exact-head QA-closed; R104 correction candidate narrows direct-read truth failures to uncovered frontend/cross-file surfaces, enforces podcast unlisted authorization, and records the pre-existing R78 caption-cache mitigation as a correction-phase false-positive closure rather than stacking duplicate code.\n- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009`.\n- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765`.\n- R103 exact-head QA: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`, run `34064836972`.\n\nThe exact package checksum and artifact digest must be generated by CI from the exact reviewed head. This manifest establishes repository-source/package identity only and does not predeclare staging, deployed/live or operational evidence.\n'''
write(Path('MANIFEST.md'),manifest)
status=f'''# File 10 Status — {NEW}\n\n**Classification:** repository/source correction candidate in the sequential R101–R120 review cycle begun 2026-09-06.\n\n- Cycle baseline exact HEAD: `9a2c317d664b3c0d56797afbf1934f6c55479aaa` (`1.2.11-rc1`).\n- Review method: complete one round read-only → freeze that round's findings → correct all proven findings together → full regression/release QA → only then begin the next round.\n- R101 exact-head QA: `03f9e29e65eac7421f70ea7e01845f4e0e8d46a4`, run `34045547009`, PHP 8.3/8.4 green.\n- R102 exact-head QA: `895c2d66a35a7b9430379a8eff8bc65aaf2d340c`, run `34064117765`, PHP 8.3/8.4 green.\n- R103 exact-head QA: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`, run `34064836972`, PHP 8.3/8.4 green before R104 began.\n- R104 review baseline: `e0a7ae7efba4c2287dc57c3978e4ef9953ef3aa3`; findings frozen in `docs/FILE-10-R104-FROZEN-FINDINGS-2026-09-07.md` before correction.\n- R104 correction validation: direct-read truth defect confirmed on frontend/cross-file helper surfaces and corrected with immediate typed DB-read propagation; podcast feed/RSS unlisted authorization defect confirmed and corrected; the caption-cache finding was invalidated because `VWLB_R78_Public_Delivery_Guard::caption_cache()` already overrides non-public caption responses to private/no-store, so no duplicate patch was added.\n- Coded/reviewed candidate: `{NEW}` on `fix/file10-r101-r120-sequential-2026-09-06` after the R104 correction.\n- Automated-QA Green: R101–R103 established; R104 exact-head QA must be established before R105 begins.\n- Staging-Accepted: not established.\n- Live-Deployed: not established.\n- Operational: not established.\n- Deployed version: unverified.\n- Live DB/schema version: unverified.\n- Migration state: unverified.\n- Live verification: not performed.\n\nGitHub, staging and live are distinct realities. Repository source/package evidence does not identify the code currently deployed to the website. Exact deployed code, live DB/schema and migration state remain unverified until separately frozen from the environment.\n'''
write(Path('STATUS.md'),status)
path=Path('README.md'); text=read(path).replace(f'Runtime: `{OLD}`',f'Runtime: `{NEW}`',1)
insert=f"\nR104 completed a read-only public/read-surface audit before correction. Correction validation confirmed two real defects: uncovered frontend/cross-file direct database reads could collapse failure into empty/404 partial state, and podcast feed/RSS could project unlisted series/episodes without the normal object-level authorization policy. Both are corrected in `{NEW}`. The originally frozen caption-cache item was then proven to be already mitigated by the pre-existing R78 response guard, so it was closed as a false positive without stacking a second cache filter. Exact-head QA is required before R105 begins.\n"
text=text.replace("\n`1.2.11-rc1` remains the historical R81–R100 candidate.",insert+"\n`1.2.11-rc1` remains the historical R81–R100 candidate.",1)
text=text.replace(f'`{OLD}` is the current R103 correction candidate and must not be treated as Automated-QA Green until its exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification and package/source parity all pass.',f'`{OLD}` is the exact-head-green R103 correction candidate. `{NEW}` is the current R104 correction candidate and must not be treated as Automated-QA Green until its exact-head PHP 8.3/8.4 release QA, deterministic packaging, checksum/archive verification and package/source parity all pass.',1)
write(path,text)

correction='''# File 10 — R104 Correction and Validation — 2026-09-07\n\n## Frozen-review integrity\nR104 remained read-only until `docs/FILE-10-R104-FROZEN-FINDINGS-2026-09-07.md` was committed. This correction phase did not begin R105.\n\n## Correction-phase validation\n1. **Direct DB-read truth — confirmed, narrowed.** REST already had R66 request-level `$wpdb->last_error` protection, but that only observes the final request error and does not protect non-REST frontend/cross-file helper reads. Immediate typed read helpers now return a 503-grade `WP_Error` at the failing query and the affected frontend/cross-file paths propagate it instead of rendering empty/404/partial state.\n2. **Caption cache — frozen finding invalidated as a false positive.** `VWLB_R78_Public_Delivery_Guard::caption_cache()` was already registered and overwrites non-public caption responses with `Cache-Control: private, no-store`. The core caption callback's public header therefore was not the final composed response for access-controlled captions. Root-cause-first/no-patch-stacking requires retaining that single canonical guard rather than adding a duplicate cache patch. Regression coverage now makes this composition explicit.\n3. **Podcast feed/RSS unlisted authorization — confirmed.** The R78 feed interceptor and canonical podcast feed both admitted unlisted rows without `VWLB_Security::can_view()`. Both series and episode projections now apply the normal object-level policy; the existing feed response remains no-store and private-storage delivery remains short-lived/provider-gated.\n\n## Candidate\nMaterial code changes receive runtime identity `1.2.15-rc1`. Exact-head PHP 8.3/8.4 release QA, deterministic package/checksum/archive and source/package parity are mandatory before R105.\n'''
write(Path('docs/FILE-10-R104-CORRECTION-2026-09-07.md'),correction)

sbom={"bomFormat":"Sabri-Public-SBOM","specVersion":"1.0","component":{"name":"video-wall-and-live-broadcasting","version":NEW,"type":"wordpress-plugin"},"runtime":{"wordpress":">=7.0","php":">=8.3"},"schemas":{"base":"1.1.0","extension":"1.1.0","future":"1.2.0"},"bundledThirdPartyRuntimeLibraries":[],"externalServiceAdapters":["local","youtube","vimeo","custom"],"notes":"R104 correction candidate: immediate direct-read failure propagation on uncovered frontend/cross-file surfaces; podcast feed/RSS object-level unlisted authorization; pre-existing R78 caption-cache mitigation validated without duplicate patch stacking. Repository/package QA remains distinct from staging, live deployment and operational acceptance."}
write(Path(f'SBOM-{NEW}.json'),json.dumps(sbom,indent=2)+"\n")

print('R104 correction batch prepared')
