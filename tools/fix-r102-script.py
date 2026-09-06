#!/usr/bin/env python3
from pathlib import Path
import re
p=Path(__file__).with_name('apply-r102.py')
text=p.read_text()
caption=re.compile(r"# Caption creation response must be opaque\.\nrep\('video-wall-and-live-broadcasting/includes/class-vwlb-videos\.php',\n.*?\n\n# Extended REST foreign-key normalization\.",re.S)
replacement="""# Caption creation response must be opaque.
rep('video-wall-and-live-broadcasting/includes/class-vwlb-videos.php',
"return array('id'=>$id,'status'=>$status,'version'=>$version);",
"$public=(string)$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.VWLB_Helpers::table('captions').' WHERE id=%d',$id));return array('public_id'=>$public,'status'=>$status,'version'=>$version);")

# Extended REST foreign-key normalization."""
text,n=caption.subn(replacement,text,count=1)
if n!=1: raise SystemExit('caption patch block not found')
pod=re.compile(r"rep\(pod,\"return array\('id'=>\$id,'public_id'=>\$public,'slug'=>\$slug,'status'=>'draft','version'=>1\);\",\"return array\('public_id'=>\$public,'slug'=>\$slug,'status'=>'draft','version'=>1\);\"\)\nrep\(pod,\"return array\('id'=>\$id,'public_id'=>\$public,'slug'=>\$slug,'status'=>'draft','version'=>1\);\",\"return array\('public_id'=>\$public,'slug'=>\$slug,'status'=>'draft','version'=>1\);\"\)")
text,n=pod.subn("replace_all(pod,\"return array('id'=>$id,'public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);\",\"return array('public_id'=>$public,'slug'=>$slug,'status'=>'draft','version'=>1);\",2)",text,count=1)
if n!=1: raise SystemExit('podcast duplicate-return patch block not found')
old="""rep('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php',
"($body['target_id']??0)",
"($body['target_public_id']??'')")"""
new="""rep('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php',
"$body['target_id']??0",
"$body['target_public_id']??''")"""
if old not in text: raise SystemExit('extensions metric matcher block not found')
text=text.replace(old,new,1)
p.write_text(text)
print('R102 patch script repaired')
