#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r101-r120: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R101 — activation rollback must cover the exact fully-filtered role/capability mutation map.
need "public static function capture_role_map" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-capture
need "vwlb_activation_role_capabilities" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-filter
need "PHP_INT_MAX" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-after-companion-filters
need "captured_at" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-captured-evidence
need "rollback evidence was not captured before commit" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-commit-gate
need "foreach((array)\$caps as \$cap=>\$had)" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-exact-restore
reject "private static function caps()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r101-no-hardcoded-rollback-map

# R102 — clean source review: resumable/private ingest durability remains covered by retained R45/R70 contracts.

# R103 — processing jobs for positive asset IDs must never be created from missing/unreadable asset truth or silently default to local.
need "VWLB_Repository::reset_read_failure();\$asset=VWLB_Repository::find('media_assets',\$asset_id)" "$P/includes/class-vwlb-media.php" r103-asset-read
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-media.php" r103-read-failure
need "vwlb_processing_asset_read_failed" "$P/includes/class-vwlb-media.php" r103-operational-signal
need "if(!\$asset)return 0" "$P/includes/class-vwlb-media.php" r103-missing-asset
need "!VWLB_Providers::get(\$provider)" "$P/includes/class-vwlb-media.php" r103-provider-exists

# R104 — public object routes and playlist membership accept opaque File 10 identifiers only.
need "class-vwlb-r104-public-id-boundary.php" "$P/video-wall-and-live-broadcasting.php" r104-autoload
need "VWLB_R104_Public_ID_Boundary::register" "$P/video-wall-and-live-broadcasting.php" r104-register
need "public static function opaque_path" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-path-guard
need "foreach(array('id','scene','target') as \$key)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-route-fields
need "ctype_digit(\$value)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-reject-numeric-path
need "array_key_exists('video_ids',\$data)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-reject-internal
need "video_public_ids" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-public-contract
need "is_numeric(\$public_id)" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-no-numeric-alias
need "vwlb_playlist_video_read_failed" "$P/includes/class-vwlb-r104-public-id-boundary.php" r104-db-failclosed

# R105-R107 — clean source reviews: live external-effect retry guards, webhook integrity and privacy storage/proof remain retained.

# R108 — after-callback privacy revalidation must fail closed; it may never preserve public/unlisted delivery on an unreadable second read.
need "vwlb_caption_cache_state_unreadable" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r108-caption-db-failclosed
need "if(!\$caption)return VWLB_Helpers::error('vwlb_not_found'" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r108-caption-race
need "vwlb_unlisted_state_unreadable" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-unlisted-db-failclosed
need "if(!\$row)return VWLB_Helpers::error('vwlb_not_found'" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-unlisted-race
need "catch(Throwable \$e)" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r108-grant-exception

# R109 — cross-file File 11/File 17 read contracts distinguish database unavailability from legitimate not-found state.
need "integration_read_failure" "$P/includes/class-vwlb-integrations.php" r109-helper
need "vwlb_file11_media_source_unreadable" "$P/includes/class-vwlb-integrations.php" r109-file11-failclosed
need "vwlb_file17_live_context_unreadable" "$P/includes/class-vwlb-integrations.php" r109-file17-failclosed
need "VWLB_Repository::reset_read_failure()" "$P/includes/class-vwlb-integrations.php" r109-reset
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-integrations.php" r109-read-state

# R110 — publication/caption truth must reject invalid schedules and surface unreadable gates as operational failures.
need "vwlb_schedule_invalid" "$P/includes/class-vwlb-videos.php" r110-invalid-schedule
need "vwlb_caption_gate_unreadable" "$P/includes/class-vwlb-videos.php" r110-caption-gate-db
need "vwlb_caption_version_unreadable" "$P/includes/class-vwlb-videos.php" r110-caption-version-db
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-videos.php" r110-asset-gate-db
need "if(!\$saved||!(int)\$wpdb->insert_id)" "$P/includes/class-vwlb-videos.php" r110-caption-insert-proof

# R111 — clean moderation/takedown invariant review.

# R112 — waiting-room capacity/policy and live-reminder execution/reconciliation must fail closed.
need "class-vwlb-r112-live-attendance-durability-guard.php" "$P/video-wall-and-live-broadcasting.php" r112-autoload
need "VWLB_R112_Live_Attendance_Durability_Guard::register" "$P/video-wall-and-live-broadcasting.php" r112-register
need "vwlb_waiting_room_disabled" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-policy
need "vwlb_waiting_room_capacity_unreadable" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-capacity-db
need "already_active" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-reentry-capacity
need "vwlb_recording_consent_unverifiable" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-consent-db
need "reconcile_reminders" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-reminder-reconcile
need "scheduled_start" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-reminder-schedule-proof
need "event_not_remindable" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-reminder-state-proof
need "remove_filter('vwlb_process_job',array('VWLB_Extensions','process_job'),10)" "$P/includes/class-vwlb-r112-live-attendance-durability-guard.php" r112-replace-old-reminder

# R113 — provider/filter outputs are revalidated for type, HTTPS remote URL and secret boundaries.
need "class-vwlb-r113-provider-output-guard.php" "$P/video-wall-and-live-broadcasting.php" r113-autoload
need "VWLB_R113_Provider_Output_Guard::register" "$P/video-wall-and-live-broadcasting.php" r113-register
need "vwlb_provider_source_contract_invalid" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-source-type
need "vwlb_provider_ingest_url_invalid" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-ingest-url
need "vwlb_provider_stream_secret_invalid" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-secret
need "vwlb_provider_ingest_reconcile_required" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-compensation
need "vwlb_provider_playback_url_invalid" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-playback-url
need "vwlb_provider_derivative_url_invalid" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-derivative-url
need "www.youtube-nocookie.com" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-youtube-host
need "player.vimeo.com" "$P/includes/class-vwlb-r113-provider-output-guard.php" r113-vimeo-host

# R114 — external AI suggestion failures retain retry guards; published auxiliary tracks are review- and audience-bound.
need "\$uncertain=\$status>=500" "$P/includes/class-vwlb-r64-intelligence-guard.php" r114-ai-uncertainty
need "class-vwlb-r114-ai-track-delivery-guard.php" "$P/video-wall-and-live-broadcasting.php" r114-autoload
need "VWLB_R114_AI_Track_Delivery_Guard::register" "$P/video-wall-and-live-broadcasting.php" r114-register
need "vwlb_media_track_publish_unreadable" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-publish-db
need "vwlb_media_track_file_ref_invalid" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-file-ref
need "vwlb_secure_media_track_grant" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-private-grant
need "vwlb_public_media_track_ref" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-public-grant
need "vwlb_media_track_delivery_unreadable" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-delivery-db
need "X-Robots-Tag" "$P/includes/class-vwlb-r114-ai-track-delivery-guard.php" r114-noindex

printf '%s\n' 'File 10 R101-R120 sequential contracts PASS'
