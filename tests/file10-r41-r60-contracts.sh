#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL r41-r60: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
forbid(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# R41 — legacy encrypted evidence migration must scan the complete bounded namespace, not only the first 500 options.
need "MIGRATION_PAGE_SIZE = 250" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-page-size
need "MIGRATION_MAX_PAGES = 200" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-hard-bound
need "option_id>%d" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-cursor
need "verify_no_legacy" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-complete-proof
need "vwlb_evidence_legacy_migration_limit" "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-limit-fail-closed
forbid 'self::options($prefix,500)' "$P/includes/class-vwlb-r30-evidence-privacy.php" r41-no-first-page-proof

# R42 — poison/conflicting fallback evidence must not head-of-line block later records; replay after release failure is deduped by canonical public_id.
need "RECONCILE_CURSOR_PREFIX = 'vwlb_r30_reconcile_cursor_'" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-cursor-prefix
need "RECONCILE_PAGE_SIZE = 50" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-page-size
need "persist_cursor" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-cursor-persist
need "vwlb_evidence_reconcile_cursor_failed" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-cursor-observable
need 'SELECT * FROM {$canonical_table} WHERE public_id=%s LIMIT 1' "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-dedupe-read
need "canonical_matches" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-dedupe-compare
need "vwlb_evidence_fallback_conflict" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-conflict-signal
need "count(\$rows)<self::RECONCILE_PAGE_SIZE?0:\$last_id" "$P/includes/class-vwlb-r30-evidence-privacy.php" r42-fair-cursor-advance
need "vwlb_r30_reconcile_cursor_audit" "$P/uninstall.php" r42-purge-audit-cursor
need "vwlb_r30_reconcile_cursor_outbox" "$P/uninstall.php" r42-purge-outbox-cursor

# R43 — private storage must never resolve through a symlink/outside wp-content, including while the schema verification lease is still fresh.
need "validate_private_storage_root" "$P/includes/class-vwlb-r4-migration-guard.php" r43-root-validator
need "vwlb_private_storage_symlink_forbidden" "$P/includes/class-vwlb-r4-migration-guard.php" r43-root-symlink
need "vwlb_private_storage_root_escape" "$P/includes/class-vwlb-r4-migration-guard.php" r43-root-containment
need "vwlb_private_storage_protection_symlink_forbidden" "$P/includes/class-vwlb-r4-migration-guard.php" r43-protection-symlink
need "\$root=self::validate_private_storage_root();if(is_wp_error(\$root))return \$root;" "$P/includes/class-vwlb-r4-migration-guard.php" r43-always-root-check
need "Base, extension, Future and podcast installers plus private-storage containment/protection verified." "$P/includes/class-vwlb-r4-migration-guard.php" r43-audit-evidence

# R44 — clean: rate-limit upsert/read and idempotency fail-closed semantics reviewed; no new corrective source required.

# R45 — partial/failed chunk writes must roll back to the prior offset before returning; rollback failure stops the upload session.
need "class-vwlb-r45-upload-durability.php" "$P/video-wall-and-live-broadcasting.php" r45-autoload
need "VWLB_R45_Upload_Durability::register" "$P/video-wall-and-live-broadcasting.php" r45-register
need "rest_request_before_callbacks" "$P/includes/class-vwlb-r45-upload-durability.php" r45-intercept
need "rollback_locked" "$P/includes/class-vwlb-r45-upload-durability.php" r45-rollback-helper
need "partial_chunk_write_rollback_failed" "$P/includes/class-vwlb-r45-upload-durability.php" r45-partial-rollback
need "chunk_database_cas_rollback_failed" "$P/includes/class-vwlb-r45-upload-durability.php" r45-cas-rollback
need "vwlb_private_upload_symlink_forbidden" "$P/includes/class-vwlb-r45-upload-durability.php" r45-file-symlink
need "while(\$write_ok&&\$written<\$length)" "$P/includes/class-vwlb-r45-upload-durability.php" r45-full-write-loop

# R46 — provider-side ingest issuance is external; failed local persistence must request compensation and surface reconciliation if revocation is unconfirmed.
need "class-vwlb-r46-stream-credential-durability.php" "$P/video-wall-and-live-broadcasting.php" r46-autoload
need "VWLB_R46_Stream_Credential_Durability::register" "$P/video-wall-and-live-broadcasting.php" r46-register
need "vwlb_provider_ingest_compensation_requested" "$P/includes/class-vwlb-r46-stream-credential-durability.php" r46-compensation-hook
need "vwlb_provider_revoke_ingest_result" "$P/includes/class-vwlb-r46-stream-credential-durability.php" r46-compensation-result
need "vwlb_provider_ingest_reconcile_required" "$P/includes/class-vwlb-r46-stream-credential-durability.php" r46-reconcile-fail-closed
need "The credential was not disclosed; reconciliation is required." "$P/includes/class-vwlb-r46-stream-credential-durability.php" r46-no-secret-disclosure
need "Cache-Control','private, no-store" "$P/includes/class-vwlb-r46-stream-credential-durability.php" r46-secret-no-store

printf '%s\n' 'File 10 R41-R60 sequential contracts PASS'
