from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="foreach($items as &$i){$i['metadata']=VWLB_Helpers::json($i['metadata_json']);unset($i['metadata_json']);}return array('items'=>$items);"
new="$can_internal=$include_candidates&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_list');foreach($items as &$i){if($can_internal)$i['metadata']=VWLB_Helpers::json($i['metadata_json']);unset($i['metadata_json']);}return array('items'=>$items);"
if new not in s:
    if old not in s: raise SystemExit('R14 annotation metadata anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R14 — arbitrary annotation metadata is reviewer-only.\nneed "can_internal" "$P/includes/class-vwlb-future-intelligence.php" r14-annotation-metadata-guard\n'''
if 'r14-annotation-metadata-guard' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R14 — DEFECT FIXED\nPublic annotation responses decoded and returned arbitrary `metadata_json`, although that field is not a public schema and is only secret-scanned. Internal provenance/provider/workflow details could leak. Metadata is now returned only to an authorized reviewer request; public DTOs contain only explicit public annotation fields.\n\n'''
if '## R14 ' not in ls: LEDGER.write_text(ls+entry)
print('R14 correction prepared')
