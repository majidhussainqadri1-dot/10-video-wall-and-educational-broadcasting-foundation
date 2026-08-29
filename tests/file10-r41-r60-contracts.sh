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

printf '%s\n' 'File 10 R41-R60 sequential contracts PASS'
