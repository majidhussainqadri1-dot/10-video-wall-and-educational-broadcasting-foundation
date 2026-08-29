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

# R53 — activation scheduling is compensating: a partial cron setup or any later activation failure must not leave orphan workers running.
need "\$created=array();" "$P/includes/class-vwlb-activator.php" r53-created-ledger
need "array_reverse(\$created)" "$P/includes/class-vwlb-activator.php" r53-reverse-compensation
need "wp_clear_scheduled_hook(\$hook)" "$P/includes/class-vwlb-activator.php" r53-clear-created-hook
need "if(is_wp_error(\$scheduled)){self::deactivate();" "$P/includes/class-vwlb-activator.php" r53-schedule-failure-cleanup
need "if(is_wp_error(\$legacy)){self::deactivate();" "$P/includes/class-vwlb-activator.php" r53-legacy-failure-cleanup
need "File 10 version state could not be recorded durably." "$P/includes/class-vwlb-activator.php" r53-version-failure-path
need "self::deactivate();deactivate_plugins" "$P/includes/class-vwlb-activator.php" r53-post-schedule-deactivation

# R54 — worker queue scans must distinguish an empty queue from an unreadable database state and emit an operational failure signal.
need "vwlb_processing_queue_read_failed" "$P/includes/class-vwlb-jobs.php" r54-processing-read
need "vwlb_outbox_queue_read_failed" "$P/includes/class-vwlb-jobs.php" r54-outbox-read
need "vwlb_scheduled_publish_queue_read_failed" "$P/includes/class-vwlb-jobs.php" r54-scheduled-read
need "vwlb_live_reconcile_queue_read_failed" "$P/includes/class-vwlb-jobs.php" r54-live-read
need "\$wpdb->last_error=''" "$P/includes/class-vwlb-jobs.php" r54-error-reset

# R55 — direct/object-level authorization must fail closed for soft-deleted rows, not only list queries.
need "!empty(\$object['deleted_at'])" "$P/includes/class-vwlb-security.php" r55-soft-delete-boundary
need "public_video_dto" "$P/includes/class-vwlb-repository.php" r55-video-dto-path
need "public_live_dto" "$P/includes/class-vwlb-repository.php" r55-live-dto-path

printf '%s\n' 'File 10 R51-R60 sequential contracts PASS'
