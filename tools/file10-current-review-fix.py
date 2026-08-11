from pathlib import Path
p=Path('video-wall-and-live-broadcasting/includes/class-vwlb-frontend.php');reg=Path('tests/fresh-40-review-contracts.sh');t=p.read_text()
old="<?php if(!empty($event['playback']['url'])):?><div class=\"vwlb-player-frame\"><iframe src=\"<?php echo esc_url($event['playback']['url']);?>\" title=\"<?php echo esc_attr($event['title']);?>\" allowfullscreen></iframe></div><?php endif;?>"
new="<?php if(!empty($event['playback']['url'])):$sandbox=VWLB_Helpers::text($event['playback']['sandbox']??'allow-scripts allow-same-origin allow-presentation',200);?><div class=\"vwlb-player-frame\"><iframe src=\"<?php echo esc_url($event['playback']['url']);?>\" title=\"<?php echo esc_attr($event['title']);?>\" loading=\"lazy\" allow=\"fullscreen; picture-in-picture\" sandbox=\"<?php echo esc_attr($sandbox);?>\" allowfullscreen></iframe></div><?php endif;?>"
if old not in t:raise SystemExit('R32 live iframe pattern missing')
t=t.replace(old,new,1)
old="<label><input type=\"checkbox\" data-vwlb-recording-consent> <?php esc_html_e('I consent to recording when this event explicitly records participant contributions.',VWLB_TEXT_DOMAIN);?></label>"
new="<label><input type=\"checkbox\" data-vwlb-recording-consent <?php checked(!empty($extras['viewer']['recording_consent']));?>> <?php esc_html_e('I consent to recording when this event explicitly records participant contributions.',VWLB_TEXT_DOMAIN);?></label>"
if old not in t:raise SystemExit('R32 consent state pattern missing')
t=t.replace(old,new,1)
old="<button type=\"button\" data-vwlb-waiting-room><?php esc_html_e('Join waiting room',VWLB_TEXT_DOMAIN);?></button>"
new="<button type=\"button\" data-vwlb-waiting-room <?php disabled(in_array($extras['viewer']['state']??'',array('waiting','approved','joined'),true));?>><?php echo esc_html(in_array($extras['viewer']['state']??'',array('waiting','approved','joined'),true)?__('Waiting room joined',VWLB_TEXT_DOMAIN):__('Join waiting room',VWLB_TEXT_DOMAIN));?></button>"
if old not in t:raise SystemExit('R32 waiting state pattern missing')
t=t.replace(old,new,1)
p.write_text(t)
r=reg.read_text();marker="""# R32 — live public UI preserves sandbox isolation and reflects server-persisted participation/consent state.\nneed \"sandbox=\\\"<?php echo esc_attr(\$sandbox);?>\\\"\" \"$P/includes/class-vwlb-frontend.php\" r32-live-sandbox\nneed \"loading=\\\"lazy\\\" allow=\\\"fullscreen; picture-in-picture\\\"\" \"$P/includes/class-vwlb-frontend.php\" r32-live-iframe\nneed \"checked(!empty(\$extras['viewer']['recording_consent']))\" \"$P/includes/class-vwlb-frontend.php\" r32-consent-state\nneed \"Waiting room joined\" \"$P/includes/class-vwlb-frontend.php\" r32-waiting-state\n"""
if '# R32 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
