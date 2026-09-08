#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-db.php"
SEC="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-security.php"
need(){ grep -F -- "$1" "$2" >/dev/null || { echo "FAIL R115: $3" >&2; exit 1; }; }
need "rollback_verified" "$DB" rollback-helper
need "vwlb_transaction_rollback_failed" "$DB" rollback-fail-closed
need "idempotency_begin'" "$SEC" idempotency-begin-read
need "idempotency_expiry_recheck" "$SEC" idempotency-expiry-read
need "idempotency_insert_race" "$SEC" idempotency-race-read
need "VWLB_DB::read_row" "$SEC" idempotency-verified-read
printf '%s\n' 'R115 durability contracts PASS'
