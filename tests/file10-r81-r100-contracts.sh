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

# R86 — privacy anonymization must preserve unique-key validity across multiple erased users.
need "anonymized_subject_id" "$P/includes/class-vwlb-privacy.php" r86-surrogate-helper
need "array('live_attendees','user_id',array('user_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-live-surrogate
need "array('creator_metrics_daily','owner_id',array('owner_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-metrics-surrogate
reject "array('live_attendees','user_id',array('user_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-live-zero
reject "array('creator_metrics_daily','owner_id',array('owner_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-metrics-zero
need "UNIQUE KEY event_user (live_event_id,user_id)" "$P/includes/class-vwlb-extensions.php" r86-live-unique-contract
need "UNIQUE KEY metric_object (metric_date,owner_id,object_type,object_id)" "$P/includes/class-vwlb-extensions.php" r86-metrics-unique-contract

printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
