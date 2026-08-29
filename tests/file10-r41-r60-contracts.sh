#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r41-r60: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
forbid(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R41 — legacy encrypted evidence migration must scan the complete bounded namespace, not only the first 500 options.
need "MIGRATION_PAGE_SIZE = 250" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-page-size
need "MIGRATION_MAX_PAGES = 200" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-hard-bound
need "option_id>%d" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-cursor
need "verify_no_legacy" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-complete-proof
need "vwlb_evidence_legacy_migration_limit" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-limit-fail-closed
forbid 'self::options($prefix,500)' "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-no-first-page-proof

printf '%s\n' 'File 10 R41-R60 sequential contracts PASS'
