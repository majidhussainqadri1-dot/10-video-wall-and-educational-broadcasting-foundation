#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL fourth-fresh-20: $3" >&2; exit 1; }; }
forbid(){ ! grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL fourth-fresh-20: $3" >&2; exit 1; }; }
# R01 batch — completed review first, then all findings corrected.
need "Version: 1.2.5-rc1" "$P/video-wall-and-live-broadcasting.php" r01-version
need "Stable tag: 1.2.5-rc1" "$P/readme.txt" r01-readme-version
need "vwlb_internal_identifier_forbidden" "$P/includes/class-vwlb-future-rest.php" r01-no-internal-api-ids
need "source_public_ids" "$P/includes/class-vwlb-future-rest.php" r01-source-public-ids
need "user_public_id" "$P/includes/class-vwlb-future-rest.php" r01-user-public-id
need "track_public_id" "$P/includes/class-vwlb-future-rest.php" r01-track-public-id
need "live_event_public_id" "$P/includes/class-vwlb-future-rest.php" r01-health-public-id
forbid "VWLB_Future_Intelligence::live" "$P/includes/class-vwlb-future-adapters.php" r01-private-live-call
forbid "VWLB_Future_Intelligence::public_row" "$P/includes/class-vwlb-future-adapters.php" r01-private-row-call
need "vwlb_track_generation_reconcile_required" "$P/includes/class-vwlb-future-adapters.php" r01-provider-reconcile
need "VWLB_DB::transaction(function()use(\$result,\$allowed,\$kinds,\$video)" "$P/includes/class-vwlb-future-adapters.php" r01-ai-atomic
need "vwlb_poll_too_many_options" "$P/includes/class-vwlb-future-safety.php" r01-poll-bound
need "LIMIT 500" "$P/includes/class-vwlb-future-safety.php" r01-list-bounds
need "vwlb_operational_failure" "$P/includes/class-vwlb-future-safety.php" r01-cleanup-observable
need "persist_metrics" "$P/includes/class-vwlb-observability.php" r01-metrics-persist
need "erasure_has_more" "$P/includes/class-vwlb-privacy.php" r01-privacy-batching
need "status_header(404)" "$P/includes/class-vwlb-plugin.php" r01-route-404
need "version state could not be recorded durably" "$P/includes/class-vwlb-activator.php" r01-activation-version
php -r "define('ABSPATH','/'); require '$P/includes/class-vwlb-future-intelligence.php'; foreach(['live','public_row'] as \$m){\$r=new ReflectionMethod('VWLB_Future_Intelligence',\$m); if(!\$r->isPrivate()) exit(2);}" >/dev/null
need "register_rest_overrides" "$P/includes/class-vwlb-future-safety.php" r01-bounded-rest-overrides
need "rest_playback" "$P/includes/class-vwlb-future-safety.php" r01-bounded-playback
need "rest_poll_create" "$P/includes/class-vwlb-future-safety.php" r01-bounded-poll-rest
need "VWLB_Future_Safety::annotations" "$P/includes/class-vwlb-future-frontend.php" r01-bounded-frontend-annotations
need "class-vwlb-future-safety.php" "$P/video-wall-and-live-broadcasting.php" r01-safety-autoload
need "VWLB_Future_Safety::register" "$P/includes/class-vwlb-plugin.php" r01-safety-register

# R02 batch — full review frozen before correction; public boundaries, privacy and reliability hardened together.
need "class-vwlb-review-hardening.php" "$P/video-wall-and-live-broadcasting.php" r02-hardening-autoload
need "VWLB_Review_Hardening::register" "$P/includes/class-vwlb-plugin.php" r02-hardening-register
need "vwlb_public_identifier_required" "$P/includes/class-vwlb-review-hardening.php" r02-route-public-id
need "channel_public_id" "$P/includes/class-vwlb-review-hardening.php" r02-channel-public-id
need "video_public_ids" "$P/includes/class-vwlb-review-hardening.php" r02-playlist-public-ids
need "object_public_id" "$P/includes/class-vwlb-review-hardening.php" r02-download-public-id
need "series_public_id" "$P/includes/class-vwlb-review-hardening.php" r02-podcast-series-public-id
need "vwlb_secure_podcast_playback_grant" "$P/includes/class-vwlb-review-hardening.php" r02-protected-podcast-grant
need "target_public_id" "$P/includes/class-vwlb-review-hardening.php" r02-creator-target-public-id
need "anonymized_user_id" "$P/includes/class-vwlb-review-hardening.php" r02-privacy-attendee-surrogate
need "NOT EXISTS (SELECT 1 FROM \$captions_table newer" "$P/includes/class-vwlb-repository.php" r02-caption-current-only
need "LIMIT 100" "$P/includes/class-vwlb-repository.php" r02-caption-bound
need "future_live_config" "$P/uninstall.php" r02-purge-future
need "podcast_episodes" "$P/uninstall.php" r02-purge-podcasts
need "live_poll_responses" "$P/uninstall.php" r02-purge-polls
need "vwlb_future_schema_version" "$P/uninstall.php" r02-purge-future-option
need "vwlb-private-media" "$P/uninstall.php" r02-purge-private-media
need "vwlb_audit_fallback_" "$P/includes/class-vwlb-helpers.php" r02-audit-fallback
need "vwlb_outbox_fallback_" "$P/includes/class-vwlb-helpers.php" r02-outbox-fallback
need "vwlb_inbox_retry_" "$P/includes/class-vwlb-review-hardening.php" r02-inbox-retry
need "reconcile_inbox_retries" "$P/includes/class-vwlb-review-hardening.php" r02-inbox-reconcile
need "vwlb_cleanup_delete_failed" "$P/includes/class-vwlb-review-hardening.php" r02-cleanup-observable
need "VWLB_Repository::find('videos',\$r['id'])" "$P/includes/class-vwlb-review-hardening.php" r02-playback-canonical-row
forbid "'channel_id'=>\$r['channel_id']" "$P/includes/class-vwlb-review-hardening.php" r02-no-public-channel-pk
need "unset(\$v['credential_id'])" "$P/includes/class-vwlb-review-hardening.php" r02-credential-pk-redacted
