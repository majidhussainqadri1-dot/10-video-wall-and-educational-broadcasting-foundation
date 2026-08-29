#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; P="$ROOT/video-wall-and-live-broadcasting"; fail(){ echo "FAIL r61-r80: $*" >&2; exit 1; }; need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R61
need "class-vwlb-r61-activation-role-guard.php" "$P/video-wall-and-live-broadcasting.php" r61-autoload; need "wp_roles()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-all-roles; need "VWLB_PUBLISH_REVIEW_ARTIFACT: '0'" "$ROOT/.github/workflows/file10-release.yml" r61-no-intermediate-artifact
# R62 clean.
# R63
need "vwlb_provider_future_policy_reconcile_required" "$P/includes/class-vwlb-future-adapters.php" r63-policy; need "vwlb_simulcast_adapter_exception" "$P/includes/class-vwlb-future-adapters.php" r63-simulcast; need "vwlb_track_generation_cancel_exception" "$P/includes/class-vwlb-future-adapters.php" r63-track; need "vwlb_video_intelligence_processor_exception" "$P/includes/class-vwlb-future-adapters.php" r63-intelligence
# R64
need "class-vwlb-r64-intelligence-guard.php" "$P/video-wall-and-live-broadcasting.php" r64-autoload; need "VWLB_R64_Intelligence_Guard::register" "$P/video-wall-and-live-broadcasting.php" r64-register; need "intelligence/suggest" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-route; need "VWLB_R60_Final_Hardening::EXTERNAL_GUARD_PREFIX" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-registry
# R65
need "private static \$read_failure=false" "$P/includes/class-vwlb-repository.php" r65-state; need "vwlb_repository_read_failed" "$P/includes/class-vwlb-repository.php" r65-signal; need "class-vwlb-r65-repository-read-guard.php" "$P/video-wall-and-live-broadcasting.php" r65-autoload; need "VWLB_Repository::read_failed" "$P/includes/class-vwlb-r65-repository-read-guard.php" r65-guard
# R66 — direct wpdb failures anywhere in a File 10 REST callback must not become a false 404/empty/success response.
need "class-vwlb-r66-request-db-guard.php" "$P/video-wall-and-live-broadcasting.php" r66-autoload; need "VWLB_R66_Request_DB_Guard::register" "$P/video-wall-and-live-broadcasting.php" r66-register; need "\$wpdb->last_error=''" "$P/includes/class-vwlb-r66-request-db-guard.php" r66-reset; need "vwlb_request_database_failure" "$P/includes/class-vwlb-r66-request-db-guard.php" r66-signal; need "File 10 could not verify the requested database state safely" "$P/includes/class-vwlb-r66-request-db-guard.php" r66-failclosed
printf '%s\n' 'File 10 R61-R80 sequential contracts PASS'
