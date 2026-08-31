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

printf '%s\n' 'File 10 R101-R120 sequential contracts PASS'
