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
# R05 — production sources/scenes preserve ownership, reject raw secrets/cross-event sources, and serialize program switching.
need "contains_raw_secret" "$P/includes/class-vwlb-future-intelligence.php" r05-secret-rejection
need "Every scene source must be an active source of the same live event" "$P/includes/class-vwlb-future-intelligence.php" r05-scene-source-scope
need "find('live_events',\$event['id'],true)" "$P/includes/class-vwlb-future-intelligence.php" r05-program-lock
need "id<>%d" "$P/includes/class-vwlb-future-intelligence.php" r05-single-program
# R06 — F10-FUT-002 guest/co-host delegation is File00-eligible, scoped, expiring, audited, CAS-protected and revocable.
need "File 00 identity and eligibility assertions" "$P/includes/class-vwlb-future-intelligence.php" r06-target-identity
need "Guest invitation changed concurrently" "$P/includes/class-vwlb-future-intelligence.php" r06-accept-cas
need "BroadcastGuestRevoked" "$P/includes/class-vwlb-future-intelligence.php" r06-revoke-event
need "/revoke','POST','guest_revoke'" "$P/includes/class-vwlb-future-rest.php" r06-revoke-route
# R07 — provider-dependent DVR/latency/backup policy is capability-declared, version-safe and truthfully normalized.
need "vwlb_latency_mode_unavailable" "$P/includes/class-vwlb-future-intelligence.php" r07-latency-capability
need "vwlb_dvr_unavailable" "$P/includes/class-vwlb-future-intelligence.php" r07-dvr-capability
need "vwlb_backup_provider_required" "$P/includes/class-vwlb-future-intelligence.php" r07-backup-required
need "submit its current version" "$P/includes/class-vwlb-future-intelligence.php" r07-config-cas
need "min(6*HOUR_IN_SECONDS" "$P/includes/class-vwlb-future-adapters.php" r07-provider-result-bound
# R08 — simulcast secrets are recursively rejected and provider transitions reserve a CAS state before external side effects.
need "self::contains_raw_secret(\$config)" "$P/includes/class-vwlb-future-intelligence.php" r08-nested-secret
need "array('disabled','ready')" "$P/includes/class-vwlb-future-intelligence.php" r08-no-client-active
need "status'=>'transitioning'" "$P/includes/class-vwlb-future-adapters.php" r08-transition-reservation
need "submit its current version" "$P/includes/class-vwlb-future-adapters.php" r08-transition-version
need "reconciliation is required" "$P/includes/class-vwlb-future-adapters.php" r08-provider-local-divergence
printf '%s\n' 'fresh 40-review regression contracts PASS'
