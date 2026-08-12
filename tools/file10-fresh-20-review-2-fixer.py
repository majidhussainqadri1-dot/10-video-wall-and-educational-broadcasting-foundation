from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="$poll=self::public_row('live_polls',$poll_id);if(!$poll)return null;$event=self::live($poll['live_event_id']);if(!$event||!VWLB_Security::can_view($event))return null;global $wpdb;"
new="$poll=self::public_row('live_polls',$poll_id);if(!$poll)return null;$event=self::live($poll['live_event_id']);if(!$event||!VWLB_Security::can_view($event))return null;if(!in_array($poll['status'],array('open','closed'),true)&&!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,$event,'future_poll_preview'))return null;global $wpdb;"
if new not in s:
    if old not in s: raise SystemExit('R10 poll visibility anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R10 — draft polls are not publicly readable before explicit open/close lifecycle.\nneed "future_poll_preview" "$P/includes/class-vwlb-future-intelligence.php" r10-poll-preview-guard\nneed "array('open','closed')" "$P/includes/class-vwlb-future-intelligence.php" r10-public-poll-status\n'''
if 'r10-poll-preview-guard' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R10 — DEFECT FIXED\nThe public poll read contract checked event visibility but did not check the poll lifecycle state. Anyone who obtained an opaque poll identifier could therefore read a `draft` poll before the broadcaster explicitly opened it. Viewer reads are now limited to `open` or `closed` polls; only an authorized broadcaster may preview another state.\n\n'''
if '## R10 ' not in ls: LEDGER.write_text(ls+entry)
print('R10 correction prepared')
