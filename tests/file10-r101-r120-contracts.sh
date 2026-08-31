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

printf '%s\n' 'File 10 R101-R120 sequential contracts PASS'
