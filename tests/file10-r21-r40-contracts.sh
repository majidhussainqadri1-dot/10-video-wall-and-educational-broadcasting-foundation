#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r21-r40: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R21
need "true !== VWLB_Plugin::instance()->run()" "$P/video-wall-and-live-broadcasting.php" r21-core-boot-gate
need "return false;" "$P/includes/class-vwlb-plugin.php" r21-failure-result
need "return true;" "$P/includes/class-vwlb-plugin.php" r21-success-result
# R22
need "unset(\$r['id'],\$r['channel_id'],\$r['owner_id'],\$r['thumbnail_id'])" "$P/includes/class-vwlb-repository.php" r22-strip-internal-ids
need "\$r['thumbnail_url']" "$P/includes/class-vwlb-repository.php" r22-public-thumbnail-url
# R25
need "R25: state transition and processing-queue persistence are one transaction" "$P/includes/class-vwlb-media.php" r25-atomic-intent
need "VWLB_DB::transaction(function()use(\$asset" "$P/includes/class-vwlb-media.php" r25-transaction
need "Media completion was rolled back because processing could not be queued" "$P/includes/class-vwlb-media.php" r25-queue-rollback
# R27
need "vwlb_provider_missing" "$P/includes/class-vwlb-live.php" r27-provider-missing
need "The configured live provider is unavailable." "$P/includes/class-vwlb-live.php" r27-provider-message
need "if(!\$provider)return VWLB_Helpers::error('vwlb_provider_missing'" "$P/includes/class-vwlb-live.php" r27-null-guard
# R28
need "moderation_blocker_exists" "$P/includes/class-vwlb-r11-restore-guard.php" r28-moderation-pagination
need "takedown_blocker_exists" "$P/includes/class-vwlb-r11-restore-guard.php" r28-takedown-pagination
need "id<%d" "$P/includes/class-vwlb-r11-restore-guard.php" r28-cursor
need "count(\$rows) < 100" "$P/includes/class-vwlb-r11-restore-guard.php" r28-bounded-pages
# R29
need "vwlb_retry_erasure_unverifiable" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-unverifiable-signal
need "privacy erasure was stopped and remains incomplete" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-fail-closed-message
need "'done'=>false" "$P/includes/class-vwlb-r20-retry-privacy.php" r29-incomplete
# R30
need "encrypt_evidence_fallback" "$P/includes/class-vwlb-helpers.php" r30-encrypt-helper
need "aes-256-gcm" "$P/includes/class-vwlb-helpers.php" r30-aead
need "class-vwlb-r30-evidence-privacy.php" "$P/video-wall-and-live-broadcasting.php" r30-autoload
need "VWLB_R30_Evidence_Privacy::register" "$P/video-wall-and-live-broadcasting.php" r30-register
need "remove_action('vwlb_reconcile_states',array('VWLB_Review_Hardening','reconcile_fallbacks'),60)" "$P/includes/class-vwlb-r30-evidence-privacy.php" r30-old-reconciler-disabled
need "migrate_legacy" "$P/includes/class-vwlb-r30-evidence-privacy.php" r30-legacy-migration
# R31
need "class-vwlb-r31-webhook-integrity.php" "$P/video-wall-and-live-broadcasting.php" r31-autoload
need "VWLB_R31_Webhook_Integrity::register" "$P/video-wall-and-live-broadcasting.php" r31-register
need "vwlb_webhook_event_conflict" "$P/includes/class-vwlb-r31-webhook-integrity.php" r31-conflict
need "hash_equals((string)\$existing['payload_hash'],\$payload_hash)" "$P/includes/class-vwlb-r31-webhook-integrity.php" r31-payload-bound
need "(string)\$existing['event_type']!==\$event_type" "$P/includes/class-vwlb-r31-webhook-integrity.php" r31-type-bound
# R34
need "class-vwlb-r34-frontend-contract.php" "$P/video-wall-and-live-broadcasting.php" r34-autoload
need "VWLB_R34_Frontend_Contract::register" "$P/video-wall-and-live-broadcasting.php" r34-register
need "thumbnail_url" "$P/includes/class-vwlb-r34-frontend-contract.php" r34-public-thumbnail
! grep -F "item['thumbnail_id']" "$P/includes/class-vwlb-r34-frontend-contract.php" >/dev/null || fail r34-no-internal-thumbnail
# R35
need "vwlb_provider_health_read_failed" "$P/includes/class-vwlb-observability.php" r35-read-failure-signal
need "if(''!==(string)\$wpdb->last_error)" "$P/includes/class-vwlb-observability.php" r35-query-error-check
# R36
need "scheduled_publish" "$P/includes/class-vwlb-jobs.php" r36-audit-action
need "Scheduled publication gate revalidated at execution time" "$P/includes/class-vwlb-jobs.php" r36-gate-revalidation
need "VWLB_DB::transaction(function()use(\$candidate,\$now)" "$P/includes/class-vwlb-jobs.php" r36-atomic-transaction
need "'public_id'=>\$current['public_id'],'scheduled'=>true" "$P/includes/class-vwlb-jobs.php" r36-public-event
# R37
need "database_verified" "$P/includes/class-vwlb-diagnostics.php" r37-database-verification
need "database_errors" "$P/includes/class-vwlb-diagnostics.php" r37-error-ledger
need "vwlb_repair_preflight_unverified" "$P/includes/class-vwlb-diagnostics.php" r37-repair-block
need "Repair was blocked because the database preflight could not be verified safely" "$P/includes/class-vwlb-diagnostics.php" r37-fail-closed-message
need "snapshot('repair_before',\$preflight)" "$P/includes/class-vwlb-diagnostics.php" r37-verified-snapshot
printf '%s\n' 'File 10 R21-R40 sequential contracts PASS'
