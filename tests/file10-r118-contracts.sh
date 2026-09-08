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
    echo "PASS R118/R121/R122 $label"
  else
    echo "FAIL R118/R121/R122 $label: missing [$needle] in $file" >&2
    return 1
  fi
}

# R118 established provenance continuity. R121 advanced the reviewed baseline
# after R120 Green. R122 removes the circular requirement to mutate repository
# metadata after QA merely to flip a transient pending/green label. Historical
# evidence remains immutable; current exact-head closure is resolved from the
# successful File 10 Release QA result for that same commit SHA.
check sbom-serial "$SBOM" '"serialNumber": "urn:uuid:file10-1.2.25-rc1"'
check sbom-review-key "$SBOM" '"name": "review_boundary"'
check sbom-r121-green "$SBOM" 'R121 exact-head QA Green at d466a9020d38a49fd9dcee8085da58775cd26601, run 34277370167'
check sbom-r120-history "$SBOM" 'historical R120 exact-head QA Green at 4094a9a861eae9a47222cc7a28959eaff87c6c76, run 34271494386'
check sbom-r119-history "$SBOM" 'historical R119 exact-head QA Green at c2d48ed9bbd5cbc29f9fe186340d01acf4e857ce, run 34259864776'
check sbom-baseline-key "$SBOM" '"name": "baseline_exact_head"'
check sbom-baseline-sha "$SBOM" 'd466a9020d38a49fd9dcee8085da58775cd26601'
check sbom-closure-key "$SBOM" '"name": "exact_head_closure_model"'
check sbom-closure-rule "$SBOM" 'successful File 10 Release QA for the same commit SHA'
check sbom-live-first "$SBOM" 'exact deployed source unverified'

check status-closure-rule "$STATUS" 'Exact-head closure rule:'
check status-r121-run "$STATUS" 'run `34277370167`'
check status-r122 "$STATUS" 'R122 read-only review findings are frozen'
check status-batch "$STATUS" 'clean rounds: **0/10**'

check manifest-boundary "$MANIFEST" 'R101–R120 sequential cycle is QA-closed'
check manifest-r121-run "$MANIFEST" 'run `34277370167`'
check manifest-r122 "$MANIFEST" 'R122 read-only review findings are frozen'
check manifest-closure-model "$MANIFEST" 'Exact-head closure model:'
check manifest-live-first "$MANIFEST" 'Exact deployed source is unverified; GitHub is not live evidence.'

check ledger-r120-closed "$LEDGER" 'R120 is closed.'
check ledger-batch "$LEDGER" 'Clean rounds: **0/10**'
check ledger-defects "$LEDGER" '**R111, R112, R113, R114, R115, R116, R117, R118, R119, R120**'

echo 'R118/R121/R122 release provenance continuity contracts PASS'
