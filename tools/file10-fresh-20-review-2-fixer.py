from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="if($current){$row['version']=(int)$current['version']+1;$changed=$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$current['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Consent record changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$current['id'];}"
new="if($current){$expected=absint($data['version']??0);if(!$expected||$expected!==(int)$current['version'])return VWLB_Helpers::error('vwlb_version_conflict',__('Consent record changed. Refresh and submit its current version.',VWLB_TEXT_DOMAIN),409);$row['version']=$expected+1;$changed=$wpdb->update($table,$row,array('id'=>$current['id'],'version'=>$expected));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Consent record changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$current['id'];}"
if new not in s:
    if old not in s: raise SystemExit('R11 consent CAS anchor missing')
    s=s.replace(old,new,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R11 — consent updates must carry the client-observed version.\nneed "Consent record changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r11-consent-client-version\n'''
if 'r11-consent-client-version' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R11 — DEFECT FIXED\nConsent-link updates locked the latest database row and used a server-side CAS, but did not require the caller to prove which version it had reviewed. A stale reviewer screen could therefore overwrite a newer consent decision. Existing consent records now require the submitted current version and reject stale/missing versions before mutation. The first R11 regression assertion expanded a shell variable under `set -u`; that QA-only defect was corrected inside R11 before the product correction was accepted.\n\n'''
if '## R11 ' not in ls: LEDGER.write_text(ls+entry)
print('R11 correction prepared')
