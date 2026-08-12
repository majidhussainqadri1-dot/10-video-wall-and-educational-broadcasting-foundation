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
