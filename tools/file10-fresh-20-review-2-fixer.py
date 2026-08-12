from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="$guests=VWLB_Helpers::table('broadcast_guests');$wpdb->query($wpdb->prepare(\"UPDATE $guests SET status='expired',updated_at=%s WHERE status IN ('invited','accepted') AND expires_at<=%s\",VWLB_Helpers::now(),VWLB_Helpers::now()));"
new="$guests=VWLB_Helpers::table('broadcast_guests');$expired=$wpdb->get_results($wpdb->prepare(\"SELECT * FROM $guests WHERE status IN ('invited','accepted') AND expires_at<=%s ORDER BY id ASC LIMIT 500\",VWLB_Helpers::now()),ARRAY_A);foreach($expired as $guest){$changed=$wpdb->update($guests,array('status'=>'expired','version'=>(int)$guest['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$guest['id'],'version'=>$guest['version'],'status'=>$guest['status']));if(1===$changed){VWLB_Helpers::audit('broadcast_guest',$guest['id'],'expire',$guest['status'],'expired','delegation_ttl',array('live_event_id'=>$guest['live_event_id'],'guest_user_id'=>$guest['user_id']));VWLB_Helpers::outbox('BroadcastGuestExpired','live',$guest['live_event_id'],array('guest_user_id'=>(int)$guest['user_id'],'guest_public_id'=>$guest['public_id']));}}"
if new not in s:
    if old not in s: raise SystemExit('R18 guest cleanup anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R18 — automatic guest expiry is versioned, audited and emitted as a lifecycle fact.\nneed "BroadcastGuestExpired" "$P/includes/class-vwlb-future-intelligence.php" r18-guest-expired-event\nneed "delegation_ttl" "$P/includes/class-vwlb-future-intelligence.php" r18-guest-expiry-audit\n'''
if 'r18-guest-expired-event' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R18 — DEFECT FIXED\nScheduled cleanup expired guest/co-host delegations with one bulk SQL update that neither advanced optimistic versions nor emitted audit/outbox evidence. Stale studio views and downstream realtime/provider bridges could miss the revocation boundary. Expiry is now per-row CAS/versioned, audited and emits `BroadcastGuestExpired`.\n\n'''
if '## R18 ' not in ls: LEDGER.write_text(ls+entry)
print('R18 correction prepared')
