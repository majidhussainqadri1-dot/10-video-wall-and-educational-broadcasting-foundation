#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
LEGACY="$ROOT/video-wall/video-wall.php"
fail(){ echo "FAIL r81-r100: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R81 — repository canonical-owner boundary: the historical `video-wall`
# source may remain as archive material, but it must not be an executable
# WordPress plugin or register a second runtime/source of truth.
need "Plugin Name: Video Wall and Live Broadcasting" "$P/video-wall-and-live-broadcasting.php" r81-canonical-plugin
need "Canonical runtime: ../video-wall-and-live-broadcasting/" "$LEGACY" r81-archive-marker
reject "Plugin Name:" "$LEGACY" r81-no-legacy-plugin-header
reject "register_activation_hook" "$LEGACY" r81-no-legacy-activation
reject "add_action( 'plugins_loaded'" "$LEGACY" r81-no-legacy-runtime
need 'SOURCE="$ROOT/video-wall-and-live-broadcasting"' "$ROOT/tools/build-package.sh" r81-package-canonical-source

# R84 — watermark grants use the already verified policy snapshot. A second
# unverified policy query must not be able to silently downgrade forensic mode.
need "SELECT mode,status,version" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r84-policy-version
need "private static function grant_payload" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r84-snapshot-grant
need "self::grant_payload(\$policy,\$type,\$object,\$session_ref)" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r84-no-second-read
reject "VWLB_Future_Intelligence::watermark_payload(array('mode'=>'off')" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r84-no-fail-open-requery

# R85 — recording finalization must keep the canonical live lifecycle, job
# lease, consent proof and policy snapshot in one fail-closed state machine.
need "'ended'=>array('recording_processing'" "$P/includes/class-vwlb-state-machine.php" r85-ended-processing
need "'recording_processing'=>array('replay_review','failed')" "$P/includes/class-vwlb-state-machine.php" r85-processing-review
need "'recording_policy_snapshot'=>\$policy" "$P/includes/class-vwlb-live.php" r85-policy-snapshot
need "array('status'=>'recording_processing')" "$P/includes/class-vwlb-live.php" r85-queue-state
need "VWLB_R73_Recording_Consent_Guard::finalization_proof" "$P/includes/class-vwlb-jobs.php" r85-post-finalizer-consent
need "array('status'=>'replay_review')" "$P/includes/class-vwlb-jobs.php" r85-success-review
need "array('status'=>'failed')" "$P/includes/class-vwlb-jobs.php" r85-terminal-failed
need "public static function finalization_proof" "$P/includes/class-vwlb-r73-recording-consent-guard.php" r85-consent-invariant
reject "add_filter('vwlb_finalize_live_recording'" "$P/includes/class-vwlb-r73-recording-consent-guard.php" r85-no-overwritable-early-filter
need "vwlb_recording_live_missing" "$P/includes/class-vwlb-r73-recording-consent-guard.php" r85-missing-live-failclosed

printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
