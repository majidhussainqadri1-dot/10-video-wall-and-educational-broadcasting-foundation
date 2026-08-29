#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL: $*" >&2; exit 1; }
[[ -f "$PLUGIN/video-wall-and-live-broadcasting.php" ]] || fail "main plugin missing"
grep -F "Version: 1.2.9-rc1" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail version
grep -F "define( 'VWLB_VERSION', '1.2.9-rc1' );" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail constant
grep -F "define( 'VWLB_FUTURE_SCHEMA_VERSION', '1.2.0' );" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail future-schema
grep -F "Text Domain: video-wall-live-broadcasting" "$PLUGIN/video-wall-and-live-broadcasting.php" >/dev/null || fail text-domain
for id in $(seq -w 1 19); do grep -F "F10-FR-0${id}" "$ROOT/docs/REQUIREMENTS-TRACEABILITY.md" >/dev/null || fail "missing FR $id"; done
for id in $(seq -w 1 10); do grep -F "F10-NFR-0${id}" "$ROOT/docs/REQUIREMENTS-TRACEABILITY.md" >/dev/null || fail "missing NFR $id"; done
for id in $(seq -w 1 24); do grep -R -F "F10-FUT-0${id}" "$PLUGIN/includes" >/dev/null || fail "missing FUT $id"; done
for token in "class VWLB_Media" "class VWLB_Videos" "class VWLB_Live" "class VWLB_Moderation" "interface VWLB_Provider_Interface" "class VWLB_Diagnostics" "class VWLB_Privacy" "class VWLB_Extensions" "class VWLB_Podcasts" "class VWLB_Future_Intelligence" "class VWLB_Future_Safety" "class VWLB_Future_Adapters" "class VWLB_Future_REST" "class VWLB_Future_Frontend" "class VWLB_R60_Final_Hardening"; do grep -R "$token" "$PLUGIN/includes" >/dev/null || fail "missing $token"; done
grep -F "permission_callback'=>\$map" "$PLUGIN/includes/class-vwlb-rest.php" >/dev/null || fail permission-map
grep -F "permission_callback'=>\$map" "$PLUGIN/includes/class-vwlb-extended-rest.php" >/dev/null || fail extended-permission-map
grep -F "permission_callback'=>\$map" "$PLUGIN/includes/class-vwlb-future-rest.php" >/dev/null || fail future-permission-map
grep -F "Cache-Control: private, no-store" "$PLUGIN/includes/class-vwlb-helpers.php" >/dev/null || fail private-cache
grep -F "File 11" "$PLUGIN/includes/class-vwlb-integrations.php" >/dev/null || fail reels-contract
bash "$ROOT/tests/plan-completion-contracts.sh"
echo "static contracts PASS"
