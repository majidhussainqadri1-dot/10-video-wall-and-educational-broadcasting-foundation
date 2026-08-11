from pathlib import Path

seo=Path('video-wall-and-live-broadcasting/includes/class-vwlb-seo.php')
privacy=Path('video-wall-and-live-broadcasting/includes/class-vwlb-privacy.php')
frontend=Path('video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php')
reg=Path('tests/fresh-40-review-contracts.sh')

seo.write_text(r'''<?php
/** Public-only VideoObject/BroadcastEvent structured data. */
defined( 'ABSPATH' ) || exit;
final class VWLB_SEO {
	public function output(){
		if(get_query_var('vwlb_video_id')){$video=VWLB_Repository::video_bundle(get_query_var('vwlb_video_id'));if(!$video||'published'!==($video['status']??'')||'public'!==($video['visibility']??''))return;$dto=VWLB_Repository::public_video_dto($video);if(!$dto)return;$data=array('@context'=>'https://schema.org','@type'=>'VideoObject','name'=>$dto['title'],'description'=>wp_strip_all_tags($dto['description']),'uploadDate'=>$dto['published_at'],'duration'=>'PT'.(int)$dto['duration_seconds'].'S','url'=>$dto['url']);if(!empty($dto['thumbnail_url']))$data['thumbnailUrl']=array($dto['thumbnail_url']);if($video['provider']!=='local'&&!empty($video['embed_url']))$data['embedUrl']=$video['embed_url'];$public_content=apply_filters('vwlb_public_seo_content_url','',$video);if($public_content)$data['contentUrl']=esc_url_raw($public_content,array('https'));$this->script($data);}
		elseif(get_query_var('vwlb_live_id')){$raw=VWLB_Repository::find('live_events',get_query_var('vwlb_live_id'));if(!$raw||'public'!==($raw['visibility']??'')||!in_array($raw['status']??'',array('scheduled','live','ended','replay_published'),true))return;$event=VWLB_Live::state($raw['public_id']);if(is_wp_error($event))return;$status='live'===$event['status']?'https://schema.org/EventInProgress':('ended'===$event['status']||'replay_published'===$event['status']?'https://schema.org/EventCompleted':'https://schema.org/EventScheduled');$data=array('@context'=>'https://schema.org','@type'=>'BroadcastEvent','name'=>$event['title'],'description'=>wp_strip_all_tags($event['description']),'startDate'=>$event['scheduled_start'],'endDate'=>$event['scheduled_end'],'eventStatus'=>$status,'url'=>$event['url']);if('live'===$event['status'])$data['isLiveBroadcast']=true;$this->script($data);}
	}
	private function script($data){echo '<script type="application/ld+json">'.wp_json_encode(array_filter($data),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>' . "\n";}
}
''')

pt=privacy.read_text()
old="\tpublic function private_headers(){$private_slugs=array('video-history','studio-video','studio-live');if(is_page(array_values((array)get_option('vwlb_page_map',array())))&&(is_user_logged_in()||is_page($private_slugs)))VWLB_Helpers::no_cache_private();if(get_query_var('vwlb_video_id')||get_query_var('vwlb_live_id')||get_query_var('vwlb_podcast_id'))VWLB_Helpers::no_cache_private();}\n"
new=r'''	public function private_headers(){
		$private_slugs=array('video-history','studio-video','studio-live');if(is_page(array_values((array)get_option('vwlb_page_map',array())))&&(is_user_logged_in()||is_page($private_slugs)))VWLB_Helpers::no_cache_private();
		if(get_query_var('vwlb_video_id')){$v=VWLB_Repository::find('videos',get_query_var('vwlb_video_id'));if(!$v||'published'!==($v['status']??'')||'public'!==($v['visibility']??''))VWLB_Helpers::no_cache_private();}
		if(get_query_var('vwlb_live_id')){$e=VWLB_Repository::find('live_events',get_query_var('vwlb_live_id'));if(!$e||'public'!==($e['visibility']??'')||!in_array($e['status']??'',array('scheduled','live','ended','replay_published'),true))VWLB_Helpers::no_cache_private();}
	}
'''
if old in pt: pt=pt.replace(old,new,1)
elif new not in pt: raise SystemExit('R34 private headers pattern missing')
privacy.write_text(pt)

ft=frontend.read_text()
ft=ft.replace("\t\tVWLB_Helpers::no_cache_private();$this->enqueue();$id=get_query_var('vwlb_video_id');", "\t\t$this->enqueue();$id=get_query_var('vwlb_video_id');",1)
ft=ft.replace("\t\tVWLB_Helpers::no_cache_private();$this->enqueue();$id=get_query_var('vwlb_live_id');", "\t\t$this->enqueue();$id=get_query_var('vwlb_live_id');",1)
old_state="\tprivate function state($class,$title,$message){return '<section class=\"vwlb-state vwlb-state-'.esc_attr($class).'\" role=\"status\"><h2>'.esc_html($title).'</h2><p>'.esc_html($message).'</p><p><a href=\"'.esc_url(home_url('/')).'\">'.esc_html__('Home',VWLB_TEXT_DOMAIN).'</a></p></section>';}"
new_state="\tprivate function state($class,$title,$message,$status=0){if(!$status&&'restricted'===$class)$status=404;if($status)status_header((int)$status);return '<section class=\"vwlb-state vwlb-state-'.esc_attr($class).'\" role=\"status\"><h2>'.esc_html($title).'</h2><p>'.esc_html($message).'</p><p><a href=\"'.esc_url(home_url('/')).'\">'.esc_html__('Home',VWLB_TEXT_DOMAIN).'</a></p></section>';}"
if old_state in ft: ft=ft.replace(old_state,new_state,1)
elif new_state not in ft: raise SystemExit('R34 state pattern missing')
frontend.write_text(ft)

r=reg.read_text();marker="""# R34 — public eligible media may be indexed; private/restricted media is noindex and never emitted into structured data.\nneed \"'published'!==(\$video['status']??'')\" \"$P/includes/class-vwlb-seo.php\" r34-public-video-only\nneed \"vwlb_public_seo_content_url\" \"$P/includes/class-vwlb-seo.php\" r34-no-raw-source-url\nneed \"'public'!==(\$raw['visibility']??'')\" \"$P/includes/class-vwlb-seo.php\" r34-public-live-only\nneed \"if(!\$v||'published'!==(\$v['status']??'')\" \"$P/includes/class-vwlb-privacy.php\" r34-conditional-noindex\nneed \"status_header((int)\$status)\" \"$P/includes/class-vwlb-frontend.php\" r34-http-state\n"""
if '# R34 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
else:
    start=r.index('# R34 —'); end=r.index("printf '%s\\n' 'fresh 40-review regression contracts PASS'",start); r=r[:start]+marker+r[end:]
reg.write_text(r)
