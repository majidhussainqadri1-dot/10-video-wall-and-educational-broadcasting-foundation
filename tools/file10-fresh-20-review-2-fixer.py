from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="if($id){$current=self::public_row('simulcast_targets',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);if('transitioning'===$current['status'])return VWLB_Helpers::error('vwlb_simulcast_busy',__('Simulcast target is changing state. Retry after reconciliation.',VWLB_TEXT_DOMAIN),409);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}"
new="if($id){$current=self::public_row('simulcast_targets',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_simulcast_missing',__('Simulcast target not found.',VWLB_TEXT_DOMAIN),404);if('transitioning'===$current['status'])return VWLB_Helpers::error('vwlb_simulcast_busy',__('Simulcast target is changing state. Retry after reconciliation.',VWLB_TEXT_DOMAIN),409);$expected_version=absint($data['version']??0);if(!$expected_version||$expected_version!==(int)$current['version'])return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=$expected_version+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>$expected_version));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Simulcast target changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}"
if new not in s:
    if old not in s: raise SystemExit('R05 simulcast anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R05 — simulcast target edits require the caller current version.\nneed "Simulcast target changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r05-target-client-version\nneed "expected_version=absint" "$P/includes/class-vwlb-future-intelligence.php" r05-target-version-parse\n'''
if 'r05-target-client-version' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R05 — DEFECT FIXED\nSimulcast target edits had the same stale-client overwrite class as production source/scene edits: the server refreshed the latest row and then applied the caller payload without proving the caller had edited that version. Existing target edits now require the submitted current version and use it as the conditional update token; stale/missing versions fail before mutation.\n\n'''
if '## R05 ' not in ls: LEDGER.write_text(ls+entry)
print('R05 correction prepared')
