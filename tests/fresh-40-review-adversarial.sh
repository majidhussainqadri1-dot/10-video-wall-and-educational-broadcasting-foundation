#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL fresh-40-adversarial: $*" >&2; exit 1; }
forbid(){ if grep -R -F -- "$1" "$2" >/dev/null; then fail "$3"; fi; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

# Release identity: current runtime is 1.2.1-rc1 while schema versions remain independently governed.
need "Version: 1.2.1-rc1" "$P/video-wall-and-live-broadcasting.php" runtime-version
forbid "Version: 1.2.0-rc1" "$P/video-wall-and-live-broadcasting.php" stale-runtime-version
need "VWLB_FUTURE_SCHEMA_VERSION', '1.2.0" "$P/video-wall-and-live-broadcasting.php" future-schema-stable

# Old unsafe implementation patterns must not reappear.
forbid "return array('provider_event_ref'=>'custom_" "$P/includes/class-vwlb-providers.php" fake-custom-live-success
forbid "return array('provider_event_ref'=>'','state'=>'configured')" "$P/includes/class-vwlb-providers.php" fake-base-live-success
forbid "if(false===\$inserted){return \$this->response(array('accepted'=>true,'duplicate'=>true));}" "$P/includes/class-vwlb-rest.php" webhook-db-as-duplicate
forbid "CAP_PUBLISH,\$video,'review_caption'" "$P/includes/class-vwlb-videos.php" caption-publish-as-review
forbid "'contentUrl'=>\$video['source_url']" "$P/includes/class-vwlb-seo.php" raw-source-seo
forbid "if(get_option('vwlb_schema_version')!==VWLB_SCHEMA_VERSION)VWLB_DB::install_schema();" "$P/includes/class-vwlb-activator.php" unchecked-base-migration
forbid "array('disabled','ready','active','failed')" "$P/includes/class-vwlb-future-intelligence.php" client-simulcast-active-state

# Positive invariants for the corrected paths.
need "rest_mutation_before" "$P/includes/class-vwlb-security.php" mutation-idempotency
need "vwlb_takedown_decision_forbidden" "$P/includes/class-vwlb-moderation.php" claimant-authority
need "verify_schema_sql" "$P/includes/class-vwlb-db.php" schema-verification
need "vwlb_reel_media_not_ready" "$P/includes/class-vwlb-extensions.php" reel-boundary
need "vwlb_public_seo_content_url" "$P/includes/class-vwlb-seo.php" seo-boundary
need "vwlb_privacy_delete_failed" "$P/includes/class-vwlb-privacy.php" privacy-fail-closed

# The sequential review ledger must retain one permanent regression section for every defect round so far.
for n in $(seq -w 2 27) $(seq -w 29 37); do
  grep -F "# R${n} —" "$ROOT/tests/fresh-40-review-contracts.sh" >/dev/null || fail "missing-review-ledger-R${n}"
done
# R01 and R28 were intentionally clean source reviews; they are documented in the final review report, not defect gates.

printf '%s\n' 'fresh 40-review adversarial contracts PASS'
