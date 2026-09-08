#!/usr/bin/env python3
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
EXT=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
REST=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-extended-rest.php'
POD=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'

def rep(text, old, new, label):
    n=text.count(old)
    if n!=1:
        raise SystemExit(f'{label}: expected exactly 1 match, found {n}')
    return text.replace(old,new,1)

# F1 — podcast-series resolver.
s=REST.read_text()
s=rep(s,
"private function series_id($public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;global $wpdb;return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',(string)$public_id));}",
"private function series_id($public_id){if(!VWLB_Helpers::is_public_id((string)$public_id))return 0;global $wpdb;$id=VWLB_DB::read_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series').' WHERE public_id=%s AND deleted_at IS NULL LIMIT 1',(string)$public_id),'r119_podcast_series_resolver');if(is_wp_error($id))return $id;return (int)$id;}",
'F1-series-resolver')
s=rep(s,
"$d['series_id']=$this->series_id($d['series_public_id']??'');if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);",
"$d['series_id']=$this->series_id($d['series_public_id']??'');if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);",
'F1-series-propagation')
REST.write_text(s)

# F9 — make the podcast episode primitive itself fail closed and update direct callers.
s=POD.read_text()
s=rep(s,
"\t\t$row=$wpdb->get_row(\"SELECT * FROM \".VWLB_Helpers::table('podcast_episodes').\" WHERE $where AND deleted_at IS NULL LIMIT 1\",ARRAY_A);\n\t\tif(!$row)return null;",
"\t\t$row=VWLB_DB::read_row(\"SELECT * FROM \".VWLB_Helpers::table('podcast_episodes').\" WHERE $where AND deleted_at IS NULL LIMIT 1\",'r119_podcast_episode');\n\t\tif(is_wp_error($row))return $row;if(!$row)return null;",
'F9-episode-primitive')
s=rep(s,
"\t\t$wpdb->last_error='';$ep=self::episode($id,true);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast episode state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$ep)return VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);",
"\t\t$ep=self::episode($id,true);if(is_wp_error($ep))return $ep;if(!$ep)return VWLB_Helpers::error('vwlb_not_found',__('Podcast episode not found.',VWLB_TEXT_DOMAIN),404);",
'F9-publish-caller')
s=rep(s,
"\t\tglobal $wpdb;if(!VWLB_Helpers::is_public_id((string)$id))return null;$wpdb->last_error='';$ep=self::episode($id,false);if(''!==(string)$wpdb->last_error)return VWLB_Helpers::error('vwlb_database_read_failed',__('Podcast episode state could not be verified safely.',VWLB_TEXT_DOMAIN),503);if(!$ep)return null;",
"\t\tglobal $wpdb;if(!VWLB_Helpers::is_public_id((string)$id))return null;$ep=self::episode($id,false);if(is_wp_error($ep))return $ep;if(!$ep)return null;",
'F9-public-caller')
POD.write_text(s)

s=EXT.read_text()
# F7 upload-session primitive.
s=rep(s,
"\t\treturn $wpdb->get_row(\n\t\t\t$wpdb->prepare( 'SELECT * FROM ' . VWLB_Helpers::table('upload_sessions') . ' WHERE public_id=%s LIMIT 1', VWLB_Helpers::text( $public_id, 64 ) ),\n\t\t\tARRAY_A\n\t\t);",
"\t\treturn VWLB_DB::read_row(\n\t\t\t$wpdb->prepare( 'SELECT * FROM ' . VWLB_Helpers::table('upload_sessions') . ' WHERE public_id=%s LIMIT 1', VWLB_Helpers::text( $public_id, 64 ) ),\n\t\t\t'r119_upload_session'\n\t\t);",
'F7-upload-session')
# F2 waiting-room transaction reads/count.
s=rep(s,
"$fresh=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $events WHERE id=%d FOR UPDATE\",$event['id']),ARRAY_A);\n\t\t\tif(!$fresh||!VWLB_Security::can_view($fresh,'waiting_room'))",
"$fresh=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $events WHERE id=%d FOR UPDATE\",$event['id']),'r119_waiting_room_event_lock');\n\t\t\tif(is_wp_error($fresh))return $fresh;if(!$fresh||!VWLB_Security::can_view($fresh,'waiting_room'))",
'F2-waiting-event')
s=rep(s,
"$existing=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE\",$fresh['id'],$uid),ARRAY_A);",
"$existing=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE\",$fresh['id'],$uid),'r119_waiting_room_attendee_lock');if(is_wp_error($existing))return $existing;",
'F2-waiting-attendee')
s=rep(s,
"$count=(int)$wpdb->get_var($wpdb->prepare(\"SELECT COUNT(*) FROM $table WHERE live_event_id=%d AND state IN ('waiting','approved','joined')\",$fresh['id']));",
"$count=VWLB_DB::read_var($wpdb->prepare(\"SELECT COUNT(*) FROM $table WHERE live_event_id=%d AND state IN ('waiting','approved','joined')\",$fresh['id']),'r119_waiting_room_capacity');if(is_wp_error($count))return $count;$count=(int)$count;",
'F2-waiting-count')
# F3 waiting-room post-insert projection.
s=rep(s,
"$attendee_public=$existing?($existing['public_id']??''):(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.$table.' WHERE id=%d',$id));return array('attendee_public_id'=>$attendee_public",
"$attendee_public=$existing?($existing['public_id']??''):VWLB_DB::read_var($wpdb->prepare('SELECT public_id FROM '.$table.' WHERE id=%d',$id),'r119_waiting_room_projection');if(is_wp_error($attendee_public))return $attendee_public;$attendee_public=(string)$attendee_public;return array('attendee_public_id'=>$attendee_public",
'F3-waiting-projection')
# F2 recording consent lock.
s=rep(s,
"$row=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE\",$event['id'],get_current_user_id()),ARRAY_A);if(!$row)",
"$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE\",$event['id'],get_current_user_id()),'r119_recording_consent_lock');if(is_wp_error($row))return $row;if(!$row)",
'F2-consent-lock')
# F7 question moderation lock.
s=rep(s,
"$row=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE public_id=%s FOR UPDATE\",VWLB_Helpers::text($question_id,64)),ARRAY_A);if(!$row)",
"$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE public_id=%s FOR UPDATE\",VWLB_Helpers::text($question_id,64)),'r119_question_lock');if(is_wp_error($row))return $row;if(!$row)",
'F7-question-lock')
# F3 public-id projections.
s=rep(s,
"return array('public_id'=>$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('chapters').' WHERE id=%d',$wpdb->insert_id)),'start_seconds'=>$start",
"$chapter_public=VWLB_DB::read_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('chapters').' WHERE id=%d',$wpdb->insert_id),'r119_chapter_projection');if(is_wp_error($chapter_public))return $chapter_public;return array('public_id'=>(string)$chapter_public,'start_seconds'=>$start",
'F3-chapter-projection')
s=rep(s,
"$id=(int)$wpdb->insert_id;$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_questions').' WHERE id=%d',$id));VWLB_Helpers::audit",
"$id=(int)$wpdb->insert_id;$public=VWLB_DB::read_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_questions').' WHERE id=%d',$id),'r119_question_projection');if(is_wp_error($public))return $public;$public=(string)$public;VWLB_Helpers::audit",
'F3-question-projection')
s=rep(s,
"$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_resources').' WHERE id=%d',$id));return array('public_id'=>$public",
"$public=VWLB_DB::read_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('live_resources').' WHERE id=%d',$id),'r119_resource_projection');if(is_wp_error($public))return $public;$public=(string)$public;return array('public_id'=>$public",
'F3-resource-projection')
# F4 recording finalization consent count.
s=rep(s,
"$missing=(int)$wpdb->get_var($wpdb->prepare(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('live_attendees').\" WHERE live_event_id=%d AND state IN ('approved','joined') AND (recording_consent=0 OR consent_version<>%s)\",$event['id'],$required));if($missing>0)",
"$missing=VWLB_DB::read_var($wpdb->prepare(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('live_attendees').\" WHERE live_event_id=%d AND state IN ('approved','joined') AND (recording_consent=0 OR consent_version<>%s)\",$event['id'],$required),'r119_recording_consent_count');if(is_wp_error($missing))return $missing;$missing=(int)$missing;if($missing>0)",
'F4-consent-count')
# F5 premiere and download-token rows.
s=rep(s,
"$existing=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d LIMIT 1\",$live['id']),ARRAY_A);if($existing)",
"$existing=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE live_event_id=%d LIMIT 1\",$live['id']),'r119_premiere_mapping');if(is_wp_error($existing))return $existing;if($existing)",
'F5-premiere')
s=rep(s,
"global $wpdb;$table=VWLB_Helpers::table('download_tokens');$row=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE public_id=%s\",VWLB_Helpers::text($public_id,64)),ARRAY_A);\n\t\tif(!$row",
"global $wpdb;$table=VWLB_Helpers::table('download_tokens');$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE public_id=%s\",VWLB_Helpers::text($public_id,64)),'r119_download_token');if(is_wp_error($row))return $row;\n\t\tif(!$row",
'F5-download-token')
# Propagate podcast episode primitive failures in extension download paths.
s=rep(s,
"$object='video'===$object_type?VWLB_Repository::find('videos',$object_id):VWLB_Podcasts::episode($object_id,true);\n\t\tif(!$object)",
"$object='video'===$object_type?VWLB_Repository::find('videos',$object_id):VWLB_Podcasts::episode($object_id,true);if(is_wp_error($object))return $object;\n\t\tif(!$object)",
'F9-download-create-caller')
s=rep(s,
"$object='video'===$row['object_type']?VWLB_Repository::video_bundle($row['object_id']):VWLB_Podcasts::episode($row['object_id'],true);\n\t\tif(!$object",
"$object='video'===$row['object_type']?VWLB_Repository::video_bundle($row['object_id']):VWLB_Podcasts::episode($row['object_id'],true);if(is_wp_error($object))return $object;\n\t\tif(!$object",
'F9-download-resolve-caller')
# F7/F8 schedule-live-extras lock and reminder enumeration.
s=rep(s,
"$fresh=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $events WHERE id=%d FOR UPDATE\",$event['id']),ARRAY_A);if(!$fresh)return VWLB_Helpers::error('vwlb_live_missing'",
"$fresh=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $events WHERE id=%d FOR UPDATE\",$event['id']),'r119_live_extras_lock');if(is_wp_error($fresh))return $fresh;if(!$fresh)return VWLB_Helpers::error('vwlb_live_missing'",
'F7-live-extras-lock')
s=rep(s,
"$old=$wpdb->get_results(\"SELECT id,input_json FROM $jobs_table WHERE job_type='send_live_reminder' AND status IN ('pending','retry') AND asset_id=0 LIMIT 500\",ARRAY_A);foreach($old as $job)",
"$old=VWLB_DB::read_results(\"SELECT id,input_json FROM $jobs_table WHERE job_type='send_live_reminder' AND status IN ('pending','retry') AND asset_id=0 LIMIT 500\",'r119_live_reminder_reconcile');if(is_wp_error($old))return $old;foreach($old as $job)",
'F8-reminder-results')
# F6 status counters.
s=rep(s,
"$dead=(int)$wpdb->get_var(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('processing_jobs').\" WHERE status='dead'\");\n\t\t$active_uploads=(int)$wpdb->get_var(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('upload_sessions').\" WHERE status='active'\");",
"$dead=VWLB_DB::read_var(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('processing_jobs').\" WHERE status='dead'\",'r119_status_dead_jobs');if(is_wp_error($dead))return $dead;$dead=(int)$dead;\n\t\t$active_uploads=VWLB_DB::read_var(\"SELECT COUNT(*) FROM \".VWLB_Helpers::table('upload_sessions').\" WHERE status='active'\",'r119_status_active_uploads');if(is_wp_error($active_uploads))return $active_uploads;$active_uploads=(int)$active_uploads;",
'F6-status')
# F8 creator studio projections.
s=rep(s,
"global $wpdb;$uid=get_current_user_id();$videos=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,status,visibility,view_count,like_count,dislike_count,updated_at FROM '.VWLB_Helpers::table('videos').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);\n\t\t$live=$wpdb->get_results($wpdb->prepare('SELECT public_id,title,status,scheduled_start,visibility,updated_at FROM '.VWLB_Helpers::table('live_events').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);\n\t\t$jobs=$wpdb->get_results($wpdb->prepare('SELECT j.public_id,j.job_type,j.status,j.attempts,j.error_code,j.updated_at FROM '.VWLB_Helpers::table('processing_jobs').' j INNER JOIN '.VWLB_Helpers::table('media_assets').' a ON a.id=j.asset_id WHERE a.owner_id=%d ORDER BY j.id DESC LIMIT 100',$uid),ARRAY_A);\n\t\t$rights=$wpdb->get_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);",
"global $wpdb;$uid=get_current_user_id();$videos=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,title,status,visibility,view_count,like_count,dislike_count,updated_at FROM '.VWLB_Helpers::table('videos').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),'r119_creator_videos');if(is_wp_error($videos))return $videos;\n\t\t$live=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,title,status,scheduled_start,visibility,updated_at FROM '.VWLB_Helpers::table('live_events').' WHERE owner_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',$uid),'r119_creator_live');if(is_wp_error($live))return $live;\n\t\t$jobs=VWLB_DB::read_results($wpdb->prepare('SELECT j.public_id,j.job_type,j.status,j.attempts,j.error_code,j.updated_at FROM '.VWLB_Helpers::table('processing_jobs').' j INNER JOIN '.VWLB_Helpers::table('media_assets').' a ON a.id=j.asset_id WHERE a.owner_id=%d ORDER BY j.id DESC LIMIT 100',$uid),'r119_creator_jobs');if(is_wp_error($jobs))return $jobs;\n\t\t$rights=VWLB_DB::read_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),'r119_creator_rights');if(is_wp_error($rights))return $rights;",
'F8-creator-results')
# F8 cleanup enumeration.
s=rep(s,
"$sessions=$wpdb->get_results($wpdb->prepare(\"SELECT * FROM \".VWLB_Helpers::table('upload_sessions').\" WHERE status IN ('active','failed') AND expires_at<%s LIMIT 100\",$now),ARRAY_A);\n\t\tforeach($sessions as $s){",
"$sessions=VWLB_DB::read_results($wpdb->prepare(\"SELECT * FROM \".VWLB_Helpers::table('upload_sessions').\" WHERE status IN ('active','failed') AND expires_at<%s LIMIT 100\",$now),'r119_cleanup_sessions');if(is_wp_error($sessions))return;\n\t\tforeach($sessions as $s){",
'F8-cleanup-results')
EXT.write_text(s)
print('R119 frozen correction applied')
