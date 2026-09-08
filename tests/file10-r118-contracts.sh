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

check sbom-serial "$SBOM" '"serialNumber": "urn:uuid:file10-r118-1.2.25-rc1"'
check sbom-review-key "$SBOM" '"name": "review_boundary"'
check sbom-review-r118 "$SBOM" 'R118 release-provenance correction candidate'
check sbom-r119-block "$SBOM" 'R119 blocked until exact-head Green'
check sbom-baseline-key "$SBOM" '"name": "baseline_exact_head"'
check sbom-baseline-sha "$SBOM" 'f2c4345973a4b01896b9250bee6f3d220f82da1c'
check sbom-live-first "$SBOM" 'exact deployed source unverified'

check status-qa-label "$STATUS" 'Automated-QA Green:'
check status-r117 "$STATUS" 'R117'
check status-r118 "$STATUS" 'R118'

check manifest-boundary "$MANIFEST" 'Current review boundary:'
check manifest-r117 "$MANIFEST" 'R117'
check manifest-r118 "$MANIFEST" 'R118'
check manifest-live-first "$MANIFEST" 'Exact deployed source is unverified; GitHub is not live evidence.'

echo 'R118 release provenance consistency contracts PASS'
