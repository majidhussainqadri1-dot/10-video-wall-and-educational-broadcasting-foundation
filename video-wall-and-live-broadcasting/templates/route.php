<?php
defined('ABSPATH')||exit;
get_header();
echo '<div id="primary" class="content-area"><div class="site-main">';
if(get_query_var('vwlb_video_id'))echo do_shortcode('[vwlb_video]');
elseif(get_query_var('vwlb_live_id'))echo do_shortcode('[vwlb_live]');
echo '</div></div>';
get_footer();
