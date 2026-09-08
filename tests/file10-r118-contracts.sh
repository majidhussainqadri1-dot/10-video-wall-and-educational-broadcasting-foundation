#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SBOM="$ROOT/SBOM-1.2.25-rc1.json"
STATUS="$ROOT/STATUS.md"
MANIFEST="$ROOT/MANIFEST.md"

check(){
  local label="$1" file="$2" needle="$3"
  if grep -Fq -- "$needle" "$file"; then
    echo "PASS R118 $label"
  else
    echo "FAIL R118 $label: missing [$needle] in $file" >&2
    return 1
  fi
}

# R118 provenance guarantees are historical invariants. The current single
# candidate SBOM must advance with later rounds rather than remain pinned to
# the R118 serial/baseline forever. Verify the current R120 provenance state
# while retaining explicit R118 closure evidence in STATUS/MANIFEST.
check sbom-serial "$SBOM" '"serialNumber": "urn:uuid:file10-r120-1.2.25-rc1"'
check sbom-review-key "$SBOM" '"name": "review_boundary"'
check sbom-review-r120 "$SBOM" 'R120 repository-provenance closure correction candidate'
check sbom-r119-green "$SBOM" 'R119 exact-head QA Green'
check sbom-baseline-key "$SBOM" '"name": "baseline_exact_head"'
check sbom-baseline-sha "$SBOM" 'c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce'
check sbom-live-first "$SBOM" 'exact deployed source unverified'

check status-qa-label "$STATUS" 'Automated-QA Green through R119'
check status-r117 "$STATUS" 'R117'
check status-r118 "$STATUS" 'R118'
check status-r119 "$STATUS" 'R119'
check status-r120 "$STATUS" 'R120'

check manifest-boundary "$MANIFEST" 'Current review boundary:'
check manifest-r117 "$MANIFEST" 'R117'
check manifest-r118 "$MANIFEST" 'R118'
check manifest-r119 "$MANIFEST" 'R119'
check manifest-r120 "$MANIFEST" 'R120'
check manifest-live-first "$MANIFEST" 'Exact deployed source is unverified; GitHub is not live evidence.'

echo 'R118 release provenance continuity contracts PASS'
