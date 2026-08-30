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

printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
