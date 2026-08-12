from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
FI=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
REST=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=FI.read_text()
old="public static function invite_guest( $live_id, $user_id, $role='guest', $scope=array(), $ttl=7200 )"
new="public static function invite_guest( $live_id, $user_id, $role='guest', $scope=array(), $ttl=7200, $expected_version=0 )"
if new not in s:
    if old not in s: raise SystemExit('R19 guest signature anchor missing')
    s=s.replace(old,new,1)
old2="if($existing){$row['version']=(int)$existing['version']+1;$changed=$wpdb->update($table,$row,array('id'=>$existing['id'],'version'=>$existing['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Guest delegation changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$existing['id'];}"
new2="if($existing){$expected_version=absint($expected_version);if(!$expected_version||$expected_version!==(int)$existing['version'])return VWLB_Helpers::error('vwlb_version_conflict',__('Guest delegation changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=$expected_version+1;$changed=$wpdb->update($table,$row,array('id'=>$existing['id'],'version'=>$expected_version));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Guest delegation changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$existing['id'];}"
if new2 not in s:
    if old2 not in s: raise SystemExit('R19 guest update anchor missing')
    s=s.replace(old2,new2,1)
FI.write_text(s)
r=REST.read_text()
old3="VWLB_Future_Intelligence::invite_guest($r['id'],$d['user_id']??0,$d['role']??'guest',$d['scope']??array(),$d['ttl']??7200)"
new3="VWLB_Future_Intelligence::invite_guest($r['id'],$d['user_id']??0,$d['role']??'guest',$d['scope']??array(),$d['ttl']??7200,$d['version']??0)"
if new3 not in r:
    if old3 not in r: raise SystemExit('R19 guest REST anchor missing')
    r=r.replace(old3,new3,1)
REST.write_text(r)
ts=TEST.read_text()
checks='''\n# R19 — existing guest delegation updates require the caller-observed version.\nneed "Guest delegation changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r19-guest-client-version\n'''
if 'r19-guest-client-version' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R19 — DEFECT FIXED\nRe-inviting or changing an existing guest/co-host delegation refreshed the latest database row and then applied the caller payload without proving which version the studio had reviewed. A stale studio could therefore overwrite a newer delegation change or revocation intent. Existing delegation changes now require the caller-observed version, use it as the CAS token, and the REST contract forwards that version explicitly.\n\n'''
if '## R19 ' not in ls: LEDGER.write_text(ls+entry)
print('R19 correction prepared')
