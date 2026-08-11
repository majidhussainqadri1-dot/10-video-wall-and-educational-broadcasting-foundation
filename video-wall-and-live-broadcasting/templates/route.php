<?php
defined('ABSPATH')||exit;
get_header();
echo '<div id="primary" class="content-area"><div class="site-main">';
if(get_query_var('vwlb_video_id'))echo do_shortcode('[vwlb_video]');
elseif(get_query_var('vwlb_live_id'))echo do_shortcode('[vwlb_live]');
elseif(get_query_var('vwlb_channel_slug'))echo do_shortcode('[vwlb_channel]');
elseif(get_query_var('vwlb_podcast_id'))echo do_shortcode('[vwlb_podcast]');
elseif('studio_video'===get_query_var('vwlb_route'))echo do_shortcode('[vwlb_studio_video]');
elseif('studio_live'===get_query_var('vwlb_route'))echo do_shortcode('[vwlb_studio_live]');
else echo do_shortcode('[vwlb_wall]');
echo '</div></div>';
get_footer();
