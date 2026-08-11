#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL fresh-40-review: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R02 — serialized base/extension/Future migration and activation parity.
need "MIGRATION_LOCK" "$P/includes/class-vwlb-activator.php" r02-migration-lock
need "VWLB_Future_Intelligence::install_schema" "$P/includes/class-vwlb-activator.php" r02-future-activation-schema
need "VWLB_Activator::reconcile_schema" "$P/includes/class-vwlb-plugin.php" r02-runtime-reconcile
# R03 — publisher/broadcaster cannot self-assert human-reviewed Future states.
need "Human review permission is required to change a generated track review state" "$P/includes/class-vwlb-future-intelligence.php" r03-track-review-guard
need "Timestamp corrections require independent review permission" "$P/includes/class-vwlb-future-intelligence.php" r03-correction-review-guard
# R04 — all File 10 REST mutations are rate-limited and idempotency guarded; signed webhooks retain provider replay dedupe.
need "rest_request_before_callbacks" "$P/includes/class-vwlb-plugin.php" r04-before-hook
need "rest_request_after_callbacks" "$P/includes/class-vwlb-plugin.php" r04-after-hook
need "rest_mutation_before" "$P/includes/class-vwlb-security.php" r04-mutation-guard
need "idempotency_abort" "$P/includes/class-vwlb-security.php" r04-idempotency-abort
need "'webhook'===\$name" "$P/includes/class-vwlb-security.php" r04-webhook-exception
need "Idempotency-Key" "$P/assets/js/vwlb.js" r04-browser-idempotency
printf '%s\n' 'fresh 40-review regression contracts PASS'
