#!/usr/bin/env bash
set -euo pipefail
EXT='video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
REST='video-wall-and-live-broadcasting/includes/class-vwlb-extended-rest.php'
POD='video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
DB='video-wall-and-live-broadcasting/includes/class-vwlb-db.php'

need(){ grep -Fq -- "$2" "$1" || { echo "FAIL R119 missing: $2" >&2; exit 1; }; }
forbid(){ ! grep -Fq -- "$2" "$1" || { echo "FAIL R119 obsolete direct read remains: $2" >&2; exit 1; }; }

need "$DB" 'public static function read_row('
need "$DB" 'public static function read_results('
need "$DB" 'public static function read_var('

need "$REST" "'r119_podcast_series_resolver'"
need "$REST" "if(is_wp_error(\$d['series_id']))return \$d['series_id'];"

for ctx in r119_waiting_room_event_lock r119_waiting_room_attendee_lock r119_waiting_room_capacity r119_recording_consent_lock r119_question_lock r119_live_extras_lock; do need "$EXT" "'$ctx'"; done
for ctx in r119_chapter_projection r119_waiting_room_projection r119_question_projection r119_resource_projection; do need "$EXT" "'$ctx'"; done

need "$EXT" "'r119_recording_consent_count'"
need "$EXT" 'if(is_wp_error($missing))return $missing;'
need "$EXT" "'r119_premiere_mapping'"
need "$EXT" "'r119_download_token'"
need "$EXT" "'r119_status_dead_jobs'"
need "$EXT" "'r119_status_active_uploads'"

need "$EXT" "'r119_upload_session'"
# Both append_chunk() and complete_resumable() must stop before auth/array access
# when the authoritative upload-session reread itself failed.
[[ "$(grep -Fc 'if ( is_wp_error( $session ) ) return $session;' "$EXT")" -ge 2 ]] || { echo 'FAIL R119 upload-session WP_Error propagation incomplete' >&2; exit 1; }
need "$EXT" "'r119_live_reminder_reconcile'"
for ctx in r119_creator_videos r119_creator_live r119_creator_jobs r119_creator_rights r119_cleanup_sessions; do need "$EXT" "'$ctx'"; done

need "$POD" "'r119_podcast_episode'"
need "$POD" 'if(is_wp_error($row))return $row;'
need "$POD" 'if(is_wp_error($ep))return $ep;'
need "$EXT" 'VWLB_Podcasts::episode($object_id,true);if(is_wp_error($object))return $object;'
need "$EXT" "VWLB_Podcasts::episode(\$row['object_id'],true);if(is_wp_error(\$object))return \$object;"

forbid "$REST" "return (int)\$wpdb->get_var(\$wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series')"
forbid "$POD" '$row=$wpdb->get_row("SELECT * FROM ".VWLB_Helpers::table('\''podcast_episodes'\'')'
forbid "$EXT" '$fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE id=%d FOR UPDATE"'
forbid "$EXT" '$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d LIMIT 1"'
forbid "$EXT" '$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE public_id=%s"'
forbid "$EXT" '$missing=(int)$wpdb->get_var('

echo 'PASS R119 fail-closed residual read contracts'
