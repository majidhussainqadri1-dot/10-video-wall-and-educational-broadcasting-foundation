#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r60: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R60 — final full review findings were frozen before this correction batch.
need "vwlb_evidence_option_read_failed" "$P/includes/class-vwlb-r30-evidence-privacy.php" evidence-db-read
need "MIGRATION_MARKER = 'complete:r60-db-verified-v1'" "$P/includes/class-vwlb-r30-evidence-privacy.php" evidence-old-marker-reverify
need "vwlb_legacy_probe_failed" "$P/includes/class-vwlb-compatibility.php" legacy-probe
need "vwlb_legacy_dedupe_read_failed" "$P/includes/class-vwlb-compatibility.php" legacy-dedupe
need "vwlb_video_browse_read_failed" "$P/includes/class-vwlb-repository.php" browse-db-read
need "vwlb_video_wall_live_read_failed" "$P/includes/class-vwlb-r34-frontend-contract.php" wall-live-db-read
need "class-vwlb-r60-final-hardening.php" "$P/video-wall-and-live-broadcasting.php" r60-autoload
need "VWLB_R60_Final_Hardening::register" "$P/video-wall-and-live-broadcasting.php" r60-register
need "activation_begin" "$P/includes/class-vwlb-r60-final-hardening.php" activation-begin
need "activation_shutdown" "$P/includes/class-vwlb-r60-final-hardening.php" activation-shutdown
need "rollback_activation_snapshot" "$P/includes/class-vwlb-r60-final-hardening.php" activation-rollback
need "EXTERNAL_GUARD_PREFIX = 'vwlb_r60_external_guard_'" "$P/includes/class-vwlb-r60-final-hardening.php" external-guard
need "request_terminated_before_completion" "$P/includes/class-vwlb-r60-final-hardening.php" external-shutdown-reconcile
need "remove_filter('rest_request_after_callbacks',array('VWLB_Sequential_Review_Hardening','enforce_command_idempotency_after'),9)" "$P/includes/class-vwlb-r60-final-hardening.php" replace-r07-idempotency
need "vwlb_external_reconcile_required" "$P/includes/class-vwlb-r60-final-hardening.php" unsafe-retry-block
need "resolve_external_guard" "$P/includes/class-vwlb-r60-final-hardening.php" operator-reconcile-release
need "safe_recount" "$P/includes/class-vwlb-r60-final-hardening.php" guarded-recount
need "vwlb_repair_postcheck_unverified" "$P/includes/class-vwlb-r60-final-hardening.php" repair-postcheck
need "vwlb_r60_activation_snapshot" "$P/uninstall.php" purge-activation-snapshot
need "vwlb_r60_external_guard_" "$P/uninstall.php" purge-external-guards
need "Version: 1.2.9-rc1" "$P/video-wall-and-live-broadcasting.php" final-version
need "define( 'VWLB_VERSION', '1.2.9-rc1' );" "$P/video-wall-and-live-broadcasting.php" final-version-constant
printf '%s\n' 'File 10 R60 final contracts PASS'
