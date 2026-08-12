from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="apply_filters( 'vwlb_public_media_track_ref', (string) $row['file_ref'], $row, $object )"
new="apply_filters( 'vwlb_public_media_track_ref', '', $row, $object )"
if new not in s:
    if old not in s: raise SystemExit('R15 track resolver anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R15 — auxiliary track delivery fails closed without an explicit public/signed resolver.\nneed "vwlb_public_media_track_ref', ''" "$P/includes/class-vwlb-future-intelligence.php" r15-track-resolver-fail-closed\n'''
if 'r15-track-resolver-fail-closed' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R15 — DEFECT FIXED\nPublished auxiliary-track DTOs used the stored `file_ref` itself as the default public URL. Without a delivery adapter, a storage/provider reference could be returned directly. The resolver now defaults to an empty value and must explicitly return a viewer-safe public/signed URL; otherwise the track remains unavailable rather than leaking its stored reference.\n\n'''
if '## R15 ' not in ls: LEDGER.write_text(ls+entry)
print('R15 correction prepared')
