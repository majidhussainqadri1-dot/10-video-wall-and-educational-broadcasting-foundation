<?php
defined('ABSPATH')||exit;
get_header();
echo '<div id="primary" class="content-area"><div class="site-main">';
if(!empty($GLOBALS['vwlb_route_unavailable']))echo '<section class="vwlb-empty" role="alert"><h2>'.esc_html__('Video service temporarily unavailable',VWLB_TEXT_DOMAIN).'</h2><p>'.esc_html__('This media page could not be read safely. Please retry later.',VWLB_TEXT_DOMAIN).'</p></section>';
elseif(get_query_var('vwlb_video_id'))echo do_shortcode('[vwlb_video][vwlb_future_video_tools]');
elseif(get_query_var('vwlb_live_id'))echo do_shortcode('[vwlb_live][vwlb_future_live_tools]');
elseif(get_query_var('vwlb_channel_slug'))echo do_shortcode('[vwlb_channel]');
elseif(get_query_var('vwlb_podcast_id'))echo do_shortcode('[vwlb_podcast]');
elseif('studio_video'===get_query_var('vwlb_route'))echo do_shortcode('[vwlb_studio_video]');
elseif('studio_live'===get_query_var('vwlb_route'))echo do_shortcode('[vwlb_studio_live][vwlb_future_production_studio]');
else echo do_shortcode('[vwlb_wall]');
echo '</div></div>';
get_footer();
