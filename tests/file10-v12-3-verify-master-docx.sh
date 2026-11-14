#!/usr/bin/env bash
# Tests original-DOCX fidelity algorithm using synthetic Word files only.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERIFY="$ROOT/tools/file10-v12-3-verify-master-docx.py"
D="$(mktemp -d)"
trap 'rm -rf "$D"' EXIT
python3 "$VERIFY" --manifest-only > "$D/manifest-only.log"
python3 - "$ROOT" "$D" <<'PY'
import csv,hashlib,pathlib,sys,unicodedata,zipfile
import xml.etree.ElementTree as ET
root,folder=map(pathlib.Path,sys.argv[1:])
with (root/'docs/V12.3-FILE10-ORIGINAL-DOCX-CONTENT-DIGESTS.tsv').open(encoding='utf-8') as f:
    ids=[r['requirement_id'] for r in csv.DictReader(f,delimiter='\t')]
assert len(ids)==64
w='{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
ET.register_namespace('w',w[1:-1])
document=ET.Element(w+'document')
body=ET.SubElement(document,w+'body')
def paragraph(i):
    p=ET.SubElement(body,w+'p')
    r=ET.SubElement(p,w+'r')
    t=ET.SubElement(r,w+'t')
    t.text=f'{ids[i]} synthetic verified input with Urdu عربی and checkpoint #{i}'
    return unicodedata.normalize('NFC',t.text.strip())
texts=[paragraph(i) for i in range(64)]
def package(name):
    path=folder/name
    with zipfile.ZipFile(path,'w',compression=zipfile.ZIP_DEFLATED) as z:
        z.writestr('word/document.xml',ET.tostring(document,encoding='utf-8',xml_declaration=True))
    return hashlib.sha256(path.read_bytes()).hexdigest()
sha=package('synthetic.docx')
with (folder/'synthetic.tsv').open('w',encoding='utf-8',newline='') as f:
    fields=['requirement_id','paragraph_index_zero_based','codepoints_nfc','sha256_nfc_utf8']
    writer=csv.DictWriter(f,fieldnames=fields,delimiter='\t');writer.writeheader()
    for i,s in enumerate(texts):
        writer.writerow(dict(requirement_id=ids[i],paragraph_index_zero_based=i,
            codepoints_nfc=len(s),sha256_nfc_utf8=hashlib.sha256(s.encode('utf-8')).hexdigest()))
(folder/'synthetic.sha').write_text(sha)
document[0][0][0][0].text=ids[0]+' TAMPERED fixture with different meaning and same ID'
tampered_sha=package('tampered.docx')
(folder/'tampered.sha').write_text(tampered_sha)
PY
SHA="$(cat "$D/synthetic.sha")"
python3 "$VERIFY" --manifest "$D/synthetic.tsv" "$D/synthetic.docx" \
    --fixture-plan-sha256 "$SHA" > "$D/ok.log"
if python3 "$VERIFY" --manifest "$D/synthetic.tsv" "$D/synthetic.docx" \
    --fixture-plan-sha256 "$(printf '0%.0s' {1..64})" > "$D/no.log" 2>&1; then
    echo 'DOCX original-binary SHA mismatch incorrectly passed' >&2;exit 1
fi
grep -F 'binary SHA-256 differs' "$D/no.log" > /dev/null
if python3 "$VERIFY" --manifest "$D/synthetic.tsv" "$D/tampered.docx" \
    --fixture-plan-sha256 "$(cat "$D/tampered.sha")" > "$D/no.log" 2>&1; then
    echo 'DOCX paragraph tamper incorrectly passed' >&2;exit 1
fi
grep -F 'source paragraph drift' "$D/no.log" > /dev/null
if python3 "$VERIFY" --manifest "$D/synthetic.tsv" \
    --manifest-only "$D/synthetic.docx" > "$D/no.log" 2>&1; then
    echo 'manifest-only incorrectly accepted Word file as verified' >&2;exit 1
fi
grep -F 'cannot pretend to verify' "$D/no.log" > /dev/null
echo 'File10 V12.3 original Word fidelity tool PASS: 64 digest structure, synthetic match, tamper rejection, binary SHA rejection'
