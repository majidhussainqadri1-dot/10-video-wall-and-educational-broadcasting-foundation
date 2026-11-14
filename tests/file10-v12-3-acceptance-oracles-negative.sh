#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tests/file10-v12-3-acceptance-oracles.php"
BASE="$ROOT/docs/V12.3-FILE10-64-REAL-NEGATIVE-ACCEPTANCE-ORACLES.csv"
D="$(mktemp -d)"
trap 'rm -rf "$D"' EXIT
php "$TOOL" >/dev/null
python3 - "$BASE" "$D" <<'PY'
import copy,csv,pathlib,sys
src=pathlib.Path(sys.argv[1]);dst=pathlib.Path(sys.argv[2])
with src.open(encoding='utf-8',newline='') as f:
    reader=csv.DictReader(f)
    header=reader.fieldnames
    original=list(reader)
def case(name,fn):
    rows=copy.deepcopy(original)
    fn(rows)
    with (dst/(name+'.csv')).open('w',encoding='utf-8',newline='') as f:
        writer=csv.DictWriter(f,fieldnames=header)
        writer.writeheader()
        writer.writerows(rows)
case('missing',lambda x:x.pop())
case('duplicate',lambda x:x.append(copy.deepcopy(x[0])))
case('wrong_category',lambda x:x[0].__setitem__('category','FUT'))
case('claimed_pass',lambda x:x[0].__setitem__('execution_status','passed'))
case('blank_oracle',lambda x:x[0].__setitem__('negative_scenario',''))
case('blank_evidence',lambda x:x[0].__setitem__('required_real_evidence',''))
case('identical_oracle',lambda x:x[1].__setitem__('negative_scenario',x[0]['negative_scenario']))
PY
n=0
for f in "$D"/*.csv; do
    if FILE10_V123_ORACLES_FIXTURE="$f" php "$TOOL" >"$D/err.log" 2>&1;then
        echo "Unverified File10 V12.3 negative oracle accepted: $(basename "$f")" >&2
        exit 1
    fi
    grep -F 'File10 V12.3 negative-oracle gate FAIL:' "$D/err.log" >/dev/null || {
        cat "$D/err.log" >&2;exit 1;
    }
    n=$((n+1))
done
echo "File10 V12.3 negative acceptance catalogue PASS: $n mutation refusals; no live inference"
