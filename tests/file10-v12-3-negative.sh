#!/usr/bin/env bash
# File10 V12.3: real negative fixture tests. Never requires a WordPress runtime.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
GUARD="$ROOT/tests/file10-v12-3-source-scope.php"
BASE="$ROOT/docs/V12.3-FILE10-SOURCE-ACCEPTANCE-OVERLAY-20261114.json"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
php "$GUARD" > /dev/null

python3 - "$BASE" "$TMP" <<'PY'
import copy,json,pathlib,sys
base=json.loads(pathlib.Path(sys.argv[1]).read_text(encoding='utf-8'))
out=pathlib.Path(sys.argv[2])
def case(name,fn):
    obj=copy.deepcopy(base)
    fn(obj)
    (out/(name+'.json')).write_text(json.dumps(obj,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
case('missing_requirement', lambda o: o['source_scope'].pop())
case('wrong_future_priority', lambda o: o['source_scope'][30].__setitem__('priority','P2'))
case('wrong_master_page', lambda o: o['source_scope'][34].__setitem__('master_plan_page',2))
case('missing_qa22', lambda o: o['additional_acceptance'].__setitem__(
    slice(None), [x for x in o['additional_acceptance'] if x['id']!='QA-22']))
case('fake_supplemental_pass', lambda o: next(x for x in o['additional_acceptance']
    if x['id']=='AJ-16').__setitem__('status','passed'))
case('fake_live_pass',lambda o: o['current_environment_evidence'].__setitem__(
    'live_verification',{'status':'passed','evidence':'unverified'}))
case('fake_production_claim',lambda o: o.__setitem__('production_claim_allowed',True))
case('forgotten_blocker',lambda o: o['unresolved_external_gates'].pop())
case('incorrect_master_hash',lambda o: o['authority'].__setitem__('document_sha256','0'*64))
PY
tests=0
for file in "$TMP"/*.json; do
  if FILE10_V123_OVERLAY_FIXTURE="$file" php "$GUARD" > "$TMP/output.log" 2>&1; then
    echo "V12.3 negative fixture unexpectedly PASSED: $(basename "$file")" >&2
    exit 1
  fi
  grep -F "File10 V12.3 source gate FAIL:" "$TMP/output.log" >/dev/null || {
    echo "V12.3 negative fixture failed without gate proof: $(basename "$file")" >&2
    cat "$TMP/output.log" >&2
    exit 1
  }
  tests=$((tests+1))
done
if php "$GUARD" --release > "$TMP/output.log" 2>&1; then
  echo "V12.3 source-only gate incorrectly approved release" >&2
  exit 1
fi
grep -F "production release forbidden" "$TMP/output.log" > /dev/null
echo "File10 V12.3 negative/proof-level fixtures PASS: $tests corrupted cases and release refusal"
