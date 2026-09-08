#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SBOM="$ROOT/SBOM-1.2.25-rc1.json"
STATUS="$ROOT/STATUS.md"
MANIFEST="$ROOT/MANIFEST.md"
python3 - "$SBOM" "$STATUS" "$MANIFEST" <<'PY'
import json, pathlib, sys
sbom=pathlib.Path(sys.argv[1]); status=pathlib.Path(sys.argv[2]).read_text(); manifest=pathlib.Path(sys.argv[3]).read_text()
data=json.loads(sbom.read_text())
assert data['serialNumber']=='urn:uuid:file10-r118-1.2.25-rc1'
props=data['metadata']['properties']
rb=[x['value'] for x in props if x.get('name')=='review_boundary']
assert rb==['R118 release-provenance correction candidate; R119 blocked until exact-head Green']
base=[x['value'] for x in props if x.get('name')=='baseline_exact_head']
assert base==['f2c4345973a4b01896b9250bee6f3d220f82da1c']
assert any(x.get('name')=='deployment_state' and 'unverified' in x.get('value','') for x in props)
# Preserve the semantic provenance requirements without depending on a particular
# Unicode dash byte sequence in shell grep matching.
assert 'Automated-QA Green:' in status and 'R101' in status and 'R117' in status and 'R118' in status
assert 'Current review boundary:' in manifest and 'R101' in manifest and 'R117' in manifest and 'R118' in manifest
assert 'Exact deployed source is unverified; GitHub is not live evidence.' in manifest
PY
echo 'R118 release provenance consistency contracts PASS'
