#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL second-fresh-20: $3" >&2; exit 1; }; }

# R01 — transaction boundaries and rollback snapshots fail closed.
need "vwlb_transaction_start_failed" "$P/includes/class-vwlb-db.php" r01-start
need "vwlb_transaction_commit_failed" "$P/includes/class-vwlb-db.php" r01-commit
need "vwlb_snapshot_persist_failed" "$P/includes/class-vwlb-db.php" r01-snapshot

# R02 — activation page setup proves rollback snapshot, persistence and compensation.
need 'is_wp_error( $snapshot )' "$P/includes/class-vwlb-activator.php" r02-snapshot-propagation
need "vwlb_activation_compensation_failed" "$P/includes/class-vwlb-activator.php" r02-compensation
need "vwlb_page_map_persist_failed" "$P/includes/class-vwlb-activator.php" r02-page-map

# R03 — migration lock takeover and release are owner-token/compare-and-delete bound.
need "delete_migration_lock_if_matches" "$P/includes/class-vwlb-activator.php" r03-lock-helper
need "option_name=%s AND option_value=%s" "$P/includes/class-vwlb-activator.php" r03-lock-cas
need 'self::delete_migration_lock_if_matches( $token )' "$P/includes/class-vwlb-activator.php" r03-owner-release

# R04 — source/scene edits require the caller's current optimistic version.
need "Production source changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-source-client-version
need "Scene changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-scene-client-version
need 'version'=>\$expected_version "$P/includes/class-vwlb-future-intelligence.php" r04-cas-version

# R05 — simulcast target edits require the caller current version.
need "Simulcast target changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r05-target-client-version
need "expected_version=absint" "$P/includes/class-vwlb-future-intelligence.php" r05-target-version-parse
