#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL sequential-20: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R03 — resumable/private media ingest must fail closed when server-side signature detection is unavailable/unknown.
need "vwlb_file_signature_unavailable" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-finfo-unavailable
need "vwlb_file_signature_unknown" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-finfo-unknown
need "detected_mime_allowed" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-detected-mime-allowlist
need "enforce_private_signature_validation" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-worker-fail-closed
need "class-vwlb-sequential-review-hardening.php" "$P/video-wall-and-live-broadcasting.php" r03-autoload
printf '%s\n' 'File 10 current sequential-20 regression contracts PASS'
