#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL: $*" >&2; exit 1; }
[[ -f "$PLUGIN/video-wall-and-live-broadcasting.php" ]] || fail "main plugin missing"
grep -F "Version: 1.0.0-rc1" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail "version header"
grep -F "define( 'VWLB_VERSION', '1.0.0-rc1' );" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail "version constant"
grep -F "Text Domain: video-wall-live-broadcasting" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail "text domain"
for id in $(seq -w 1 19); do grep -R "F10-FR-0${id}" "$ROOT/docs/REQUIREMENTS-TRACEABILITY.md" >/dev/null || fail "missing FR $id"; done
for id in $(seq -w 1 10); do grep -R "F10-NFR-0${id}" "$ROOT/docs/REQUIREMENTS-TRACEABILITY.md" >/dev/null || fail "missing NFR $id"; done
for token in "class VWLB_Media" "class VWLB_Videos" "class VWLB_Live" "class VWLB_Moderation" "interface VWLB_Provider_Interface" "class VWLB_Diagnostics" "class VWLB_Privacy"; do grep -R "$token" "$PLUGIN/includes" >/dev/null || fail "missing $token"; done
for table in channels playlists media_assets processing_jobs videos captions live_events stream_credentials playback_sessions moderation takedowns audit outbox inbox webhooks idempotency rate_limits rollback_snapshots; do grep -F "}${table} (" "$PLUGIN/includes/class-vwlb-db.php" >/dev/null || fail "missing table $table"; done
grep -F "Idempotency-Key" "$PLUGIN/includes/class-vwlb-rest.php" >/dev/null || fail "idempotency header absent"
grep -F "require_step_up" "$PLUGIN/includes/class-vwlb-live.php" >/dev/null || fail "step-up absent"
grep -F "password_hash" "$PLUGIN/includes/class-vwlb-providers.php" >/dev/null || fail "secret hashing absent"
! grep -R -E "(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{30,}|BEGIN (RSA|OPENSSH) PRIVATE KEY|stream[_ -]?key[[:space:]]*=[[:space:]]*['\"][^'\"]+)" "$PLUGIN" "$ROOT/docs" >/dev/null || fail "possible secret in source"
grep -F "permission_callback'=>\$map" "$PLUGIN/includes/class-vwlb-rest.php" >/dev/null || fail "closed server permission map absent"
! grep -F "_vwlb_permission" "$PLUGIN/includes/class-vwlb-rest.php" >/dev/null || fail "request-controlled permission marker present"
grep -F "Cache-Control: private, no-store" "$PLUGIN/includes/class-vwlb-helpers.php" >/dev/null || fail "private cache control absent"
grep -F "prefers-reduced-motion" "$PLUGIN/assets/css/vwlb.css" >/dev/null || fail "reduced motion absent"
grep -F "[dir=\"rtl\"]" "$PLUGIN/assets/css/vwlb.css" >/dev/null || fail "RTL absent"
grep -F "File 11" "$PLUGIN/includes/class-vwlb-integrations.php" >/dev/null || fail "Reels contract absent"
echo "static contracts PASS"
