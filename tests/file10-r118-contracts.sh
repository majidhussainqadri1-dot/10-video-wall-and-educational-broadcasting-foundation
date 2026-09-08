#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SBOM="$ROOT/SBOM-1.2.25-rc1.json"
STATUS="$ROOT/STATUS.md"
MANIFEST="$ROOT/MANIFEST.md"
python3 - "$SBOM" <<'PY'
import json, pathlib, sys
p=pathlib.Path(sys.argv[1]); data=json.loads(p.read_text())
assert data['serialNumber']=='urn:uuid:file10-r118-1.2.25-rc1'
props=data['metadata']['properties']
rb=[x['value'] for x in props if x.get('name')=='review_boundary']
assert rb==['R118 release-provenance correction candidate; R119 blocked until exact-head Green']
base=[x['value'] for x in props if x.get('name')=='baseline_exact_head']
assert base==['f2c4345973a4b01896b9250bee6f3d220f82da1c']
assert any(x.get('name')=='deployment_state' and 'unverified' in x.get('value','') for x in props)
PY
grep -F 'Automated-QA Green: R101–R117 established' "$STATUS" >/dev/null
grep -F 'R117' "$STATUS" >/dev/null
grep -F 'R118' "$STATUS" >/dev/null
grep -F 'R101–R117 are QA-closed' "$MANIFEST" >/dev/null
grep -F 'R118' "$MANIFEST" >/dev/null
grep -F 'Exact deployed source is unverified; GitHub is not live evidence.' "$MANIFEST" >/dev/null
echo 'R118 release provenance consistency contracts PASS'
