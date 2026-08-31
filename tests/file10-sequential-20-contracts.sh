#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL sequential-20: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R03 — resumable/private media ingest must fail closed when server-side signature detection is unavailable/unknown.
need "vwlb_file_signature_unavailable" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-finfo-unavailable
need "vwlb_file_signature_unknown" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-finfo-unknown
need "detected_mime_allowed" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-detected-mime-allowlist
need "enforce_private_signature_validation" "$P/includes/class-vwlb-sequential-review-hardening.php" r03-worker-fail-closed
need "class-vwlb-sequential-review-hardening.php" "$P/video-wall-and-live-broadcasting.php" r03-autoload
# R05 — emergency end commits local safety state/credential revocation/recording queue before irreversible provider termination.
need "vwlb_provider_emergency_end_result" "$P/includes/class-vwlb-sequential-review-hardening.php" r05-provider-confirmation
need "vwlb_provider_emergency_end_reconcile_required" "$P/includes/class-vwlb-sequential-review-hardening.php" r05-provider-reconcile
need "local_state'=>'ended'" "$P/includes/class-vwlb-sequential-review-hardening.php" r05-local-ended-durable
need "processing_jobs" "$P/includes/class-vwlb-sequential-review-hardening.php" r05-recording-durable
# R06 — remote/custom URLs must pass DNS/private-address aware WordPress validation.
need "vwlb_remote_url_allowed" "$P/includes/class-vwlb-sequential-review-hardening.php" r06-terminal-url-filter
need "wp_http_validate_url" "$P/includes/class-vwlb-sequential-review-hardening.php" r06-dns-private-validation
need "PHP_INT_MAX" "$P/includes/class-vwlb-sequential-review-hardening.php" r06-terminal-priority
# R07 — command-level idempotency state is verified before affected REST mutations can return success.
need "enforce_command_idempotency_after" "$P/includes/class-vwlb-sequential-review-hardening.php" r07-after-callback
need "create_video' === \$name" "$P/includes/class-vwlb-sequential-review-hardening.php" r07-video-scope
need "schedule_live','premiere_create" "$P/includes/class-vwlb-sequential-review-hardening.php" r07-live-scope
need "vwlb_idempotency_persist_failed" "$P/includes/class-vwlb-sequential-review-hardening.php" r07-fail-closed
# R09 — caption delivery honors stored format and correction publication is deferred until publish transition.
need "application/x-subrip; charset=UTF-8" "$P/includes/class-vwlb-sequential-review-hardening.php" r09-srt-content-type
need "application/ttml+xml; charset=UTF-8" "$P/includes/class-vwlb-sequential-review-hardening.php" r09-ttml-content-type
need "X-VWLB-Caption-Format" "$P/includes/class-vwlb-sequential-review-hardening.php" r09-caption-format-header
need "publication_event_deferred" "$P/includes/class-vwlb-sequential-review-hardening.php" r09-correction-deferred
! sed -n '/public static function create_annotation/,/private static function annotation_dto/p' "$P/includes/class-vwlb-sequential-review-hardening.php" | grep -F "VideoTimestampCorrectionPublished" >/dev/null || fail r09-no-premature-published-event
need "'published'===\$to" "$P/includes/class-vwlb-future-intelligence.php" r09-publish-transition
need "VideoTimestampCorrectionPublished" "$P/includes/class-vwlb-future-intelligence.php" r09-publish-event
# R10 — migration completion proves columns/indexes and resumable storage revalidates protection/symlink safety.
need "class-vwlb-r10-integrity.php" "$P/video-wall-and-live-broadcasting.php" r10-autoload
need "VWLB_R10_Integrity::register" "$P/video-wall-and-live-broadcasting.php" r10-register
need "r10_contract_from_create_sql" "$P/includes/class-vwlb-r10-integrity.php" r10-base-contract-parser
need "vwlb_schema_column_missing" "$P/includes/class-vwlb-r10-integrity.php" r10-column-proof
need "vwlb_schema_index_mismatch" "$P/includes/class-vwlb-r10-integrity.php" r10-index-proof
need "segment_search" "$P/includes/class-vwlb-r10-integrity.php" r10-fulltext-proof
need "vwlb_private_storage_symlink_forbidden" "$P/includes/class-vwlb-r10-integrity.php" r10-dir-symlink
need "vwlb_private_storage_protection_symlink_forbidden" "$P/includes/class-vwlb-r10-integrity.php" r10-protection-symlink
need "guard_private_storage_before" "$P/includes/class-vwlb-r10-integrity.php" r10-runtime-storage-recheck
need "rest_pre_dispatch" "$P/includes/class-vwlb-r10-integrity.php" r10-fail-closed-rest
printf '%s\n' 'File 10 current sequential-20 regression contracts PASS'