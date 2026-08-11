from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-providers.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="\tpublic function create_live($event){return array('provider_event_ref'=>'','state'=>'configured');}\n"
new="\tpublic function create_live($event){return VWLB_Helpers::error('vwlb_provider_live_unavailable',__('The selected provider has no configured live-event adapter.',VWLB_TEXT_DOMAIN),503);}\n"
if old not in t:raise SystemExit('R29 base live pattern missing')
t=t.replace(old,new,1)
old="\tpublic function issue_ingest($event){$endpoint=apply_filters('vwlb_local_live_ingest_endpoint','',$event);if(!$endpoint)return parent::issue_ingest($event);return array('ingest_url'=>esc_url_raw($endpoint),'provider_ref'=>'local_'.$event['public_id']);}\n"
new="\tpublic function issue_ingest($event){$endpoint=apply_filters('vwlb_local_live_ingest_endpoint','',$event);if(!$endpoint)return parent::issue_ingest($event);$endpoint=VWLB_Helpers::remote_url($endpoint);return $endpoint?array('ingest_url'=>$endpoint,'provider_ref'=>'local_'.$event['public_id']):VWLB_Helpers::error('vwlb_provider_ingest_invalid',__('Configured ingest endpoint is not a safe HTTPS remote URL.',VWLB_TEXT_DOMAIN),503);}\n"
if old not in t:raise SystemExit('R29 local ingest pattern missing')
t=t.replace(old,new,1)
old="\tpublic function create_live($event){$result=apply_filters('vwlb_custom_create_live',null,$event);return is_array($result)?$result:array('provider_event_ref'=>'custom_'.$event['public_id'],'state'=>'configured');}\n"
new="\tpublic function create_live($event){$result=apply_filters('vwlb_custom_create_live',null,$event);if(is_wp_error($result)||is_array($result))return $result;return parent::create_live($event);}\n"
if old not in t:raise SystemExit('R29 custom live pattern missing')
t=t.replace(old,new,1)
old="\tpublic function issue_ingest($event){$base=defined('VWLB_CUSTOM_INGEST_BASE')?VWLB_CUSTOM_INGEST_BASE:'';$base=VWLB_Helpers::safe_url($base);if(!$base)return parent::issue_ingest($event);return array('ingest_url'=>trailingslashit($base).rawurlencode($event['public_id']),'stream_key'=>VWLB_Providers::stream_secret(),'provider_ref'=>'custom_'.$event['public_id']);}\n"
new="\tpublic function issue_ingest($event){$base=defined('VWLB_CUSTOM_INGEST_BASE')?VWLB_CUSTOM_INGEST_BASE:'';$base=VWLB_Helpers::remote_url($base);if(!$base)return parent::issue_ingest($event);return array('ingest_url'=>trailingslashit($base).rawurlencode($event['public_id']),'stream_key'=>VWLB_Providers::stream_secret(),'provider_ref'=>'custom_'.$event['public_id']);}\n"
if old not in t:raise SystemExit('R29 custom ingest pattern missing')
t=t.replace(old,new,1)
old="\tpublic function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::safe_url($state['playback_url']??($object['source_url']??''));return $url?array('type'=>!empty($state['iframe'])?'iframe':'hls','url'=>$url,'captions'=>$object['captions']??array(),'autoplay'=>false):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);}\n"
new="\tpublic function playback($object,$viewer){$state=VWLB_Helpers::json($object['provider_state_json']??'{}');$url=VWLB_Helpers::remote_url($state['playback_url']??($object['source_url']??''));return $url?array('type'=>!empty($state['iframe'])?'iframe':'hls','url'=>$url,'captions'=>$object['captions']??array(),'autoplay'=>false):VWLB_Helpers::error('vwlb_playback_unavailable',__('Playback is not ready.',VWLB_TEXT_DOMAIN),503);}\n"
if old not in t:raise SystemExit('R29 custom playback pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R29 — provider live/create/ingest paths fail closed without a real adapter and custom/local remote endpoints use SSRF-aware HTTPS validation.\nneed \"vwlb_provider_live_unavailable\" \"$P/includes/class-vwlb-providers.php\" r29-live-fail-closed\nneed \"Configured ingest endpoint is not a safe HTTPS remote URL\" \"$P/includes/class-vwlb-providers.php\" r29-local-ingest\nneed \"return parent::create_live(\$event)\" \"$P/includes/class-vwlb-providers.php\" r29-custom-live\nneed \"VWLB_Helpers::remote_url(\$base)\" \"$P/includes/class-vwlb-providers.php\" r29-custom-ingest\nneed \"VWLB_Helpers::remote_url(\$state['playback_url']\" \"$P/includes/class-vwlb-providers.php\" r29-custom-playback\n"""
if '# R29 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
