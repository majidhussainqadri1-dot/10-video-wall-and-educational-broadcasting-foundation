#!/usr/bin/env bash
set -euo pipefail
EXT='video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
REST='video-wall-and-live-broadcasting/includes/class-vwlb-extended-rest.php'
POD='video-wall-and-live-broadcasting/includes/class-vwlb-podcasts.php'
DB='video-wall-and-live-broadcasting/includes/class-vwlb-db.php'

need(){ grep -Fq -- "$2" "$1" || { echo "FAIL R119 missing: $2" >&2; exit 1; }; }
forbid(){ ! grep -Fq -- "$2" "$1" || { echo "FAIL R119 obsolete direct read remains: $2" >&2; exit 1; }; }

# Safe primitives must exist.
need "$DB" 'public static function read_row('
need "$DB" 'public static function read_results('
need "$DB" 'public static function read_var('

# F1 series resolver + propagation.
need "$REST" "'r119_podcast_series_resolver'"
need "$REST" "if(is_wp_error(\$d['series_id']))return \$d['series_id'];"

# F2/F7 mutation locks and counts.
for ctx in r119_waiting_room_event_lock r119_waiting_room_attendee_lock r119_waiting_room_capacity r119_recording_consent_lock r119_question_lock r119_live_extras_lock; do need "$EXT" "'$ctx'"; done

# F3 post-insert projections.
for ctx in r119_chapter_projection r119_waiting_room_projection r119_question_projection r119_resource_projection; do need "$EXT" "'$ctx'"; done

# F4 high-integrity recording consent count.
need "$EXT" "'r119_recording_consent_count'"
need "$EXT" 'if(is_wp_error($missing))return $missing;'

# F5 premiere/download resolution.
need "$EXT" "'r119_premiere_mapping'"
need "$EXT" "'r119_download_token'"

# F6 operational status counters.
need "$EXT" "'r119_status_dead_jobs'"
need "$EXT" "'r119_status_active_uploads'"

# F7/F8 remaining row/result boundaries.
need "$EXT" "'r119_upload_session'"
need "$EXT" "'r119_live_reminder_reconcile'"
for ctx in r119_creator_videos r119_creator_live r119_creator_jobs r119_creator_rights r119_cleanup_sessions; do need "$EXT" "'$ctx'"; done

# F9 podcast primitive and caller propagation.
need "$POD" "'r119_podcast_episode'"
need "$POD" 'if(is_wp_error($row))return $row;'
need "$POD" 'if(is_wp_error($ep))return $ep;'
need "$EXT" 'VWLB_Podcasts::episode($object_id,true);if(is_wp_error($object))return $object;'
need "$EXT" "VWLB_Podcasts::episode(\$row['object_id'],true);if(is_wp_error(\$object))return \$object;"

# Targeted obsolete direct reads must be absent after correction.
forbid "$REST" "return (int)\$wpdb->get_var(\$wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('podcast_series')"
forbid "$POD" '$row=$wpdb->get_row("SELECT * FROM ".VWLB_Helpers::table('\''podcast_episodes'\'')'
forbid "$EXT" '$fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE id=%d FOR UPDATE"'
forbid "$EXT" '$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d LIMIT 1"'
forbid "$EXT" '$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE public_id=%s"'
forbid "$EXT" '$missing=(int)$wpdb->get_var('

echo 'PASS R119 fail-closed residual read contracts'
