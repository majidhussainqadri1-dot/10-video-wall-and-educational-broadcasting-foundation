#!/usr/bin/env bash
# Acceptance audit is a read-only source evidence reporter, never a production signoff.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tools/file10-v12-3-acceptance-audit.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

php -l "$TOOL" >/dev/null
php "$TOOL" --json > "$TMP/actual.json"
php -r '
$r=json_decode(file_get_contents($argv[1]),true);
if(!is_array($r)||($r["totals"]["total"]??0)!==64||
   ($r["totals"]["individual_unaccepted"]??-1)!==64||
   ($r["totals"]["staging_unverified"]??-1)!==64||
   ($r["totals"]["live_unverified"]??-1)!==64||
   ($r["totals"]["native_candidate_sources"]??0)!==54||
   ($r["totals"]["external_cf04_required"]??0)!==10||
   ($r["release_approval"]??null)!==false||
   ($r["live_source_verified"]??null)!==false||
   count($r["requirements"]??[])!==64)exit(1);
' "$TMP/actual.json"

python3 - "$ROOT" "$TMP" <<'PY'
import copy,csv,json,pathlib,sys
root,tmp=(pathlib.Path(x) for x in sys.argv[1:])
original=root/'docs/V12.1-FILE10-64-REQUIREMENT-EVIDENCE.csv'
mapping=root/'docs/V12.3-FILE10-CODE-AND-ACCEPTANCE-GAPS.csv'
overlay=root/'docs/V12.3-FILE10-SOURCE-ACCEPTANCE-OVERLAY-20261114.json'
def read(path):
    with path.open(newline='',encoding='utf-8') as f:
        r=csv.DictReader(f)
        return r.fieldnames,list(r)
a,rows=read(original)
b,matrix=read(mapping)
base=json.loads(overlay.read_text(encoding='utf-8'))
def dump_csv(path,head,vals):
    with path.open('w',newline='',encoding='utf-8') as f:
        w=csv.DictWriter(f,fieldnames=head);w.writeheader();w.writerows(vals)
def case(name, change):
    p=tmp/name
    p.mkdir()
    r=copy.deepcopy(rows);m=copy.deepcopy(matrix);o=copy.deepcopy(base)
    change(r,m,o)
    dump_csv(p/'ledger.csv',a,r)
    dump_csv(p/'matrix.csv',b,m)
    (p/'overlay.json').write_text(json.dumps(o,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def status(r,m,field,value):
    r[0][field]=value
    if field in m[0]:m[0][field]=value
case('missing_row',lambda r,m,o:r.pop())
case('duplicate_row',lambda r,m,o:r.append(copy.deepcopy(r[0])))
case('matrix_lost_row',lambda r,m,o:m.pop())
case('invalid_priority',lambda r,m,o:m[0].__setitem__('priority','P2'))
case('wrong_cf04_owner',lambda r,m,o:m[-1].__setitem__('source_kind','code_candidate'))
case('individual_pass_no_proof',lambda r,m,o:status(r,m,'individual_test_status','passed'))
def stage_without_individual(r,m,o):
    status(r,m,'staging_status','passed')
    r[0]['staging_evidence']='synthetic_fixture_only'
case('stage_without_individual',stage_without_individual)
def live_without_staging(r,m,o):
    status(r,m,'individual_test_status','passed')
    r[0]['test_evidence']='synthetic_fixture_only'
    status(r,m,'live_status','passed')
    r[0]['live_evidence']='synthetic_fixture_only'
case('live_without_staging',live_without_staging)
case('forgot_issue26',lambda r,m,o:o['unresolved_external_gates'].pop())
case('invent_deployed_approval',lambda r,m,o:o.__setitem__('production_claim_allowed',True))
def all_fake_passes(r,m,o):
    for row,record in zip(r,m):
        for field in ('individual_test_status','staging_status','live_status'):
            row[field]='passed';record[field]='passed'
        for field in ('test_evidence','staging_evidence','live_evidence'):
            row[field]='synthetic-no-real-environment'
case('synthetic_all_passes',all_fake_passes)
PY

negative=0
for f in "$TMP"/*/; do
    name="$(basename "$f")"
    if [[ "$name" == 'synthetic_all_passes' ]]; then
        FILE10_V123_LEDGER_FIXTURE="$f/ledger.csv" \
        FILE10_V123_MATRIX_FIXTURE="$f/matrix.csv" \
        FILE10_V123_OVERLAY_FIXTURE="$f/overlay.json" \
        php "$TOOL" --json > "$TMP/synthetic.json"
        php -r '
          $r=json_decode(file_get_contents($argv[1]),true);
          if(($r["totals"]["individual_passed_declared"]??0)!==64 ||
             ($r["totals"]["staging_passed_declared"]??0)!==64 ||
             ($r["totals"]["live_passed_declared"]??0)!==64 ||
             ($r["release_approval"]??null)!==false ||
             ($r["live_source_verified"]??null)!==false ||
             ($r["staging_environment_verified"]??null)!==false)exit(1);
        ' "$TMP/synthetic.json"
        continue
    fi
    if FILE10_V123_LEDGER_FIXTURE="$f/ledger.csv" \
       FILE10_V123_MATRIX_FIXTURE="$f/matrix.csv" \
       FILE10_V123_OVERLAY_FIXTURE="$f/overlay.json" \
       php "$TOOL" --json >"$TMP/failure.log" 2>&1; then
        echo "File10 V12.3 audit negative fixture incorrectly passed: $name" >&2
        exit 1
    fi
    grep -F 'File10 V12.3 acceptance audit FAIL:' "$TMP/failure.log" >/dev/null || {
        echo "File10 V12.3 audit fixture failed for unexpected reason: $name" >&2
        cat "$TMP/failure.log" >&2
        exit 1
    }
    negative=$((negative+1))
done
if php "$TOOL" --release >"$TMP/release.log" 2>&1; then
    echo 'File10 V12.3 audit incorrectly allowed --release' >&2
    exit 1
fi
grep -F 'NO-GO: release evidence' "$TMP/release.log" >/dev/null
echo "File10 V12.3 acceptance audit PASS: $negative refusal fixtures, full fake-passes still NO-GO, no deployed proof"
