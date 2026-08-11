from pathlib import Path
import re
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-videos.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="$status=VWLB_Security::can(VWLB_Contracts::CAP_PUBLISH,$video,'review_caption')?'published':'review';"
new="$source=VWLB_Helpers::enum($data['source']??'manual',array('manual','imported','machine_draft'),'manual');$can_review=VWLB_Security::can(VWLB_Contracts::CAP_REVIEW,$video,'review_caption');$status=('machine_draft'!==$source&&$can_review)?'published':'review';"
if old not in t:raise SystemExit('R18 caption review pattern missing')
t=t.replace(old,new,1)
old2="'source'=>VWLB_Helpers::enum($data['source']??'manual',array('manual','imported','machine_draft'),'manual')"
if old2 not in t:raise SystemExit('R18 source field pattern missing')
t=t.replace(old2,"'source'=>$source",1)
old3=");$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('caption',$id,'create','',$status);return array('id'=>$id,'status'=>$status,'version'=>$version);"
new3=");if(!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Caption could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;VWLB_Helpers::audit('caption',$id,'create','',$status,'',array('source'=>$source));return array('id'=>$id,'status'=>$status,'version'=>$version);"
if old3 not in t:raise SystemExit('R18 insert check pattern missing')
t=t.replace(old3,new3,1)
p.write_text(t)
r=reg.read_text();marker="""# R18 — generated captions require human review and caption persistence failure is not reported as success.\nneed \"'machine_draft'!==\$source&&\$can_review\" \"$P/includes/class-vwlb-videos.php\" r18-human-review\nneed \"VWLB_Contracts::CAP_REVIEW\" \"$P/includes/class-vwlb-videos.php\" r18-review-cap\nneed \"Caption could not be saved\" \"$P/includes/class-vwlb-videos.php\" r18-db-check\n"""
if '# R18 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
