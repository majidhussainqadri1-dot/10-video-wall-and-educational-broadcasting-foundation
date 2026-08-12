from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'video-wall-and-live-broadcasting/includes/class-vwlb-future-adapters.php'
TEST=ROOT/'tests/fresh-20-review-2-contracts.sh'
LEDGER=ROOT/'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'
s=F.read_text()
old="if(is_wp_error($result)){$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>$result->get_error_code(),'updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version));return $result;}"
new="if(is_wp_error($result)){$failed=$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>$result->get_error_code(),'updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version,'status'=>'transitioning'));if(1!==$failed)return VWLB_Helpers::error('vwlb_simulcast_reconcile_required',__('The provider failed and local failure state could not be finalized; reconciliation is required.',VWLB_TEXT_DOMAIN),503,array('provider_code'=>$result->get_error_code()));return $result;}"
if new not in s:
    if old not in s: raise SystemExit('R06 provider-error anchor missing')
    s=s.replace(old,new,1)
old2="if(!is_array($result)||empty($result['accepted'])){$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>'unavailable','updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version));return VWLB_Helpers::error('vwlb_simulcast_adapter_unavailable',__('Simulcast provider adapter is unavailable.',VWLB_TEXT_DOMAIN),503);}"
new2="if(!is_array($result)||empty($result['accepted'])){$failed=$wpdb->update($table,array('status'=>'failed','version'=>$reserved_version+1,'last_state_json'=>VWLB_Helpers::json_encode(array('status'=>'failed','provider_code'=>'unavailable','updated_at'=>gmdate('c'))),'updated_at'=>VWLB_Helpers::now()),array('id'=>$target['id'],'version'=>$reserved_version,'status'=>'transitioning'));if(1!==$failed)return VWLB_Helpers::error('vwlb_simulcast_reconcile_required',__('The provider was unavailable and local failure state could not be finalized; reconciliation is required.',VWLB_TEXT_DOMAIN),503);return VWLB_Helpers::error('vwlb_simulcast_adapter_unavailable',__('Simulcast provider adapter is unavailable.',VWLB_TEXT_DOMAIN),503);}"
if new2 not in s:
    if old2 not in s: raise SystemExit('R06 unavailable anchor missing')
    s=s.replace(old2,new2,1)
F.write_text(s)
ts=TEST.read_text()
checks='''\n# R06 — provider failure paths must durably leave simulcast transitioning state or demand reconciliation.\nneed "provider failed and local failure state could not be finalized" "$P/includes/class-vwlb-future-adapters.php" r06-provider-failure-persist\nneed "provider was unavailable and local failure state could not be finalized" "$P/includes/class-vwlb-future-adapters.php" r06-unavailable-persist\nneed "'status'=>'transitioning'" "$P/includes/class-vwlb-future-adapters.php" r06-failure-lease-bound\n'''
if 'r06-unavailable-persist' not in ts: TEST.write_text(ts+checks)
ls=LEDGER.read_text()
entry='''## R06 — DEFECT FIXED\nThe simulcast transition reserved local state as `transitioning`, but both provider-error branches ignored whether the subsequent local `failed` state write succeeded. A database/CAS race could therefore strand the target in `transitioning` while the caller saw only the provider error. Failure-state writes are now version/status-bound and verified; if File 10 cannot persist the provider failure truth, the API returns an explicit reconciliation-required error instead of masking local divergence.\n\n'''
if '## R06 ' not in ls: LEDGER.write_text(ls+entry)
print('R06 correction prepared')
