#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r61-r80: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R61
need "class-vwlb-r61-activation-role-guard.php" "$P/video-wall-and-live-broadcasting.php" r61-autoload
need "VWLB_R61_Activation_Role_Guard', 'activation_begin" "$P/video-wall-and-live-broadcasting.php" r61-begin
need "wp_roles()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-all-roles
need "VWLB_PUBLISH_REVIEW_ARTIFACT: '0'" "$ROOT/.github/workflows/file10-release.yml" r61-no-intermediate-artifact
# R62 clean.
# R63
need "vwlb_provider_future_policy_reconcile_required" "$P/includes/class-vwlb-future-adapters.php" r63-policy-exception
need "vwlb_simulcast_adapter_exception" "$P/includes/class-vwlb-future-adapters.php" r63-simulcast-exception
need "vwlb_track_generation_cancel_exception" "$P/includes/class-vwlb-future-adapters.php" r63-track-cancel-exception
need "vwlb_video_intelligence_processor_exception" "$P/includes/class-vwlb-future-adapters.php" r63-intelligence-exception
# R64 — intelligence suggestion external effects need the same durable reconciliation semantics as other processor operations.
need "class-vwlb-r64-intelligence-guard.php" "$P/video-wall-and-live-broadcasting.php" r64-autoload
need "VWLB_R64_Intelligence_Guard::register" "$P/video-wall-and-live-broadcasting.php" r64-register
need "intelligence/suggest" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-route
need "VWLB_R60_Final_Hardening::EXTERNAL_GUARD_PREFIX" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-shared-registry
need "vwlb_video_intelligence_processor_exception" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-uncertain
need "request_terminated_before_completion" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-shutdown
printf '%s\n' 'File 10 R61-R80 sequential contracts PASS'
