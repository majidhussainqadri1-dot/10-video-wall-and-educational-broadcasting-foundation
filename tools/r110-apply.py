from pathlib import Path
import re, json

OLD='1.2.20-rc1'
NEW='1.2.21-rc1'


def read(path):
    return Path(path).read_text()

def write(path,text):
    Path(path).write_text(text)

def once(path, old, new):
    t=read(path)
    n=t.count(old)
    if n!=1:
        raise SystemExit(f'{path}: expected one occurrence, found {n}: {old[:100]!r}')
    write(path,t.replace(old,new,1))

def regex_once(path, pattern, replacement, flags=0):
    t=read(path)
    out,n=re.subn(pattern,replacement,t,count=1,flags=flags)
    if n!=1:
        raise SystemExit(f'{path}: regex expected one occurrence, found {n}: {pattern[:120]!r}')
    write(path,out)

# R110-01: canonical opaque public-ID grammar on late playback and delivery matchers.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r3-playback.php'
once(p,"/videos/(?P<id>[A-Za-z0-9_-]+)/playback","/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/playback")
once(p,"if(!in_array($video['visibility'],array('public','unlisted'),true)){","if('public'!==($video['visibility']??'private')){")
for p in [
 'video-wall-and-live-broadcasting/includes/class-vwlb-r71-private-download-guard.php',
 'video-wall-and-live-broadcasting/includes/class-vwlb-r72-podcast-boundary-guard.php',
 'video-wall-and-live-broadcasting/includes/class-vwlb-r78-public-delivery-guard.php',
 'video-wall-and-live-broadcasting/includes/class-vwlb-r79-watermark-session-guard.php',
 'video-wall-and-live-broadcasting/includes/class-vwlb-r91-unlisted-access-guard.php',
]:
    t=read(p)
    if '[A-Za-z0-9_-]+' not in t:
        raise SystemExit(f'{p}: expected legacy public-ID matcher')
    write(p,t.replace('[A-Za-z0-9_-]+','[a-z][a-z0-9]*_[a-z0-9]+'))

# Canonical non-public playback must use a secure grant too, including unlisted frontend playback.
p='video-wall-and-live-broadcasting/includes/class-vwlb-videos.php'
once(p,"if(!in_array($video['visibility'],array('public','unlisted'),true)){$secure=apply_filters('vwlb_secure_playback_grant','',$video,$payload,$session,VWLB_Security::claims());if(!$secure)return VWLB_Helpers::error('vwlb_secure_delivery_required',__('Secure playback delivery is not configured.',VWLB_TEXT_DOMAIN),503);$payload['url']=esc_url_raw($secure);}","if('public'!==($video['visibility']??'private')){try{$secure=apply_filters('vwlb_secure_playback_grant','',$video,$payload,$session,VWLB_Security::claims());}catch(Throwable $e){do_action('vwlb_operational_failure','playback','vwlb_secure_playback_grant_exception',array('video_public_id'=>$video['public_id']??'','exception'=>sanitize_key(get_class($e))));return VWLB_Helpers::error('vwlb_secure_delivery_failed',__('Secure playback authorization ended unexpectedly.',VWLB_TEXT_DOMAIN),503);}$secure=esc_url_raw(is_string($secure)?$secure:'',array('https'));if(!$secure)return VWLB_Helpers::error('vwlb_secure_delivery_required',__('Secure playback delivery is not configured.',VWLB_TEXT_DOMAIN),503);$payload['url']=$secure;}")

# R110-02: current rights/consent is applied during public browse, not only detail/playback.
p='video-wall-and-live-broadcasting/includes/class-vwlb-repository.php'
once(p,"SELECT id,public_id,channel_id,owner_id,title,slug,excerpt,language,provider,embed_url,thumbnail_id,duration_seconds,visibility,status,published_at,view_count,like_count,dislike_count FROM $table WHERE ","SELECT id,public_id,channel_id,owner_id,title,slug,excerpt,language,provider,embed_url,thumbnail_id,duration_seconds,visibility,status,published_at,consent_status,rights_status,rights_json,access_policy_json,deleted_at,view_count,like_count,dislike_count FROM $table WHERE ")
once(p,"foreach($rows as &$r){$channel_id=(int)($r['channel_id']??0);", "$items=array();foreach($rows as $r){if(!VWLB_Security::can_view($r,'browse_video')){if(class_exists('VWLB_R109_Rights_Consent_Replay_Guard')&&VWLB_R109_Rights_Consent_Replay_Guard::policy_read_failed())return array('items'=>array(),'next_cursor'=>'','error_code'=>'vwlb_video_browse_policy_unverifiable');continue;}$channel_id=(int)($r['channel_id']??0);")
once(p,"unset($r['id'],$r['channel_id'],$r['owner_id'],$r['thumbnail_id']);", "unset($r['id'],$r['channel_id'],$r['owner_id'],$r['thumbnail_id'],$r['consent_status'],$r['rights_status'],$r['rights_json'],$r['access_policy_json'],$r['deleted_at']);")
once(p,"$r['url']=home_url('/video/'.$r['public_id'].'/'.$r['slug'].'/');}unset($r);return array('items'=>$rows,'next_cursor'=>$next);", "$r['url']=home_url('/video/'.$r['public_id'].'/'.$r['slug'].'/');$items[]=$r;}return array('items'=>$items,'next_cursor'=>$next);")

p='video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php'
once(p,"$data=VWLB_Repository::browse_videos(array('per_page'=>12));if(VWLB_Repository::read_failed())", "$data=VWLB_Repository::browse_videos(array('per_page'=>12));if(VWLB_Repository::read_failed()||!empty($data['error_code']))")

# R110-03: consent-link table is video-specific; podcast consent remains object-level only.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r109-rights-consent-replay-guard.php'
once(p,"private static function read_failed($context){self::$policy_read_failed=true;", "public static function policy_read_failed(){return self::$policy_read_failed;}\n\tprivate static function read_failed($context){self::$policy_read_failed=true;")
once(p,"if(!array_key_exists('published_at',$video)||empty($video['id']))return true;\n\t\tif(array_key_exists('consent_status',$video)&&!in_array((string)$video['consent_status'],array('not_patient_case','documented','anonymized','approved'),true))return false;", "if(array_key_exists('consent_status',$video)&&!in_array((string)$video['consent_status'],array('not_patient_case','documented','anonymized','approved'),true))return false;\n\t\t$is_video=array_key_exists('rights_json',$video)&&array_key_exists('access_policy_json',$video)&&array_key_exists('published_at',$video);if(!$is_video||empty($video['id']))return true;")

# R110-04: R71 owns fail-closed verification and protects any non-public/private-storage download.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r71-private-download-guard.php'
once(p,"$object='video'===$row['object_type']?VWLB_Repository::video_bundle($row['object_id']):VWLB_Podcasts::episode($row['object_id'],true);if(!$object||('podcast'===$row['object_type']&&'published'!==($object['status']??''))||!VWLB_Security::can_view($object,'download')||!self::allowed($row['object_type'],$object))return $response;$asset='video'===$row['object_type']?($object['asset']??array()):VWLB_Repository::find('media_assets',$object['asset_id']??0);$storage=VWLB_Helpers::json($asset['storage_json']??'{}');if('private_file'!==($storage['driver']??''))return $response;", "$wpdb->last_error='';VWLB_Repository::reset_read_failure();$object='video'===$row['object_type']?VWLB_Repository::video_bundle($row['object_id']):VWLB_Podcasts::episode($row['object_id'],true);if(''!==(string)$wpdb->last_error||VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_download_state_unreadable',__('Download authorization state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$object||('podcast'===$row['object_type']&&'published'!==($object['status']??''))||!VWLB_Security::can_view($object,'download')||!self::allowed($row['object_type'],$object))return VWLB_Helpers::error('vwlb_download_revoked',__('Download access is unavailable or revoked.',VWLB_TEXT_DOMAIN),403);VWLB_Repository::reset_read_failure();$asset='video'===$row['object_type']?($object['asset']??array()):VWLB_Repository::find('media_assets',$object['asset_id']??0);if(VWLB_Repository::read_failed()||!$asset)return VWLB_Helpers::error('vwlb_download_state_unreadable',__('Download media state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$storage=VWLB_Helpers::json($asset['storage_json']??'{}');$requires_secure='private_file'===($storage['driver']??'')||'public'!==($object['visibility']??'private');if(!$requires_secure)return $response;")

# R72 revalidation must fail closed after a successful canonical callback.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r72-podcast-boundary-guard.php'
marker="\tpublic static function after($response,$handler,$request){"
helper="\tprivate static function revalidated_episode($id){global $wpdb;$wpdb->last_error='';VWLB_Repository::reset_read_failure();$ep=VWLB_Podcasts::episode($id,false);if(''!==(string)$wpdb->last_error||VWLB_Repository::read_failed())return VWLB_Helpers::error('vwlb_podcast_delivery_unverifiable',__('Podcast delivery state could not be verified safely.',VWLB_TEXT_DOMAIN),503);return $ep?:VWLB_Helpers::error('vwlb_podcast_delivery_unverifiable',__('Podcast delivery state changed during authorization.',VWLB_TEXT_DOMAIN),503);}\n"
once(p,marker,helper+marker)
once(p,"$episode_id=self::item_route_id($request);if($episode_id){$ep=VWLB_Podcasts::episode($episode_id,false);if($ep){$asset=VWLB_Repository::find('media_assets',$ep['asset_id']??0);$storage=VWLB_Helpers::json($asset['storage_json']??'{}');", "$episode_id=self::item_route_id($request);if($episode_id){$ep=self::revalidated_episode($episode_id);if(is_wp_error($ep))return $ep;VWLB_Repository::reset_read_failure();$asset=VWLB_Repository::find('media_assets',$ep['asset_id']??0);if(VWLB_Repository::read_failed()||!$asset)return VWLB_Helpers::error('vwlb_podcast_delivery_unverifiable',__('Podcast media state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$storage=VWLB_Helpers::json($asset['storage_json']??'{}');")
# The replacement removed one nested if($ep) opening; remove its paired extra close before DTO stripping.
once(p,"$wrapped->header('Cache-Control','private, no-store');}}}\n\t\t$wrapped->set_data", "$wrapped->header('Cache-Control','private, no-store');}}\n\t\t$wrapped->set_data")

# R78 complete authorization projection and fail-closed caption revalidation; unlisted feed audio uses grants.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r78-public-delivery-guard.php'
once(p,"SELECT public_id,title,description,duration_seconds,published_at,asset_id FROM {$episode_table}", "SELECT id,public_id,owner_id,asset_id,title,description,duration_seconds,published_at,visibility,status,rights_status,consent_status,deleted_at FROM {$episode_table}")
once(p,"private static function secure_audio($asset,$episode,$series){$storage=VWLB_Helpers::json($asset['storage_json']??'{}');$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');if('private_file'!==($storage['driver']??''))return esc_url_raw($der['audio_only']??$der['mp3']??'',array('https'));", "private static function secure_audio($asset,$episode,$series){$storage=VWLB_Helpers::json($asset['storage_json']??'{}');$der=VWLB_Helpers::json($asset['derivatives_json']??'{}');$requires_secure='private_file'===($storage['driver']??'')||'public'!==($episode['visibility']??'private')||'public'!==($series['visibility']??'private');if(!$requires_secure)return esc_url_raw($der['audio_only']??$der['mp3']??'',array('https'));")
once(p,"$caption=VWLB_Repository::find('captions',$id);$video=$caption?VWLB_Repository::find('videos',$caption['video_id']):null;if($video&&('public'!==($video['visibility']??'')||'published'!==($video['status']??''))){", "VWLB_Repository::reset_read_failure();$caption=VWLB_Repository::find('captions',$id);if(VWLB_Repository::read_failed()||!$caption)return VWLB_Helpers::error('vwlb_caption_delivery_unverifiable',__('Caption delivery state could not be verified safely.',VWLB_TEXT_DOMAIN),503);$video=VWLB_Repository::find('videos',$caption['video_id']);if(VWLB_Repository::read_failed()||!$video)return VWLB_Helpers::error('vwlb_caption_delivery_unverifiable',__('Caption parent state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if('public'!==($video['visibility']??'')||'published'!==($video['status']??'')){")

# R91 late unlisted revalidation must return an error on unreadable/disappeared state and contain grant callbacks.
p='video-wall-and-live-broadcasting/includes/class-vwlb-r91-unlisted-access-guard.php'
marker="\tprivate static function route_object($request){"
helper="\tprivate static function repository_route_object($type,$entity,$id){VWLB_Repository::reset_read_failure();$row=VWLB_Repository::find($entity,$id);if(VWLB_Repository::read_failed()||!$row)return VWLB_Helpers::error('vwlb_unlisted_state_unverifiable',__('Unlisted delivery state could not be verified safely.',VWLB_TEXT_DOMAIN),503);return array('type'=>$type,'row'=>$row);}\n"
once(p,marker,helper+marker)
once(p,"if(preg_match('#^/'.$q.'/videos/([a-z][a-z0-9]*_[a-z0-9]+)/playback$#',$route,$m))return array('type'=>'video','row'=>VWLB_Repository::find('videos',$m[1]));if(preg_match('#^/'.$q.'/live-events/([a-z][a-z0-9]*_[a-z0-9]+)$#',$route,$m))return array('type'=>'live','row'=>VWLB_Repository::find('live_events',$m[1]));", "if(preg_match('#^/'.$q.'/videos/([a-z][a-z0-9]*_[a-z0-9]+)/playback$#',$route,$m))return self::repository_route_object('video','videos',$m[1]);if(preg_match('#^/'.$q.'/live-events/([a-z][a-z0-9]*_[a-z0-9]+)$#',$route,$m))return self::repository_route_object('live','live_events',$m[1]);")
once(p,"$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',VWLB_Helpers::text($m[1],64)),ARRAY_A);return array('type'=>'podcast','row'=>$row);", "$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_episodes').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',VWLB_Helpers::text($m[1],64)),ARRAY_A);if(''!==(string)$wpdb->last_error||!$row)return VWLB_Helpers::error('vwlb_unlisted_state_unverifiable',__('Unlisted podcast state could not be verified safely.',VWLB_TEXT_DOMAIN),503);return array('type'=>'podcast','row'=>$row);")
once(p,"$ctx=self::route_object($request);$row=$ctx['row']??null;", "$ctx=self::route_object($request);if(is_wp_error($ctx))return $ctx;$row=$ctx['row']??null;")
once(p,"$url=apply_filters('vwlb_secure_playback_grant','',$row,$data['playback'],$session,VWLB_Security::claims());", "try{$url=apply_filters('vwlb_secure_playback_grant','',$row,$data['playback'],$session,VWLB_Security::claims());}catch(Throwable $e){return VWLB_Helpers::error('vwlb_unlisted_secure_delivery_failed',__('Unlisted secure playback grant failed safely.',VWLB_TEXT_DOMAIN),503);}")
once(p,"$url=apply_filters('vwlb_secure_live_playback_grant','',$row,$data['playback'],VWLB_Security::claims());", "try{$url=apply_filters('vwlb_secure_live_playback_grant','',$row,$data['playback'],VWLB_Security::claims());}catch(Throwable $e){return VWLB_Helpers::error('vwlb_unlisted_secure_delivery_failed',__('Unlisted secure live grant failed safely.',VWLB_TEXT_DOMAIN),503);}")
once(p,"$series=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE id=%d LIMIT 1',(int)$row['series_id']),ARRAY_A)?:array();", "$series=VWLB_DB::read_row($wpdb->prepare('SELECT * FROM '.VWLB_Helpers::table('podcast_series').' WHERE id=%d LIMIT 1',(int)$row['series_id']),'unlisted_podcast_series');if(is_wp_error($series)||!$series)return is_wp_error($series)?$series:VWLB_Helpers::error('vwlb_unlisted_state_unverifiable',__('Unlisted podcast series state changed during delivery.',VWLB_TEXT_DOMAIN),503);")
once(p,"$url=apply_filters('vwlb_public_podcast_feed_grant','',$asset?:array(),$row,$series);", "try{$url=apply_filters('vwlb_public_podcast_feed_grant','',$asset?:array(),$row,$series);}catch(Throwable $e){return VWLB_Helpers::error('vwlb_unlisted_secure_delivery_failed',__('Unlisted podcast grant failed safely.',VWLB_TEXT_DOMAIN),503);}")

# Canonical and REST download/media contracts: only truly public media may expose ordinary derivatives.
p='video-wall-and-live-broadcasting/includes/class-vwlb-review-hardening.php'
# media contract: protect unlisted too and contain the grant callback.
once(p,"$protected=!in_array($video['visibility'],array('public','unlisted'),true);$derivatives=$protected?apply_filters('vwlb_secure_media_contract_grant',array(),$video,$asset,VWLB_Security::claims()):self::public_derivatives($asset);", "$protected='public'!==($video['visibility']??'private');if($protected){try{$derivatives=apply_filters('vwlb_secure_media_contract_grant',array(),$video,$asset,VWLB_Security::claims());}catch(Throwable $e){return VWLB_Helpers::error('vwlb_secure_media_contract_grant_exception',__('Secure media-contract delivery failed safely.',VWLB_TEXT_DOMAIN),503);}}else{$derivatives=self::public_derivatives($asset);}")
once(p,"if(!is_wp_error($response)&&$protected)$response->header('Cache-Control','private, no-store');return $response;", "if(!is_wp_error($response)&&$protected){$response->header('Cache-Control','private, no-store');if('unlisted'===($video['visibility']??''))$response->header('X-Robots-Tag','noindex, nofollow, noarchive');}return $response;")
# download resolver: unlisted is protected too; preserve public raw download only for truly public visibility.
once(p,"$protected=!in_array($object['visibility']??'private',array('public','unlisted'),true);if($protected){$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);$url=esc_url_raw(is_string($url)?$url:'');}else{", "$storage=VWLB_Helpers::json($asset['storage_json']??'{}');$protected='public'!==($object['visibility']??'private')||'private_file'===($storage['driver']??'');if($protected){try{$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);}catch(Throwable $e){return VWLB_Helpers::error('vwlb_private_download_grant_failed',__('Secure download delivery failed safely.',VWLB_TEXT_DOMAIN),503);}$url=esc_url_raw(is_string($url)?$url:'',array('https'));}else{")

p='video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
# canonical media contract replacement in the function body.
once(p,"$asset=$video['asset']??array();$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$chapters=self::chapters('video',$video['id']);if(is_wp_error($chapters))return $chapters;", "$asset=$video['asset']??array();if(!$asset||'ready'!==($asset['status']??'')||'passed'!==($asset['scan_status']??''))return VWLB_Helpers::error('vwlb_media_not_ready',__('Media is not ready for delivery.',VWLB_TEXT_DOMAIN),409);$protected='public'!==($video['visibility']??'private');if($protected){try{$derivatives=apply_filters('vwlb_secure_media_contract_grant',array(),$video,$asset,VWLB_Security::claims());}catch(Throwable $e){return VWLB_Helpers::error('vwlb_secure_media_contract_grant_exception',__('Secure media-contract delivery failed safely.',VWLB_TEXT_DOMAIN),503);}}else{$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');}$safe=array();foreach(array('hls','mp4_high','mp4_low','audio_only','poster','storyboard') as $key){$url=esc_url_raw($derivatives[$key]??'',array('https'));if($url)$safe[$key]=$url;}if($protected&&!$safe)return VWLB_Helpers::error('vwlb_secure_delivery_required',__('Secure media delivery is not configured.',VWLB_TEXT_DOMAIN),503);$derivatives=$safe;$chapters=self::chapters('video',$video['id']);if(is_wp_error($chapters))return $chapters;")
# canonical download URL selection must not expose raw non-public/private-storage derivatives.
once(p,"$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$url=$derivatives['download']??$derivatives['mp4_high']??$derivatives['mp4']??$derivatives['audio_only']??'';if(!$url){$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);$url=is_string($url)?$url:'';}", "$derivatives=VWLB_Helpers::json($asset['derivatives_json']??'{}');$storage=VWLB_Helpers::json($asset['storage_json']??'{}');$protected='public'!==($object['visibility']??'private')||'private_file'===($storage['driver']??'');if($protected){try{$url=apply_filters('vwlb_private_download_grant','',$asset,$object,$row);}catch(Throwable $e){return VWLB_Helpers::error('vwlb_private_download_grant_failed',__('Secure download delivery failed safely.',VWLB_TEXT_DOMAIN),503);}$url=is_string($url)?$url:'';}else{$url=$derivatives['download']??$derivatives['mp4_high']??$derivatives['mp4']??$derivatives['audio_only']??'';}")

# Version identity.
p='video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
t=read(p)
if t.count(OLD)<2: raise SystemExit('plugin version identity not found')
write(p,t.replace(OLD,NEW))
p='tests/run-all.sh'; once(p,"CURRENT_VERSION='1.2.20-rc1'","CURRENT_VERSION='1.2.21-rc1'")
p='video-wall-and-live-broadcasting/readme.txt'
once(p,'Stable tag: 1.2.20-rc1','Stable tag: 1.2.21-rc1')
once(p,'video-wall-and-live-broadcasting-1.2.20-rc1.zip','video-wall-and-live-broadcasting-1.2.21-rc1.zip')
once(p,"== Changelog ==\n\n= 1.2.20-rc1 =", "== Changelog ==\n\n= 1.2.21-rc1 =\n* R110: restore canonical opaque IDs on late delivery routes and re-apply current rights/consent policy during public discovery.\n* Fail closed on security-sensitive delivery revalidation reads and use complete podcast authorization projections.\n* Treat every non-public visibility, including unlisted, as secure-grant-only for playback, downloads and media contracts.\n\n= 1.2.20-rc1 =")

# R110 regression contracts.
p='tests/file10-r101-r120-contracts.sh'
t=read(p)
anchor="\necho 'R101-R120 contracts PASS'"
if anchor not in t: raise SystemExit('R110 contract anchor missing')
block=r'''

# R110 — current public-delivery truth, canonical opaque IDs and fail-closed secure grants.
grep -F "/videos/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/playback" "$P/includes/class-vwlb-r3-playback.php" >/dev/null
for f in class-vwlb-r3-playback.php class-vwlb-r71-private-download-guard.php class-vwlb-r72-podcast-boundary-guard.php class-vwlb-r78-public-delivery-guard.php class-vwlb-r79-watermark-session-guard.php class-vwlb-r91-unlisted-access-guard.php; do
  ! grep -F "[A-Za-z0-9_-]+" "$P/includes/$f" >/dev/null
 done
grep -F "VWLB_Security::can_view(\$r,'browse_video')" "$P/includes/class-vwlb-repository.php" >/dev/null
grep -F "vwlb_video_browse_policy_unverifiable" "$P/includes/class-vwlb-repository.php" >/dev/null
grep -F "policy_read_failed()" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "array_key_exists('rights_json',\$video)" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_download_state_unreadable" "$P/includes/class-vwlb-r71-private-download-guard.php" >/dev/null
grep -F "revalidated_episode" "$P/includes/class-vwlb-r72-podcast-boundary-guard.php" >/dev/null
grep -F "id,public_id,owner_id,asset_id" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "vwlb_caption_delivery_unverifiable" "$P/includes/class-vwlb-r78-public-delivery-guard.php" >/dev/null
grep -F "repository_route_object" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" >/dev/null
grep -F "vwlb_unlisted_state_unverifiable" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" >/dev/null
grep -F "vwlb_secure_media_contract_grant_exception" "$P/includes/class-vwlb-review-hardening.php" >/dev/null
grep -F "vwlb_secure_media_contract_grant_exception" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "'public'!==(\$video['visibility']??'private')" "$P/includes/class-vwlb-review-hardening.php" >/dev/null
grep -F "X-Robots-Tag','noindex, nofollow, noarchive" "$P/includes/class-vwlb-review-hardening.php" >/dev/null
grep -F "vwlb_private_download_grant_failed" "$P/includes/class-vwlb-extensions.php" >/dev/null
'''
write(p,t.replace(anchor,block+anchor,1))

# New SBOM identity from the immediately previous immutable candidate.
old_sbom=Path('SBOM-1.2.20-rc1.json')
if not old_sbom.exists(): raise SystemExit('SBOM-1.2.20-rc1.json missing')
new_sbom=Path('SBOM-1.2.21-rc1.json')
if new_sbom.exists(): raise SystemExit('SBOM-1.2.21-rc1.json already exists')
obj=json.loads(old_sbom.read_text())
def repl(v):
    if isinstance(v,str): return v.replace(OLD,NEW)
    if isinstance(v,list): return [repl(x) for x in v]
    if isinstance(v,dict): return {k:repl(x) for k,x in v.items()}
    return v
new_sbom.write_text(json.dumps(repl(obj),indent=2,ensure_ascii=False)+'\n')

print('R110 correction applicator completed')
