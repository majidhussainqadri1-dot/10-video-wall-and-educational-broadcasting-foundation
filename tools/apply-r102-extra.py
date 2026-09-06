#!/usr/bin/env python3
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
t=p.read_text()
old="$rights=$wpdb->get_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);\n\t\treturn array('videos'=>$videos,'live'=>$live,'jobs'=>$jobs,'copyright'=>$rights,'insights'=>self::creator_insights(30),'comments'=>apply_filters('vwlb_creator_comment_projection',array(),$uid),'canonical_owner'=>'File 10','comments_owner'=>'File 21/shared interaction contract');"
new="$rights=$wpdb->get_results($wpdb->prepare('SELECT public_id,target_type,target_id,status,rights_basis,decision_reason,updated_at FROM '.VWLB_Helpers::table('takedowns').' WHERE claimant_id=%d ORDER BY id DESC LIMIT 100',$uid),ARRAY_A);foreach($rights as &$case){$entity='video'===($case['target_type']??'')?'videos':'live_events';$target=VWLB_Repository::find($entity,(int)$case['target_id'],true);$case['target_public_id']=$target['public_id']??'';unset($case['target_id']);}unset($case);\n\t\treturn array('videos'=>$videos,'live'=>$live,'jobs'=>$jobs,'copyright'=>$rights,'insights'=>self::creator_insights(30),'comments'=>apply_filters('vwlb_creator_comment_projection',array(),$uid),'canonical_owner'=>'File 10','comments_owner'=>'File 21/shared interaction contract');"
if t.count(old)!=1: raise SystemExit('creator-studio rights projection block not found')
p.write_text(t.replace(old,new,1))
print('R102 creator-studio native target ID redacted')
