<?php
/** Progressive UI for the 24 Future Video & Broadcasting Intelligence capabilities. */
defined( 'ABSPATH' ) || exit;

final class VWLB_Future_Frontend {
	public function register(){
		add_action('wp_enqueue_scripts',array($this,'assets'));
		add_shortcode('vwlb_future_video_tools',array($this,'video_tools'));
		add_shortcode('vwlb_future_live_tools',array($this,'live_tools'));
		add_shortcode('vwlb_future_production_studio',array($this,'production_studio'));
	}
	public function assets(){
		wp_register_style('vwlb-future',VWLB_URL.'assets/css/vwlb-future.css',array('vwlb'),VWLB_VERSION);
		wp_register_script('vwlb-future',VWLB_URL.'assets/js/vwlb-future.js',array('vwlb'),VWLB_VERSION,true);
	}
	private function enqueue(){wp_enqueue_style('vwlb');wp_enqueue_script('vwlb');wp_enqueue_style('vwlb-future');wp_enqueue_script('vwlb-future');}
	private function video_id(){ $id=get_query_var('vwlb_video_id'); return $id?:sanitize_text_field(wp_unslash($_GET['video']??'')); }
	private function live_id(){ $id=get_query_var('vwlb_live_id'); return $id?:sanitize_text_field(wp_unslash($_GET['live']??'')); }
	public function video_tools(){
		$this->enqueue();$id=$this->video_id(); if(!$id)return '';$video=VWLB_Repository::find('videos',$id);if(!$video||!VWLB_Security::can_view($video))return '';
		$annotations=VWLB_Future_Intelligence::annotations($id,false);$items=is_wp_error($annotations)?array():($annotations['items']??array());
		ob_start();?><section class="vwlb-future-tools" data-vwlb-future-video="<?php echo esc_attr($video['public_id']);?>" aria-labelledby="vwlb-future-video-tools-title">
		<h2 id="vwlb-future-video-tools-title"><?php esc_html_e('Video knowledge tools',VWLB_TEXT_DOMAIN);?></h2>
		<form data-vwlb-search-inside><label><?php esc_html_e('Search inside this video',VWLB_TEXT_DOMAIN);?><input type="search" name="q" minlength="2" maxlength="120" autocomplete="off"></label><button type="submit"><?php esc_html_e('Search',VWLB_TEXT_DOMAIN);?></button></form>
		<div data-vwlb-search-results role="status" aria-live="polite"></div>
		<?php if($items):?><ol class="vwlb-annotation-list"><?php foreach($items as $a):?><li data-kind="<?php echo esc_attr($a['kind']);?>"><button type="button" data-vwlb-future-seek="<?php echo esc_attr((int)$a['start_ms']/1000);?>"><?php echo esc_html(gmdate('H:i:s',(int)floor($a['start_ms']/1000)));?></button> <strong><?php echo esc_html($a['title']?:ucwords(str_replace('_',' ',$a['kind'])));?></strong><?php if($a['body']):?><span> — <?php echo esc_html($a['body']);?></span><?php endif;?><?php if($a['source_owner']&&$a['source_ref']):?><small><?php echo esc_html($a['source_owner'].' · '.$a['source_ref']);?></small><?php endif;?></li><?php endforeach;?></ol><?php endif;?>
		</section><?php return ob_get_clean();
	}
	public function live_tools(){
		$this->enqueue();$id=$this->live_id();if(!$id)return '';$event=VWLB_Repository::find('live_events',$id);if(!$event||!VWLB_Security::can_view($event))return '';
		global $wpdb;$poll=$wpdb->get_row($wpdb->prepare("SELECT public_id FROM ".VWLB_Helpers::table('live_polls')." WHERE live_event_id=%d AND status='open' ORDER BY id DESC LIMIT 1",$event['id']),ARRAY_A);$dto=$poll?VWLB_Future_Intelligence::poll($poll['public_id'],false):null;
		ob_start();?><section class="vwlb-future-live" data-vwlb-future-live="<?php echo esc_attr($event['public_id']);?>" aria-labelledby="vwlb-future-live-title"><h2 id="vwlb-future-live-title"><?php esc_html_e('Live learning tools',VWLB_TEXT_DOMAIN);?></h2>
		<?php if($dto):?><form data-vwlb-live-poll="<?php echo esc_attr($dto['public_id']);?>"><fieldset><legend><?php echo esc_html($dto['question']);?></legend><?php foreach($dto['options'] as $o):?><label><input type="<?php echo esc_attr('multiple'===$dto['poll_type']?'checkbox':'radio');?>" name="poll_option" value="<?php echo esc_attr($o['public_id']);?>"> <?php echo esc_html($o['option_text']);?></label><?php endforeach;?><button type="submit"><?php esc_html_e('Submit answer',VWLB_TEXT_DOMAIN);?></button></fieldset><div class="vwlb-status" role="status" aria-live="polite"></div></form><?php else:?><p><?php esc_html_e('No live knowledge check is open right now.',VWLB_TEXT_DOMAIN);?></p><?php endif;?>
		</section><?php return ob_get_clean();
	}
	public function production_studio(){
		$this->enqueue();if(!VWLB_Security::can(VWLB_Contracts::CAP_BROADCAST,null,'future_production_studio'))return '';
		return '<section class="vwlb-future-studio" data-vwlb-production-studio><h2>'.esc_html__('Advanced Live Production',VWLB_TEXT_DOMAIN).'</h2><p>'.esc_html__('Multi-camera scenes, guest/co-host access, screen/slides sources, DVR/latency policy, SRT/WebRTC adapters, simulcast, redundant recording and health telemetry are available through the File 10 canonical REST contracts.',VWLB_TEXT_DOMAIN).'</p><div class="vwlb-status" role="status" aria-live="polite"></div></section>';
	}
}
