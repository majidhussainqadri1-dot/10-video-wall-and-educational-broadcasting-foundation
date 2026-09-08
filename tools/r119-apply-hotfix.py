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
