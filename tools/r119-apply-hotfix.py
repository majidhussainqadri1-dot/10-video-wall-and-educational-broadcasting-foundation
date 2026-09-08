#!/usr/bin/env python3
from pathlib import Path
import runpy
p=Path(__file__).with_name('r119-apply.py')
s=p.read_text()
old="\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']??'');if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
new="\"$d['series_id']=$this->series_id($d['series_public_id']);if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\",\n\"$d['series_id']=$this->series_id($d['series_public_id']);if(is_wp_error($d['series_id']))return $d['series_id'];if(!$d['series_id'])return VWLB_Helpers::error('vwlb_not_found',__('Podcast series not found.',VWLB_TEXT_DOMAIN),404);\","
if old not in s:
    raise SystemExit('R119 applicator hotfix target not found')
p.write_text(s.replace(old,new,1))
runpy.run_path(str(p),run_name='__main__')

# F7 follow-through: upload_session() now returns WP_Error on DB failure, so all
# resumable mutation callers must propagate that error before array access/auth.
ext=Path(__file__).resolve().parents[1]/'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
t=ext.read_text()
old_call="$session = self::upload_session( $public_id );\n\t\t$auth = self::authorize_upload_session( $session, $token );"
new_call="$session = self::upload_session( $public_id );\n\t\tif ( is_wp_error( $session ) ) return $session;\n\t\t$auth = self::authorize_upload_session( $session, $token );"
count=t.count(old_call)
if count != 2:
    raise SystemExit(f'F7-upload-session-callers: expected 2 matches, found {count}')
t=t.replace(old_call,new_call)
ext.write_text(t)
print('R119 upload-session caller propagation applied')
