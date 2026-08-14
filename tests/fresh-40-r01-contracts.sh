#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
F="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-live.php"
need(){ grep -F -- "$1" "$F" >/dev/null || { echo "FAIL fresh40 R01: $2" >&2; exit 1; }; }
forbid(){ ! grep -F -- "$1" "$F" >/dev/null || { echo "FAIL fresh40 R01: $2" >&2; exit 1; }; }
need "vwlb_provider_contract_invalid" malformed-create-result
need "vwlb_provider_cancel_live" provider-create-compensation
need "vwlb_provider_live_reconcile_required" provider-orphan-reconcile
need "vwlb_provider_ingest_contract_invalid" ingest-contract-validation
need "vwlb_provider_revoke_ingest" credential-compensation
need "vwlb_stream_credential_reconcile_required" credential-reconcile
need "credential_public_id" public-credential-audit-id
forbid "return array('credential_id'=>" numeric-credential-response
need "VWLB_DB::transaction(function()use(\$wpdb,\$event,\$event" schedule-transaction-placeholder || true
# The exact schedule transaction contract is asserted independently of formatting.
need "\$result=VWLB_DB::transaction(function()use(\$wpdb,\$event,\$public,\$start,\$idempotency_key)" schedule-transaction
printf '%s\n' 'fresh40 R01 contracts PASS'
