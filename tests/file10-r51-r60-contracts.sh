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

# R56 — restoration safety is a command invariant, not merely a REST preflight; consent/moderation/takedown blocker reads are fresh-error-aware.
need "public static function assert_restore_allowed" "$P/includes/class-vwlb-r11-restore-guard.php" r56-public-invariant
need "assert_restore_allowed(\$fresh['target_type'],(int)\$fresh['target_id'],'moderation'" "$P/includes/class-vwlb-moderation.php" r56-moderation-command-guard
need "assert_restore_allowed(\$fresh['target_type'],(int)\$fresh['target_id'],'takedown'" "$P/includes/class-vwlb-moderation.php" r56-takedown-command-guard
need "\$wpdb->last_error = '';" "$P/includes/class-vwlb-r11-restore-guard.php" r56-fresh-db-error
need "vwlb_restore_blocked_by_consent" "$P/includes/class-vwlb-r11-restore-guard.php" r56-consent-block
need "vwlb_restore_blocked_by_moderation" "$P/includes/class-vwlb-r11-restore-guard.php" r56-moderation-block
need "vwlb_restore_blocked_by_takedown" "$P/includes/class-vwlb-r11-restore-guard.php" r56-takedown-block

# R57 — external provider live creation must be compensated before its idempotency lock is released; unconfirmed compensation is operator-reconciled and retry-blocked.
need "compensate_live_creation" "$P/includes/class-vwlb-live.php" r57-compensation-helper
need "vwlb_provider_live_compensation_requested" "$P/includes/class-vwlb-live.php" r57-compensation-hook
need "vwlb_provider_delete_live_result" "$P/includes/class-vwlb-live.php" r57-confirmation-filter
need "vwlb_provider_live_reconcile_required" "$P/includes/class-vwlb-live.php" r57-reconcile-signal
need "if(\$compensated){VWLB_Security::idempotency_abort" "$P/includes/class-vwlb-live.php" r57-release-only-after-confirmed-compensation
need "Reconciliation is required before this idempotency key can be retried." "$P/includes/class-vwlb-live.php" r57-retry-block-message

# R58 — durable stream-credential compensation is enforced by the command itself, and transition-to-live distinguishes DB read failure from a genuinely missing active credential.
need "public static function issue_durable" "$P/includes/class-vwlb-r46-stream-credential-durability.php" r58-public-durable-issuer
need "VWLB_R46_Stream_Credential_Durability::issue_durable" "$P/includes/class-vwlb-live.php" r58-command-delegation
need "vwlb_stream_credential_read_failed" "$P/includes/class-vwlb-live.php" r58-read-failure
need "if(is_wp_error(\$active))return \$active" "$P/includes/class-vwlb-live.php" r58-read-error-propagation
need "\$wpdb->last_error=''" "$P/includes/class-vwlb-live.php" r58-fresh-credential-read

# R59 — materially changed deployable code must receive a fresh immutable identity, and every current release surface must agree on it.
need "Version: 1.2.8-rc1" "$P/video-wall-and-live-broadcasting.php" r59-plugin-header
need "define( 'VWLB_VERSION', '1.2.8-rc1' );" "$P/video-wall-and-live-broadcasting.php" r59-runtime-constant
need "Stable tag: 1.2.8-rc1" "$P/readme.txt" r59-readme
need "video-wall-and-live-broadcasting-1.2.8-rc1.zip" "$ROOT/.github/workflows/file10-release.yml" r59-workflow-package
need "file10-video-wall-live-1.2.8-rc1" "$ROOT/.github/workflows/file10-release.yml" r59-workflow-artifact
need "video-wall-and-live-broadcasting-1.2.8-rc1.zip" "$ROOT/tools/build-package.sh" r59-builder
need "CURRENT_VERSION='1.2.8-rc1'" "$ROOT/tests/run-all.sh" r59-suite-version
need "run_rebased_127" "$ROOT/tests/run-all.sh" r59-historical-version-rebase
need "# File 10 Release Candidate Manifest — 1.2.8-rc1" "$ROOT/MANIFEST.md" r59-manifest
need "round `R59` completed" "$ROOT/MANIFEST.md" r59-manifest-boundary
need "# File 10 Status — 1.2.8-rc1" "$ROOT/STATUS.md" r59-status
need "R60 remains pending" "$ROOT/STATUS.md" r59-no-premature-r60
need "Runtime: `1.2.8-rc1`" "$ROOT/README.md" r59-readme-root
need '"version": "1.2.8-rc1"' "$ROOT/SBOM-1.2.8-rc1.json" r59-sbom

printf '%s\n' 'File 10 R51-R60 sequential contracts PASS'
