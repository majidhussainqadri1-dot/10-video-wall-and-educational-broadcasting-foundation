#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL sequential-late: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R11 — restoration is case-bound across moderation/takedown and consent restrictions.
need "vwlb_restore_blocked_by_consent" "$P/includes/class-vwlb-r11-restore-guard.php" r11-consent-blocker
need "vwlb_restore_blocked_by_moderation" "$P/includes/class-vwlb-r11-restore-guard.php" r11-moderation-blocker
need "vwlb_restore_blocked_by_takedown" "$P/includes/class-vwlb-r11-restore-guard.php" r11-takedown-blocker
need "target_previous_status" "$P/includes/class-vwlb-r11-restore-guard.php" r11-provenance
need "class-vwlb-r11-restore-guard.php" "$P/video-wall-and-live-broadcasting.php" r11-autoload
need "VWLB_R11_Restore_Guard::register" "$P/video-wall-and-live-broadcasting.php" r11-register
# R13 — cross-file extension filters cannot widen the final public event DTO.
need "private function public_event_payload" "$P/includes/class-vwlb-integrations.php" r13-event-allowlist-helper
count=$(grep -F -c '$safe=$this->public_event_payload(' "$P/includes/class-vwlb-integrations.php" || true)
[[ "$count" -ge 2 ]] || fail r13-resanitize-after-filter
need "public-safe-event-projection" "$P/includes/class-vwlb-integrations.php" r13-public-projection
# R14 — every Future UI mutation carries the required idempotency key.
need "const idempotencyKey" "$P/assets/js/vwlb-future.js" r14-idem-generator
need "headers['Idempotency-Key']" "$P/assets/js/vwlb-future.js" r14-idem-header
need "!['GET','HEAD','OPTIONS'].includes(method)" "$P/assets/js/vwlb-future.js" r14-idem-mutations
printf '%s\n' 'File 10 sequential late-round contracts PASS'