from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-rest.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="public function get_live(WP_REST_Request $r){$state=VWLB_Live::state($r['id']);if(!is_wp_error($state)){$event=VWLB_Repository::find('live_events',$r['id']);$state['experience']=VWLB_Extensions::live_extras($event);$state['media_tracks']=$event?VWLB_Future_Intelligence::published_tracks('live',$event['id']):array();}return $this->response($state);}"
new="public function get_live(WP_REST_Request $r){$state=VWLB_Live::state($r['id']);if(!is_wp_error($state)){$event=VWLB_Repository::find('live_events',$r['id']);$state['experience']=VWLB_Extensions::live_extras($event);$state['media_tracks']=$event?VWLB_Future_Intelligence::published_tracks('live',$event['id']):array();}$response=$this->response($state);if(!is_wp_error($response))$response->header('Cache-Control','private, no-store');return $response;}"
if new not in s:
    if old not in s: raise SystemExit('R17 live response anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R17 — live viewer state containing delivery refs is private/no-store.\nneed "media_tracks" "$P/includes/class-vwlb-rest.php" r17-live-track-state\nneed "Cache-Control','private, no-store" "$P/includes/class-vwlb-rest.php" r17-live-no-store\n'''
if 'r17-live-track-state' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R17 — DEFECT FIXED\nLive viewer state now includes adapter-resolved auxiliary-track references, which may be viewer/session-specific. The endpoint lacked an explicit no-store policy, so signed delivery data could be reused as shared cache content. Live state now returns `Cache-Control: private, no-store`.\n\n'''
if '## R17 ' not in ls: LEDGER.write_text(ls+entry)
print('R17 correction prepared')
