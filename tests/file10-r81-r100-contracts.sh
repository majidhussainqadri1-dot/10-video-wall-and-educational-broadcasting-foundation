#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
LEGACY="$ROOT/video-wall/video-wall.php"
fail(){ echo "FAIL r81-r100: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R81 — repository canonical-owner boundary.
need "Plugin Name: Video Wall and Live Broadcasting" "$P/video-wall-and-live-broadcasting.php" r81-canonical-plugin
need "Canonical runtime: ../video-wall-and-live-broadcasting/" "$LEGACY" r81-archive-marker
reject "Plugin Name:" "$LEGACY" r81-no-legacy-plugin-header
reject "register_activation_hook" "$LEGACY" r81-no-legacy-activation
reject "add_action( 'plugins_loaded'" "$LEGACY" r81-no-legacy-runtime
need 'SOURCE="$ROOT/video-wall-and-live-broadcasting"' "$ROOT/tools/build-package.sh" r81-package-canonical-source

# R85 — canonical frontend deep links must not turn DB outages into false 404s.
need "vwlb_frontend_state_unreadable" "$P/includes/class-vwlb-plugin.php" r85-unavailable-error
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-plugin.php" r85-repository-failure
need "\$wpdb->last_error=''" "$P/includes/class-vwlb-plugin.php" r85-channel-db-reset
need "status_header(503)" "$P/includes/class-vwlb-plugin.php" r85-http-503
need "vwlb_route_unavailable" "$P/templates/route.php" r85-unavailable-template
need "Video service temporarily unavailable" "$P/templates/route.php" r85-user-state

printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
