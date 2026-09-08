#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SBOM="$ROOT/SBOM-1.2.25-rc1.json"
STATUS="$ROOT/STATUS.md"
MANIFEST="$ROOT/MANIFEST.md"
LEDGER="$ROOT/docs/FILE-10-R101-R120-REVIEW-2026-09-06.md"

check(){
  local label="$1" file="$2" needle="$3"
  if grep -Fq -- "$needle" "$file"; then
    echo "PASS R118/R121 $label"
  else
    echo "FAIL R118/R121 $label: missing [$needle] in $file" >&2
    return 1
  fi
}

# R118 established provenance continuity. R121 advances the single current
# candidate only after R120 exact-head Green, while retaining historical R119
# evidence and the Live-First separation between repository and deployment.
check sbom-serial "$SBOM" '"serialNumber": "urn:uuid:file10-r121-1.2.25-rc1"'
check sbom-review-key "$SBOM" '"name": "review_boundary"'
check sbom-review-r121 "$SBOM" 'R121 post-R120 closure/provenance correction candidate'
check sbom-r120-green "$SBOM" 'R120 exact-head QA Green at 4094a9a861eae9a47222cc7a28959eaff87c6c76, run 34271494386'
check sbom-r119-history "$SBOM" 'historical R119 exact-head QA Green at c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce, run 34259864776'
check sbom-baseline-key "$SBOM" '"name": "baseline_exact_head"'
check sbom-baseline-sha "$SBOM" '4094a9a861eae9a47222cc7a28959eaff87c6c76'
check sbom-live-first "$SBOM" 'exact deployed source unverified'

check status-qa-label "$STATUS" 'Automated-QA Green through R120'
check status-r120-run "$STATUS" 'run `34271494386`'
check status-batch "$STATUS" 'clean rounds: **0/10**'
check status-r121 "$STATUS" 'R121 review:'

check manifest-boundary "$MANIFEST" 'R101–R120 sequential cycle is QA-closed'
check manifest-r120-run "$MANIFEST" 'run `34271494386`'
check manifest-batch "$MANIFEST" 'clean rounds 0/10'
check manifest-r121 "$MANIFEST" 'R121 post-R120 closure/provenance review findings are frozen'
check manifest-live-first "$MANIFEST" 'Exact deployed source is unverified; GitHub is not live evidence.'

check ledger-r120-closed "$LEDGER" 'R120 is closed.'
check ledger-batch "$LEDGER" 'Clean rounds: **0/10**'
check ledger-defects "$LEDGER" '**R111, R112, R113, R114, R115, R116, R117, R118, R119, R120**'

echo 'R118/R121 release provenance continuity contracts PASS'
