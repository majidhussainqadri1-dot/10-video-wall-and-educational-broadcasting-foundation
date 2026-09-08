#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
# R101 — production studio must be reload-safe and must not expose native integer IDs.
grep -F "production/state','GET','production_state','broadcast'" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'function production_state' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'live_event_public_id' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'source_public_ids' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'Production studio state could not be verified.' "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F 'data-vwlb-production-state' "$P/includes/class-vwlb-future-frontend.php" >/dev/null
grep -F '/production/state' "$P/assets/js/vwlb-future.js" >/dev/null
# Public DTO query selects must not emit raw live_event_id/user_id/credential refs for the studio-state response.
python3 - "$P/includes/class-vwlb-future-rest.php" <<'PY'
import pathlib,re,sys
text=pathlib.Path(sys.argv[1]).read_text()
m=re.search(r'public function production_state\s*\([^)]*\)\s*\{(.*?)\n\t\}',text,re.S)
assert m, 'production_state missing'
body=m.group(1)
for forbidden in ("'user_id'=>", "'live_event_id'=>", "'credential_ref'=>"):
    assert forbidden not in body, f'raw/internal field leaked in production state: {forbidden}'
PY

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

# R103 — schema/migration completeness and non-REST public opaque-ID boundary.
grep -F 'SHOW COLUMNS FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'SHOW INDEX FROM' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_column_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_schema_index_missing' "$P/includes/class-vwlb-db.php" >/dev/null
grep -F 'vwlb_legacy_migration_cursor' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F 'WHERE id>%d ORDER BY id ASC LIMIT 500' "$P/includes/class-vwlb-compatibility.php" >/dev/null
! grep -F 'ORDER BY id ASC LIMIT 10000' "$P/includes/class-vwlb-compatibility.php" >/dev/null
grep -F '^video/([a-z][a-z0-9]*_[a-z0-9]+)/([^/]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^live/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F '^podcast/([a-z][a-z0-9]*_[a-z0-9]+)/?$' "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-plugin.php" >/dev/null
grep -F "is_public_id((string)\$id)" "$P/includes/class-vwlb-frontend.php" >/dev/null
grep -F 'global $wpdb;if(!VWLB_Helpers::is_public_id' "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "item['thumbnail_url']" "$P/includes/class-vwlb-frontend.php" >/dev/null
! grep -F "item['thumbnail_id']" "$P/includes/class-vwlb-frontend.php" >/dev/null

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

# R105 — privacy export/erasure propagation and scoped legal-hold enforcement.
grep -F "class VWLB_R105_Privacy_Lifecycle" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "VWLB_R105_Privacy_Lifecycle::register()" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "vwlb-attribution" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
for token in moderation takedowns captions production_sources production_scenes simulcast_targets media_tracks video_annotations live_polls consent_links watermark_policies stream_credentials creator_metrics_daily audit; do
  grep -F "array('$token'" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null || { echo "R105 exporter coverage missing: $token" >&2; exit 1; }
done
grep -F "vwlb_privacy_legal_hold_decision" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "lawful_basis" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "reference_hash" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "expires_at" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "process_canonical" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "process_fallback" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "decrypt_evidence_fallback" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "encrypt_evidence_fallback" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "VWLB_R97_Privacy_Storage_Erasure_Guard::erase" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "base_retained" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
grep -F "held=false" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" >/dev/null
! grep -F "credential_hash'" "$P/includes/class-vwlb-r105-privacy-lifecycle.php" | grep -F "SELECT" >/dev/null

# R106 — upload/scan/transcode safety.
grep -F "LOCK_EX|LOCK_NB" "$P/includes/class-vwlb-r76-cleanup-durability.php" >/dev/null
grep -F "vwlb_upload_cleanup_file_busy" "$P/includes/class-vwlb-r76-cleanup-durability.php" >/dev/null
grep -F "vwlb_asset_checksum_unreadable" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "vwlb_malware_scanner_exception" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "vwlb_external_media_validation_exception" "$P/includes/class-vwlb-extensions.php" >/dev/null
grep -F "vwlb_asset_validation_exception" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "Media validation failed safely and will follow the normal retry policy." "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "'/media/resumable/(?P<id>[a-z][a-z0-9]*_[a-z0-9]+)/complete'" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null
! grep -F "'/media/resumable/(?P<id>[A-Za-z0-9_-]+)/complete'" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null

# R107 — provider/webhook/secrets/readiness boundaries.
grep -F "public static function contains_raw_secret" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "client_secret" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "str_ends_with( \$key, '_secret' )" "$P/includes/class-vwlb-helpers.php" >/dev/null
grep -F "VWLB_Helpers::contains_raw_secret(\$safe)" "$P/includes/class-vwlb-future-adapters.php" >/dev/null
grep -F "return VWLB_Helpers::contains_raw_secret( \$value );" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "return VWLB_Helpers::contains_raw_secret( \$value );" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null
! grep -F "[A-Za-z0-9_-]+" "$P/includes/class-vwlb-future-safety.php" >/dev/null
! grep -E "'/((captions|videos|live-events)/[^']*)\[A-Za-z0-9_-\]\+" "$P/includes/class-vwlb-sequential-review-hardening.php" >/dev/null
grep -F "Playback enrichment state could not be verified safely." "$P/includes/class-vwlb-future-safety.php" >/dev/null
! grep -F "\$payload['video']['id']" "$P/includes/class-vwlb-future-safety.php" >/dev/null
grep -F "vwlb_provider_live_compensation_exception" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "try{do_action('vwlb_provider_ingest_compensation_requested'" "$P/includes/class-vwlb-r46-stream-credential-durability.php" >/dev/null
grep -F "provider_readiness" "$P/includes/class-vwlb-observability.php" >/dev/null
grep -F "capability_semantics" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "implementation_presence_not_runtime_readiness" "$P/includes/class-vwlb-future-rest.php" >/dev/null
grep -F "runtime_readiness" "$P/includes/class-vwlb-future-rest.php" >/dev/null

# R108 — live lifecycle/emergency/reconciliation/concurrency/idempotency.
grep -F "vwlb_provider_proof_forbidden" "$P/includes/class-vwlb-rest.php" >/dev/null
! grep -F "d['provider_proof']??array()" "$P/includes/class-vwlb-rest.php" >/dev/null
grep -F "reconcile_provider_observation" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "vwlb_provider_live_reconcile_observation" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "vwlb_provider_live_webhook_observation" "$P/includes/class-vwlb-integrations.php" >/dev/null
grep -F "vwlb_verified_live_reconcile_failed" "$P/includes/class-vwlb-integrations.php" >/dev/null
grep -F "vwlb_provider_emergency_end_result" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "positively confirmed" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "provider_confirmed'=>true" "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "LIVE_RECONCILE_CURSOR_OPTION='vwlb_r108_live_reconcile_cursor'" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "id>%d ORDER BY id ASC LIMIT 100" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "vwlb_live_reconcile_cursor_failed" "$P/includes/class-vwlb-jobs.php" >/dev/null
grep -F "REDUNDANCY_RECONCILE_CURSOR_OPTION = 'vwlb_r108_redundancy_reconcile_cursor'" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "ORDER BY c.id ASC LIMIT 100" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "vwlb_redundancy_reconcile_read_failed" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "vwlb_redundancy_reconcile_exception" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
! grep -F "[A-Za-z0-9_-]+" "$P/includes/class-vwlb-review-hardening.php" >/dev/null
grep -F "vwlb_r108_live_reconcile_cursor" "$P/uninstall.php" >/dev/null
grep -F "vwlb_r108_redundancy_reconcile_cursor" "$P/uninstall.php" >/dev/null


# R109 — rights/takedown/recording-consent/replay integrity.
grep -F "class VWLB_R109_Rights_Consent_Replay_Guard" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "VWLB_R109_Rights_Consent_Replay_Guard::register()" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F 'current_delivery_allowed($object,$purpose)' "$P/includes/class-vwlb-security.php" >/dev/null
grep -F "vwlb_rights_consent_unverifiable" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_rights_territory_authorized" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_consent_terminal_history" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "VideoConsentRestricted" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "CONSENT_CURSOR_OPTION='vwlb_r109_consent_expiry_cursor'" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "id>%d ORDER BY id ASC LIMIT %d" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_consent_expiry_read_failed" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "remove_action('vwlb_reconcile_states',array('VWLB_Future_Safety','reconcile_consent_expiry'),20)" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F 'assert_consent_transition($current,$status)' "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "private static function restrict_video_for_consent" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F "consent_auto_restrict" "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F 'record_consent_change($locked,$id,$before,$status,$ref)' "$P/includes/class-vwlb-future-intelligence.php" >/dev/null
grep -F 'assert_replay($event,$video)' "$P/includes/class-vwlb-live.php" >/dev/null
grep -F "vwlb_replay_not_authorized" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_replay_lineage_invalid" "$P/includes/class-vwlb-r109-rights-consent-replay-guard.php" >/dev/null
grep -F "vwlb_r109_consent_expiry_cursor" "$P/uninstall.php" >/dev/null


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

# R111 — public read/enrichment failures must never become successful empty/partial payloads.
grep -F "class-vwlb-r111-public-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "VWLB_R111_Public_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "VWLB_DB::read_results(\$query,'r111_public_live_browse')" "$P/includes/class-vwlb-r111-public-read-integrity.php" >/dev/null
grep -F "if(is_wp_error(\$chapters))return \$chapters" "$P/includes/class-vwlb-r111-public-read-integrity.php" >/dev/null
grep -F "vwlb_database_read_failed" "$P/includes/class-vwlb-r111-public-read-integrity.php" >/dev/null

# R112 — authenticated creator/operational reads must fail closed.
grep -F "class-vwlb-r112-operational-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "VWLB_R112_Operational_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "r112_creator_videos" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null
grep -F "r112_observability_dead_jobs" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null
grep -F "r112_podcast_series_resolver" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null
grep -F "r112_admin_preflight_" "$P/includes/class-vwlb-r112-operational-read-integrity.php" >/dev/null
echo 'R101-R120 contracts PASS'
# R113 mutation read-integrity correction
grep -F "class-vwlb-r113-mutation-read-integrity.php" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "VWLB_R113_Mutation_Read_Integrity::register" "$P/video-wall-and-live-broadcasting.php" >/dev/null
grep -F "r113_asset_public_resolver" "$P/includes/class-vwlb-r113-mutation-read-integrity.php" >/dev/null
grep -F "r113_publish_series" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "Podcast episode state could not be verified safely" "$P/includes/class-vwlb-podcasts.php" >/dev/null
grep -F "vwlb_live_mutation_read_failed" "$P/includes/class-vwlb-live.php" >/dev/null

# R114 direct database mutation-read integrity correction
grep -F "r114_publication_caption_count" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_caption_parent_lock" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_caption_version" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_playback_session_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_progress_session_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_interaction_video_lock" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_interaction_lookup" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_interaction_count" "$P/includes/class-vwlb-videos.php" >/dev/null
grep -F "r114_moderation_lock" "$P/includes/class-vwlb-moderation.php" >/dev/null
