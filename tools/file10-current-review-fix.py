from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-media.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="$result=VWLB_Repository::update_versioned('media_assets',$asset['id'],$expected_version,array('source_url'=>$url,'attachment_id'=>$attachment,'status'=>'uploaded','scan_status'=>'pending','checksum'=>VWLB_Helpers::text($data['checksum']??$asset['checksum'],128),'duration_seconds'=>VWLB_Helpers::duration_seconds($data['duration']??0)),array('%s','%d','%s','%s','%s','%d'));"
new="$checksum=strtolower(VWLB_Helpers::text($data['checksum']??$asset['checksum'],128));if($checksum&&!preg_match('/^[a-f0-9]{64}$/',$checksum))return VWLB_Helpers::error('vwlb_checksum_invalid',__('Checksum must be SHA-256.',VWLB_TEXT_DOMAIN),422);$result=VWLB_Repository::update_versioned('media_assets',$asset['id'],$expected_version,array('source_url'=>$url,'attachment_id'=>$attachment,'status'=>'uploaded','scan_status'=>'pending','checksum'=>$checksum,'duration_seconds'=>VWLB_Helpers::duration_seconds($data['duration']??0)),array('%s','%d','%s','%s','%s','%d'));"
if old not in t:raise SystemExit('R20 checksum pattern missing')
t=t.replace(old,new,1)
old="self::enqueue($asset['id'],'verify_and_process',array('required_derivatives'=>array('hls','mp4_high','mp4_low','audio_only','poster','storyboard','transcript_draft')));\n\t\tVWLB_Helpers::audit"
new="$job=self::enqueue($asset['id'],'verify_and_process',array('required_derivatives'=>array('hls','mp4_high','mp4_low','audio_only','poster','storyboard','transcript_draft')));if(!$job)return VWLB_Helpers::error('vwlb_processing_queue_failed',__('Media uploaded but processing could not be queued; retry completion after reconciliation.',VWLB_TEXT_DOMAIN),503);\n\t\tVWLB_Helpers::audit"
if old not in t:raise SystemExit('R20 queue pattern missing')
t=t.replace(old,new,1)
old="$wpdb->insert(VWLB_Helpers::table('processing_jobs'),array('public_id'=>VWLB_Helpers::public_id('job')"
new="$saved=$wpdb->insert(VWLB_Helpers::table('processing_jobs'),array('public_id'=>VWLB_Helpers::public_id('job')"
if old not in t:raise SystemExit('R20 enqueue pattern missing')
t=t.replace(old,new,1)
old="'created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));return (int)$wpdb->insert_id;\n\t}\n\tpublic static function verify_magic"
new="'created_at'=>VWLB_Helpers::now(),'updated_at'=>VWLB_Helpers::now()));return $saved?(int)$wpdb->insert_id:0;\n\t}\n\tpublic static function verify_magic"
if old not in t:raise SystemExit('R20 enqueue return pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R20 — media completion validates checksums and cannot report a complete upload when processing enqueue fails.\nneed \"vwlb_processing_queue_failed\" \"$P/includes/class-vwlb-media.php\" r20-queue-fail\nneed \"Checksum must be SHA-256\" \"$P/includes/class-vwlb-media.php\" r20-checksum\nneed \"return \$saved?(int)\$wpdb->insert_id:0\" \"$P/includes/class-vwlb-media.php\" r20-enqueue-check\n"""
if '# R20 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
