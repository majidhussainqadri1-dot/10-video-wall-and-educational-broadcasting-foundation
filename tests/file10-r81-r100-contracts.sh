#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
LEGACY="$ROOT/video-wall/video-wall.php"
fail(){ echo "FAIL r81-r100: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R81 — repository canonical-owner boundary.
need "Plugin Name: Video Wall and Live Broadcasting" "$P/video-wall-and-live-broadcasting.php" r81-canonical-plugin
need "Canonical runtime: ../video-wall-and-live-broadcasting/" "$LEGACY" r81-archive-marker
reject "Plugin Name:" "$LEGACY" r81-no-legacy-plugin-header
reject "register_activation_hook" "$LEGACY" r81-no-legacy-activation
reject "add_action( 'plugins_loaded'" "$LEGACY" r81-no-legacy-runtime
need 'SOURCE="$ROOT/video-wall-and-live-broadcasting"' "$ROOT/tools/build-package.sh" r81-package-canonical-source

# R85 — canonical frontend deep links must not turn DB outages into false 404s.
need "vwlb_frontend_state_unreadable" "$P/includes/class-vwlb-plugin.php" r85-unavailable-error
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-plugin.php" r85-repository-failure
need "\$wpdb->last_error=''" "$P/includes/class-vwlb-plugin.php" r85-channel-db-reset
need "status_header(503)" "$P/includes/class-vwlb-plugin.php" r85-http-503
need "vwlb_route_unavailable" "$P/templates/route.php" r85-unavailable-template
need "Video service temporarily unavailable" "$P/templates/route.php" r85-user-state

# R86 — privacy anonymization must preserve unique-key validity across multiple erased users.
need "anonymized_subject_id" "$P/includes/class-vwlb-privacy.php" r86-surrogate-helper
need "array('live_attendees','user_id',array('user_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-live-surrogate
need "array('creator_metrics_daily','owner_id',array('owner_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-metrics-surrogate
reject "array('live_attendees','user_id',array('user_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-live-zero
reject "array('creator_metrics_daily','owner_id',array('owner_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-metrics-zero
need "UNIQUE KEY event_user (live_event_id,user_id)" "$P/includes/class-vwlb-extensions.php" r86-live-unique-contract
need "UNIQUE KEY metric_object (metric_date,owner_id,object_type,object_id)" "$P/includes/class-vwlb-extensions.php" r86-metrics-unique-contract

# R91 — unlisted access is signed, non-indexable and secure-delivery scoped.
need "'unlisted'===\$visibility" "$P/includes/class-vwlb-security.php" r91-unlisted-explicit
need "vwlb_unlisted_access_authorized" "$P/includes/class-vwlb-security.php" r91-unlisted-fail-closed-hook
need "VWLB_R91_Unlisted_Access_Guard::register" "$P/video-wall-and-live-broadcasting.php" r91-guard-registered
need "vwlb_exp" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-expiry-proof
need "hash_hmac('sha256','v1|'" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-signed-proof
need "X-Robots-Tag" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-noindex
need "Cache-Control','private, no-store" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-no-store
need "vwlb_secure_playback_grant" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-video-secure-delivery
need "vwlb_secure_live_playback_grant" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-live-secure-delivery
need "vwlb_public_podcast_feed_grant" "$P/includes/class-vwlb-r91-unlisted-access-guard.php" r91-podcast-secure-delivery

# R94 — emergency-end 5xx outcomes must stay reconciliation-required.
need "VWLB_R94_Live_External_Uncertainty_Guard::register" "$P/video-wall-and-live-broadcasting.php" r94-guard-registered
need "vwlb_provider_emergency_end_reconcile_required" "$P/includes/class-vwlb-r94-live-external-uncertainty-guard.php" r94-typed-uncertainty
need "if(\$status<500)return \$response" "$P/includes/class-vwlb-r94-live-external-uncertainty-guard.php" r94-server-failure-boundary
need "reconcile_required'=>true" "$P/includes/class-vwlb-r94-live-external-uncertainty-guard.php" r94-reconcile-contract
need "vwlb_provider_emergency_end_reconcile_required" "$P/includes/class-vwlb-r60-final-hardening.php" r94-r60-recognizes-unsafe
need "(?:credentials|kill|future-config/apply)" "$P/includes/class-vwlb-r60-final-hardening.php" r94-kill-external-route

# R97 — privacy erasure must cover private upload files regardless of session status and reject unsafe paths/races.
need "VWLB_R97_Privacy_Storage_Erasure_Guard::register" "$P/video-wall-and-live-broadcasting.php" r97-guard-registered
need "WHERE owner_id=%d ORDER BY id ASC LIMIT %d" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-all-status-upload-enumeration
reject "status='active'" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-no-active-only-erasure
need "basename((string)\$filename)" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-basename
need "realpath(\$base)" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-realpath
need "is_link(\$path)" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-symlink
need "LOCK_EX|LOCK_NB" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-concurrent-write-lock
need "VWLB_R50_Privacy_Proof::erase" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-completion-proof-chain

printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
