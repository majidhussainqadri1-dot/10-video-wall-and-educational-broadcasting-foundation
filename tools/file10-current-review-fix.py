from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-rest.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="if(false===$inserted){return $this->response(array('accepted'=>true,'duplicate'=>true));}"
new="if(false===$inserted){$existing=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.VWLB_Helpers::table('webhooks').' WHERE provider=%s AND event_id=%s LIMIT 1',$provider->id(),$event_id));if($existing)return $this->response(array('accepted'=>true,'duplicate'=>true));return VWLB_Helpers::error('vwlb_webhook_persist_failed',__('Verified webhook could not be persisted for durable processing.',VWLB_TEXT_DOMAIN),503);}"
if old not in t:raise SystemExit('R24 webhook duplicate pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R24 — verified webhook DB failures are never acknowledged as harmless duplicates.\nneed \"vwlb_webhook_persist_failed\" \"$P/includes/class-vwlb-rest.php\" r24-db-failure\nneed \"WHERE provider=%s AND event_id=%s\" \"$P/includes/class-vwlb-rest.php\" r24-duplicate-check\nneed \"vwlb_webhook_replay_window\" \"$P/includes/class-vwlb-rest.php\" r24-replay-window\n"""
if '# R24 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
