#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r51-r60: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
forbid(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R51 — verified webhook persistence, dispatch and retry are one durable state machine; retry payload is encrypted at rest.
need "PROCESSING_STALE_SECONDS = 300" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-stale-lease
need "PAYLOAD_KIND = 'webhook'" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-encrypted-kind
need "encrypt_evidence_fallback(self::PAYLOAD_KIND" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-encrypt-payload
need "decrypt_evidence_fallback(\$envelope,self::PAYLOAD_KIND)" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-decrypt-retry
need "status='processing',attempts=attempts+1" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-claim
need "vwlb_webhook_finalize_failed" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-finalize-fail
need "stale_processing_recovered" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-stale-recovery
need "status IN ('received','failed')" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-reconcile-queue
need "vwlb_webhook_event_conflict" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-content-collision
forbid "'payload_json'=>VWLB_Helpers::json_encode(\$data)" "$P/includes/class-vwlb-r31-webhook-integrity.php" r51-no-plaintext-retry-payload

# R52 — secure inbox retry reconciliation/cleanup must be cursor-fair, DB-error-aware, and purge its cursor state.
need "RECONCILE_CURSOR_OPTION = 'vwlb_retry_reconcile_cursor'" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-reconcile-cursor
need "CLEANUP_CURSOR_OPTION = 'vwlb_retry_cleanup_cursor'" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-cleanup-cursor
need "RECONCILE_PAGE_SIZE = 25" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-reconcile-page
need "persist_cursor" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-cursor-persist
need "vwlb_retry_option_read_failed" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-read-fail
need "vwlb_inbox_retry_state_unreadable" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-inbox-read-fail
need "count(\$options)<self::RECONCILE_PAGE_SIZE?0:\$last" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-fair-advance
need "vwlb_retry_reconcile_cursor" "$P/uninstall.php" r52-purge-reconcile-cursor
need "vwlb_retry_cleanup_cursor" "$P/uninstall.php" r52-purge-cleanup-cursor
need "vwlb_retry_erasure_cursor_" "$P/uninstall.php" r52-purge-erasure-cursors
forbid "foreach(self::records(0,25)" "$P/includes/class-vwlb-r20-retry-privacy.php" r52-no-head-of-line-loop

printf '%s\n' 'File 10 R51-R60 sequential contracts PASS'
