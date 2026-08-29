#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL third-fresh-20: $3" >&2; exit 1; }; }
forbid(){ ! grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL third-fresh-20: $3" >&2; exit 1; }; }

# R01 — one-time secret/grant responses are never durably replayed by generic REST idempotency.
need "vwlb_idempotency_nonreplayable" "$P/includes/class-vwlb-security.php" r01-nonreplayable
need "issue_credential','upload_start','download_token','download_resolve','watermark_grant" "$P/includes/class-vwlb-security.php" r01-secret-callbacks
need "nonreplayable'=>true" "$P/includes/class-vwlb-security.php" r01-redacted-storage

# R02 — live end/kill fails closed if recording finalization cannot be queued.
need "vwlb_recording_queue_failed" "$P/includes/class-vwlb-live.php" r02-recording-queue
need "\$queued=self::queue_recording" "$P/includes/class-vwlb-live.php" r02-queue-check
need "VWLB_DB::transaction(function()use(\$event,\$expected_version,\$changes,\$to" "$P/includes/class-vwlb-live.php" r02-end-atomicity

# R03 — public recorded-video DTOs expose channel public IDs only.
need "channel_public_id" "$P/includes/class-vwlb-repository.php" r03-channel-public
forbid "'channel_id'=>(int)\$video['channel_id']" "$P/includes/class-vwlb-repository.php" r03-no-channel-pk

# R04 — public live DTOs expose replay public IDs only.
need "replay_video_public_id" "$P/includes/class-vwlb-repository.php" r04-replay-public
forbid "'replay_video_id'=>(int)\$event['replay_video_id']" "$P/includes/class-vwlb-repository.php" r04-no-replay-pk

# R05 — mutation REST responses use minimized DTOs instead of raw rows.
need "video_mutation_dto" "$P/includes/class-vwlb-repository.php" r05-video-dto
need "asset_mutation_dto" "$P/includes/class-vwlb-repository.php" r05-asset-dto
need "live_mutation_dto" "$P/includes/class-vwlb-rest.php" r05-live-dto
need "takedown_mutation_dto" "$P/includes/class-vwlb-rest.php" r05-takedown-dto

# R06 — live-question moderation uses opaque public IDs.
need "/live-questions/(?P<id>[A-Za-z0-9_-]+)/moderate" "$P/includes/class-vwlb-extended-rest.php" r06-route-public
need "WHERE public_id=%s FOR UPDATE" "$P/includes/class-vwlb-extensions.php" r06-lookup-public
need "public_id'=>\$row['public_id']" "$P/includes/class-vwlb-extensions.php" r06-response-public

# R07 — other extended/Future mutation responses do not return internal row IDs.
need "attendee_public_id" "$P/includes/class-vwlb-extensions.php" r07-attendee-public
need "video_public_id'=>\$video['public_id']" "$P/includes/class-vwlb-future-intelligence.php" r07-transcript-public
forbid "SELECT id,public_id,question,status,answer,created_at" "$P/includes/class-vwlb-extensions.php" r07-question-no-pk

# R08 — resumable setup compensates orphaned initiated assets and hides asset PKs.
need "abandon_resumable_asset" "$P/includes/class-vwlb-extensions.php" r08-compensate
need "vwlb_upload_compensation_failed" "$P/includes/class-vwlb-extensions.php" r08-compensation-fail-closed
need "'session_id'=>\$public, 'asset_public_id'" "$P/includes/class-vwlb-extensions.php" r08-public-result

# R09 — expired private upload cleanup proves file and DB-state deletion.
need "expire_failed" "$P/includes/class-vwlb-extensions.php" r09-cleanup-failure
need "Expired private upload file could not be deleted." "$P/includes/class-vwlb-extensions.php" r09-unlink-check
need "array('id'=>\$s['id'],'status'=>\$s['status'])" "$P/includes/class-vwlb-extensions.php" r09-state-cas

# R10 — live resource attachments require explicit read + File10 safety authorization.
need "vwlb_live_resource_attachment_allowed" "$P/includes/class-vwlb-extensions.php" r10-resource-adapter
need "current_user_can('read_post',\$attachment)" "$P/includes/class-vwlb-extensions.php" r10-read-check

# R11 — rights-restricted live resources cannot enter published viewer state.
need "\$resource_status='restricted'===\$rights_status?'restricted':'published'" "$P/includes/class-vwlb-extensions.php" r11-rights-state
need "'status'=>\$resource_status" "$P/includes/class-vwlb-extensions.php" r11-persist-state

# R12 — public premiere lookup accepts only opaque public IDs.
need "WHERE public_id=%s LIMIT 1" "$P/includes/class-vwlb-extensions.php" r12-public-lookup
forbid "WHERE (id=%d OR public_id=%s) LIMIT 1" "$P/includes/class-vwlb-extensions.php" r12-no-numeric-lookup

# R13 — creator metric persistence failure is explicit/observable.
need "vwlb_metric_write_failed" "$P/includes/class-vwlb-extensions.php" r13-metric-failure
need "if(false===\$written)" "$P/includes/class-vwlb-extensions.php" r13-write-check

# R14 — dead-letter asset reconciliation failure returns the job to retry/reconciliation.
need "vwlb_asset_dead_letter_reconcile_failed" "$P/includes/class-vwlb-jobs.php" r14-reconcile-code
need "dead_letter_reconcile_failed" "$P/includes/class-vwlb-jobs.php" r14-reconcile-audit

# R15 — operational repair requires rollback snapshot and verifies Safe Mode persistence.
need "\$snapshot=VWLB_DB::snapshot('repair_before'" "$P/includes/class-vwlb-diagnostics.php" r15-snapshot-check
need "vwlb_repair_persist_failed" "$P/includes/class-vwlb-diagnostics.php" r15-option-persist

# R16 — required cron workers are scheduled and verified fail-closed.
need "vwlb_cron_schedule_failed" "$P/includes/class-vwlb-activator.php" r16-cron-fail
need "wp_schedule_event(time()+\$delay,\$recurrence,\$hook,array(),true)" "$P/includes/class-vwlb-activator.php" r16-wp-error-mode
need "\$scheduled=self::schedules()" "$P/includes/class-vwlb-activator.php" r16-activation-propagation

# R17 — legacy migration verifies rollback snapshot, each insert and completion marker.
need "vwlb_legacy_insert_failed" "$P/includes/class-vwlb-compatibility.php" r17-insert-check
need "vwlb_legacy_marker_failed" "$P/includes/class-vwlb-compatibility.php" r17-marker-check
need "VWLB_DB::transaction" "$P/includes/class-vwlb-compatibility.php" r17-transaction

# R18 — moderation/takedown public surfaces require opaque verified targets/report IDs.
need "resolve_public_target" "$P/includes/class-vwlb-moderation.php" r18-target-resolver
need "vwlb_public_target_required" "$P/includes/class-vwlb-moderation.php" r18-no-numeric-target
need "/moderation/reports/(?P<id>[A-Za-z0-9_-]+)/decision" "$P/includes/class-vwlb-rest.php" r18-public-report-route

# R19 — restore is provenance-bound to captured pre-restriction state.
need "safe_restore_status" "$P/includes/class-vwlb-moderation.php" r19-safe-restore
need "target_previous_status" "$P/includes/class-vwlb-moderation.php" r19-state-provenance
need "vwlb_restore_state_unproven" "$P/includes/class-vwlb-moderation.php" r19-restore-fail-closed

# R20 — privacy erasure removes historical idempotency linkage and does not re-link the subject ID in audit.
need "DELETE FROM \$idem_table WHERE scope LIKE %s" "$P/includes/class-vwlb-privacy.php" r20-idem-erasure
need "public_id('erase')" "$P/includes/class-vwlb-privacy.php" r20-anonymous-receipt
forbid "audit('privacy',\$uid,'erase'" "$P/includes/class-vwlb-privacy.php" r20-no-subject-relink
