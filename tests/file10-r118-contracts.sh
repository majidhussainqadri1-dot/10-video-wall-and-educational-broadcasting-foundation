#!/usr/bin/env bash
set -euxo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SBOM="$ROOT/SBOM-1.2.25-rc1.json"
STATUS="$ROOT/STATUS.md"
MANIFEST="$ROOT/MANIFEST.md"

grep -F '"serialNumber": "urn:uuid:file10-r118-1.2.25-rc1"' "$SBOM" >/dev/null
grep -F '"name": "review_boundary"' "$SBOM" >/dev/null
grep -F 'R118 release-provenance correction candidate' "$SBOM" >/dev/null
grep -F 'R119 blocked until exact-head Green' "$SBOM" >/dev/null
grep -F '"name": "baseline_exact_head"' "$SBOM" >/dev/null
grep -F 'f2c4345973a4b01896b9250bee6f3d220f82da1c' "$SBOM" >/dev/null
grep -F 'exact deployed source unverified' "$SBOM" >/dev/null

grep -F 'Automated-QA Green:' "$STATUS" >/dev/null
grep -F 'R117' "$STATUS" >/dev/null
grep -F 'R118' "$STATUS" >/dev/null

grep -F 'Current review boundary:' "$MANIFEST" >/dev/null
grep -F 'R117' "$MANIFEST" >/dev/null
grep -F 'R118' "$MANIFEST" >/dev/null
grep -F 'Exact deployed source is unverified; GitHub is not live evidence.' "$MANIFEST" >/dev/null

echo 'R118 release provenance consistency contracts PASS'
