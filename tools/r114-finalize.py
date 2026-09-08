from pathlib import Path

OLD='1.2.24-rc1'; NEW='1.2.25-rc1'

def read(p): return Path(p).read_text()
def write(p,t): Path(p).write_text(t)
def once(p,a,b):
    t=read(p); n=t.count(a)
    if n!=1: raise SystemExit(f'{p}: expected one occurrence, found {n}: {a[:120]!r}')
    write(p,t.replace(a,b,1))

# R114-01 — publication caption gate must fail closed on SQL read failure.
p='video-wall-and-live-broadcasting/includes/class-vwlb-videos.php'
once(p,"$caption=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('captions').' WHERE video_id=%d AND status=%s',$video['id'],'published'));if(!$caption)return VWLB_Helpers::error('vwlb_caption_gate'","$caption=VWLB_DB::read_var($wpdb->prepare('SELECT COUNT(*) FROM '.VWLB_Helpers::table('captions').' WHERE video_id=%d AND status=%s',$video['id'],'published'),'r114_publication_caption_count');if(is_wp_error($caption))return $caption;$caption=(int)$caption;if(!$caption)return VWLB_Helpers::error('vwlb_caption_gate'")

# R114-02 — serialize caption version allocation by locking the parent video row; all reads fail closed.
old="global $wpdb;$now=VWLB_Helpers::now();$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','imported','machine_draft'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'review_caption');$status=('machine_draft'!==$source&&$can_review)?'published':'review';$version=(int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(MAX(version),0)+1 FROM '.VWLB_Helpers::table('captions').' WHERE video_id=%d AND language=%s AND kind=%s',$video['id'],VWLB_Helpers::text($data['language']??'en-US',20),VWLB_Helpers::enum($data['kind']??'captions',array('captions','subtitles','transcript'),'captions')));$wpdb->insert(VWLB_Helpers::table('captions'),array('public_id'=>VWLB_Helpers::public_id('cap'),'video_id'=>$video['id'],'language'=>VWLB_Helpers::text($data['language']??'en-US',20),'kind'=>VWLB_Helpers::enum($data['kind']??'captions',array('captions','subtitles','transcript'),'captions'),'source'=>$source,'format'=>$format,'content'=>$content,'status'=>$status,'quality_score'=>isset($data['quality_score'])?(float)$data['quality_score']:null,'version'=>$version,'created_by'=>get_current_user_id(),'reviewed_by'=>'published'===$status?get_current_user_id():0,'created_at'=>$now,'updated_at'=>$now));if(!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Caption could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('caption',$id,'create','',$status,'',array('source'=>$source));$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('captions').' WHERE id=%d',$id));return array('public_id'=>$public,'status'=>$status,'version'=>$version);"
new="global $wpdb;$now=VWLB_Helpers::now();$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','imported','machine_draft'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'review_caption');$status=('machine_draft'!==$source&&$can_review)?'published':'review';$language=VWLB_Helpers::text($data['language']??'en-US',20);$kind=VWLB_Helpers::enum($data['kind']??'captions',array('captions','subtitles','transcript'),'captions');return VWLB_DB::transaction(function()use($video,$language,$kind,$source,$format,$content,$status,$data,$now){global $wpdb;$locked=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE id=%d FOR UPDATE',$video['id']),'r114_caption_parent_lock');if(is_wp_error($locked))return $locked;if(!$locked)return VWLB_Helpers::error('vwlb_video_missing',__('Video not found.',VWLB_TEXT_DOMAIN),404);$version=VWLB_DB::read_var($wpdb->prepare('SELECT COALESCE(MAX(version),0)+1 FROM '.VWLB_Helpers::table('captions').' WHERE video_id=%d AND language=%s AND kind=%s',$video['id'],$language,$kind),'r114_caption_version');if(is_wp_error($version))return $version;$version=(int)$version;$public=VWLB_Helpers::public_id('cap');$saved=$wpdb->insert(VWLB_Helpers::table('captions'),array('public_id'=>$public,'video_id'=>$video['id'],'language'=>$language,'kind'=>$kind,'source'=>$source,'format'=>$format,'content'=>$content,'status'=>$status,'quality_score'=>isset($data['quality_score'])?(float)$data['quality_score']:null,'version'=>$version,'created_by'=>get_current_user_id(),'reviewed_by'=>'published'===$status?get_current_user_id():0,'created_at'=>$now,'updated_at'=>$now));if(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Caption could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('caption',$id,'create','',$status,'',array('source'=>$source));return array('public_id'=>$public,'status'=>$status,'version'=>$version);});"
once(p,old,new)

# R114-03 — playback/progress existence reads must distinguish SQL failure from absence.
once(p,"$row=$wpdb->get_row($wpdb->prepare('SELECT id,public_id,progress_seconds FROM '.VWLB_Helpers::table('playback_sessions').' WHERE user_id=%d AND object_type=%s AND object_id=%d',$user,'video',$video['id']),ARRAY_A);if($row)","$row=VWLB_DB::read_row($wpdb->prepare('SELECT id,public_id,progress_seconds FROM '.VWLB_Helpers::table('playback_sessions').' WHERE user_id=%d AND object_type=%s AND object_id=%d',$user,'video',$video['id']),'r114_playback_session_lookup');if(is_wp_error($row))return $row;if($row)")
once(p,"$existing=$wpdb->get_row($wpdb->prepare(\"SELECT id FROM $table WHERE user_id=%d AND object_type='video' AND object_id=%d\",get_current_user_id(),$video['id']),ARRAY_A);$data=array","$existing=VWLB_DB::read_row($wpdb->prepare(\"SELECT id FROM $table WHERE user_id=%d AND object_type='video' AND object_id=%d\",get_current_user_id(),$video['id']),'r114_progress_session_lookup');if(is_wp_error($existing))return $existing;$data=array")

# R114-04 — interaction lock/existence/count reads fail closed before mutation/counter writes.
once(p,"$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE id=%d FOR UPDATE',$video['id']));if(in_array($type,array('like','dislike'),true))","$locked=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('videos').' WHERE id=%d FOR UPDATE',$video['id']),'r114_interaction_video_lock');if(is_wp_error($locked))return $locked;if(!$locked)return VWLB_Helpers::error('vwlb_not_found',__('Video not found.',VWLB_TEXT_DOMAIN),404);if(in_array($type,array('like','dislike'),true))")
once(p,"$exists=$wpdb->get_var($wpdb->prepare(\"SELECT id FROM $table WHERE user_id=%d AND video_id=%d AND interaction=%s\",$user,$video['id'],$type));if($exists)","$exists=VWLB_DB::read_var($wpdb->prepare(\"SELECT id FROM $table WHERE user_id=%d AND video_id=%d AND interaction=%s\",$user,$video['id'],$type),'r114_interaction_lookup');if(is_wp_error($exists))return $exists;if($exists)")
once(p,"$count=(int)$wpdb->get_var($wpdb->prepare(\"SELECT COUNT(*) FROM $table WHERE video_id=%d AND interaction=%s\",$video['id'],$count_type));$changed=$wpdb->update","$count=VWLB_DB::read_var($wpdb->prepare(\"SELECT COUNT(*) FROM $table WHERE video_id=%d AND interaction=%s\",$video['id'],$count_type),'r114_interaction_count');if(is_wp_error($count))return $count;$count=(int)$count;$changed=$wpdb->update")

# R114-05 — moderation FOR UPDATE read must fail closed on SQL failure.
p='video-wall-and-live-broadcasting/includes/class-vwlb-moderation.php'
once(p,"$fresh=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE id=%d FOR UPDATE\",$report['id']),ARRAY_A);if(!$fresh)return VWLB_Helpers::error('vwlb_report_missing'","$fresh=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE id=%d FOR UPDATE\",$report['id']),'r114_moderation_lock');if(is_wp_error($fresh))return $fresh;if(!$fresh)return VWLB_Helpers::error('vwlb_report_missing'")

# Advance immutable candidate identity.
for p in ['video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php','video-wall-and-live-broadcasting/readme.txt','README.md','MANIFEST.md','STATUS.md','tests/run-all.sh']:
    t=read(p)
    if OLD not in t: raise SystemExit(f'{p}: old version missing')
    write(p,t.replace(OLD,NEW))

# R114 regression assertions.
p='tests/file10-r101-r120-contracts.sh'; t=read(p)
block='''\n# R114 direct database mutation-read integrity correction\ngrep -F "r114_publication_caption_count" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_caption_parent_lock" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_caption_version" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_playback_session_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_progress_session_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_interaction_video_lock" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_interaction_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_interaction_count" "$P/includes/class-vwlb-videos.php" >/dev/null\ngrep -F "r114_moderation_lock" "$P/includes/class-vwlb-moderation.php" >/dev/null\n'''
if 'r114_publication_caption_count' not in t: write(p,t+block)

src=Path('SBOM-1.2.24-rc1.json'); dst=Path('SBOM-1.2.25-rc1.json')
dst.write_text(src.read_text().replace(OLD,NEW))

for p in ['MANIFEST.md','STATUS.md']:
    t=read(p)
    note='\n- R114: read-only direct database mutation-read integrity review completed; five findings frozen in `docs/FILE-10-R114-FROZEN-FINDINGS-2026-09-08.md`; correction candidate `1.2.25-rc1` requires exact-head Green before R115.\n'
    if 'R114:' not in t: write(p,t+note)
print('R114 correction applicator completed')
