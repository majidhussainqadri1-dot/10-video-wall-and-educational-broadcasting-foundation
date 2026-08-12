from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="$statuses=$include_candidates&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_list')?\"('candidate','reviewed','published')\":\"('reviewed','published')\";"
new="$statuses=$include_candidates&&VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'future_annotation_list')?\"('candidate','reviewed','published')\":\"('published')\";"
if new not in s:
    if old not in s: raise SystemExit('R09 annotation visibility anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R09 — public annotation listing exposes only explicitly published records.\nneed "\\\"('published')\\\"" "$P/includes/class-vwlb-future-intelligence.php" r09-public-published-only\n'''
if 'r09-public-published-only' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R09 — DEFECT FIXED\nThe public annotation read path exposed both `reviewed` and `published` records. Review completion is not publication, so a citation, correction, overlay or knowledge-link could become externally visible before its explicit publish transition. Public annotation reads now return only `published` records; reviewers may still request candidate/reviewed states through the authorized review path.\n\n'''
if '## R09 ' not in ls: LEDGER.write_text(ls+entry)
print('R09 correction prepared')
