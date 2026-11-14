#!/usr/bin/env bash
# Validate source-level issue #22 detection without GitHub admin mutations.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tools/file10-v12-3-ruleset-assessment.py"
BASE="$ROOT/docs/V12.3-FILE10-GITHUB-RULESET-EVIDENCE-20261114.json"
D="$(mktemp -d)"
trap 'rm -rf "$D"' EXIT
set +e
python3 "$TOOL" "$BASE" > "$D/actual.json"
status=$?
set -e
[[ "$status" == 3 ]] || { echo "Issue22 current ruleset drift not detected: $status" >&2;exit 1; }
python3 - "$D/actual.json" <<'PY'
import json,sys
a=json.load(open(sys.argv[1]))
assert not a['is_selected_by_snapshot']
assert not a['required_contexts_match_observed_jobs']
assert not a['source_snapshot_preflight_ready']
assert not a['effective_protection_verified_live']
assert not a['issue22_closure_authorized']
PY
python3 - "$BASE" "$D" <<'PY'
import copy,json,pathlib,sys
s=json.loads(pathlib.Path(sys.argv[1]).read_text(encoding='utf-8'))
d=pathlib.Path(sys.argv[2])
def out(name,fn):
    j=copy.deepcopy(s);fn(j)
    (d/(name+'.json')).write_text(json.dumps(j,indent=2)+'\n')
def matching(j):
    j['ref_conditions']['include'].append('refs/heads/'+j['candidate_branch'])
    j['required_status_checks']=[{'context':name,'integration_id':15368}
        for name in j['exact_sha_jobs']['check_contexts']]
out('matching',matching)
def bad_required(j):
    matching(j)
    j['required_status_checks'][1]['context']='source-package-qa (8.4.26)'
out('bad_required',bad_required)
def disabled(j):
    matching(j)
    j['enforcement']='disabled'
out('disabled',disabled)
def failed_job(j):
    matching(j)
    j['exact_sha_jobs']['conclusions'][0]='failure'
out('failed_job',failed_job)
PY
python3 "$TOOL" "$D/matching.json" > "$D/matching-result.json"
python3 - "$D/matching-result.json" <<'PY'
import json,sys
a=json.load(open(sys.argv[1]))
assert a['source_snapshot_preflight_ready']
assert not a['effective_protection_verified_live']
assert not a['issue22_closure_authorized']
PY
for case in bad_required disabled failed_job;do
    set +e
    python3 "$TOOL" "$D/$case.json" > "$D/$case-result.json"
    code=$?
    set -e
    [[ "$code" == 3 ]] || { echo "Unexpected issue22 negative fixture acceptance: $case" >&2;exit 1; }
done
echo 'File10 V12.3 GitHub issue22 source governance audit PASS: real snapshot BLOCKED; synthetic corrected snapshot still not effective/live proof'
