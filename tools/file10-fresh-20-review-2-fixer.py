from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php'
BUILD=ROOT/'tools/build-package.sh'
RELEASE=ROOT/'.github/workflows/file10-release.yml'
OLDTEST=ROOT/'tests/fresh-20-review-contracts.sh'
STATIC=ROOT/'tests/static-contracts.sh'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'

def repl(path, old, new, label):
    s=path.read_text()
    if new in s and old not in s:
        return
    if old not in s:
        raise SystemExit(label+' anchor missing')
    path.write_text(s.replace(old,new))

repl(PLUGIN,'1.2.2-rc1','1.2.3-rc1','R20 plugin version')
repl(BUILD,'video-wall-and-live-broadcasting-1.2.2-rc1.zip','video-wall-and-live-broadcasting-1.2.3-rc1.zip','R20 build artifact')
repl(RELEASE,'1.2.2-rc1','1.2.3-rc1','R20 release workflow')
repl(OLDTEST,'1.2.2-rc1','1.2.3-rc1','R20 prior release identity regression')
repl(STATIC,'1.2.2-rc1','1.2.3-rc1','R20 static release identity regression')

ts=TEST.read_text()
checks='''\n# R20 — the corrected candidate, package builder, QA and release workflow share one release identity.\nneed "Version: 1.2.3-rc1" "$P/video-wall-and-live-broadcasting.php" r20-runtime-version\nneed "video-wall-and-live-broadcasting-1.2.3-rc1.zip" "$ROOT/tools/build-package.sh" r20-build-version\nneed "file10-video-wall-live-1.2.3-rc1" "$ROOT/.github/workflows/file10-release.yml" r20-artifact-version\nneed "Version: 1.2.3-rc1" "$ROOT/tests/static-contracts.sh" r20-static-version\n'''
if 'r20-runtime-version' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R20 — DEFECT FIXED\nThe final fresh release-hygiene review found that the working corrective branch was explicitly the `v1.2.3-rc1` candidate, but runtime metadata, default package naming, release workflow artifact naming and prior release-identity regression contracts still advertised `1.2.2-rc1`. Shipping the reviewed corrections under the prior candidate identity would make package/deployment parity evidence ambiguous. Runtime, deterministic builder, GitHub release QA artifact naming and all active release-identity regression expectations are now synchronized to `1.2.3-rc1`; schema versions remain unchanged because this corrective cycle did not introduce a new database schema. The first R20 validation exposed one stale static release-identity assertion; it was corrected within R20 before any R20 product changes were accepted.\n\n'''
if '## R20 ' not in ls: LEDGER.write_text(ls+entry)
print('R20 correction prepared')
