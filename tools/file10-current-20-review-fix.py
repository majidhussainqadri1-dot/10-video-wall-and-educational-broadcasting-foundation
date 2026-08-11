from pathlib import Path

root = Path('.')
ext = root / 'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
run = root / 'tests/run-all.sh'
contracts = root / 'tests/fresh-20-review-contracts.sh'
ledger = root / 'docs/FILE-10-FRESH-20-REVIEW-2026-08-12.md'


def replace_function(path: Path, name: str, next_name: str, body: str):
    text = path.read_text()
    start = text.index('\tpublic static function ' + name)
    end = text.index('\n\tpublic static function ' + next_name, start)
    text = text[:start] + body.rstrip() + '\n' + text[end:]
    path.write_text(text)


new_join = r'''\tpublic static function join_waiting_room( $live_id, $data=array() ) {
\t\t$event=VWLB_Repository::find('live_events',$live_id);
\t\tif(!$event||!VWLB_Security::can_view($event,'waiting_room'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
\t\tif(!is_user_logged_in())return VWLB_Helpers::error('vwlb_login_required',__('Sign in to join the waiting room.',VWLB_TEXT_DOMAIN),401);
\t\tif(!in_array($event['status'],array('scheduled','rehearsal','ready','live'),true))return VWLB_Helpers::error('vwlb_waiting_room_closed',__('The waiting room is closed.',VWLB_TEXT_DOMAIN),409);
\t\treturn VWLB_DB::transaction(function()use($event,$data){
\t\t\tglobal $wpdb;
\t\t\t$events=VWLB_Helpers::table('live_events');
\t\t\t$fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE id=%d FOR UPDATE",$event['id']),ARRAY_A);
\t\t\tif(!$fresh||!VWLB_Security::can_view($fresh,'waiting_room'))return VWLB_Helpers::error('vwlb_not_found',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
\t\t\tif(!in_array($fresh['status'],array('scheduled','rehearsal','ready','live'),true))return VWLB_Helpers::error('vwlb_waiting_room_closed',__('The waiting room is closed.',VWLB_TEXT_DOMAIN),409);
\t\t\t$table=VWLB_Helpers::table('live_attendees');$uid=get_current_user_id();
\t\t\t$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d FOR UPDATE",$fresh['id'],$uid),ARRAY_A);
\t\t\t$capacity=self::event_capacity($fresh);
\t\t\tif(!$existing&&$capacity>0){
\t\t\t\t$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE live_event_id=%d AND state IN ('waiting','approved','joined')",$fresh['id']));
\t\t\t\tif($count>=$capacity)return VWLB_Helpers::error('vwlb_live_capacity_reached',__('This live event has reached capacity.',VWLB_TEXT_DOMAIN),409);
\t\t\t}
\t\t\t$state='live'===$fresh['status']?'joined':'waiting';$reminder=max(0,min(1440,(int)($data['reminder_minutes']??15)));$now=VWLB_Helpers::now();
\t\t\tif($existing){
\t\t\t\t$changed=$wpdb->update($table,array('state'=>$state,'reminder_minutes'=>$reminder,'joined_at'=>'joined'===$state?$now:($existing['joined_at']??null),'version'=>(int)$existing['version']+1,'updated_at'=>$now),array('id'=>$existing['id'],'version'=>$existing['version']));
\t\t\t\tif(1!==$changed)return VWLB_Helpers::error('vwlb_waiting_room_conflict',__('Waiting-room state changed concurrently. Retry safely.',VWLB_TEXT_DOMAIN),409);
\t\t\t\t$id=(int)$existing['id'];
\t\t\t}else{
\t\t\t\t$saved=$wpdb->insert($table,array('public_id'=>VWLB_Helpers::public_id('att'),'live_event_id'=>$fresh['id'],'user_id'=>$uid,'state'=>$state,'reminder_minutes'=>$reminder,'recording_consent'=>0,'consent_version'=>'','joined_at'=>'joined'===$state?$now:null,'version'=>1,'created_at'=>$now,'updated_at'=>$now));
\t\t\t\tif(!$saved||!(int)$wpdb->insert_id)return VWLB_Helpers::error('vwlb_database_error',__('Waiting-room attendance could not be saved.',VWLB_TEXT_DOMAIN),500);
\t\t\t\t$id=(int)$wpdb->insert_id;
\t\t\t}
\t\t\tVWLB_Helpers::audit('live_attendee',$id,'waiting_room_join','',$state,'',array('live_event_id'=>$fresh['id']));
\t\t\treturn array('attendee_id'=>$id,'state'=>$state,'reminder_minutes'=>$reminder,'recording_consent'=>$existing?!empty($existing['recording_consent']):false);
\t\t});
\t}'''.replace('\\t', '\t')
replace_function(ext, 'join_waiting_room', 'set_recording_consent', new_join)

if not contracts.exists():
    contracts.write_text("""#!/usr/bin/env bash
set -euo pipefail
ROOT=\"$(cd \"$(dirname \"${BASH_SOURCE[0]}\")/..\" && pwd)\"
P=\"$ROOT/video-wall-and-live-broadcasting\"
need(){ grep -R -F -- \"$1\" \"$2\" >/dev/null || { echo \"FAIL fresh-20-review: $3\" >&2; exit 1; }; }
""")
ct = contracts.read_text()
marker = "# R01 — waiting-room capacity is serialized and attendee writes fail closed.\nneed \"FOR UPDATE\" \"$P/includes/class-vwlb-extensions.php\" r01-event-lock\nneed \"vwlb_waiting_room_conflict\" \"$P/includes/class-vwlb-extensions.php\" r01-cas\nneed \"Waiting-room attendance could not be saved.\" \"$P/includes/class-vwlb-extensions.php\" r01-insert-check\n"
if '# R01 —' not in ct:
    ct += marker
contracts.write_text(ct)

r = run.read_text()
line = 'bash "$ROOT/tests/fresh-20-review-contracts.sh"\n'
if line not in r:
    anchor = 'bash "$ROOT/tests/fresh-40-review-adversarial.sh"\n'
    r = r.replace(anchor, anchor + line, 1)
run.write_text(r)

if not ledger.exists():
    ledger.write_text("""# File 10 — Fresh Sequential 20-Review Corrective Record

Date: 2026-08-12 (Asia/Karachi)
Repository: `majidhussainqadri1-dot/10-video-wall-and-educational-broadcasting-foundation`
Branch: `fix/file-10-fresh-20-review-v1.2.2-rc1`
Frozen starting source HEAD: `1593c722abf4fe8f5e2094621a1a9215cd9b992b`
Governing basis: Central Master Plan v3.0 + File 10 v1.1 Future Video & Broadcasting Intelligence 24 amendment.

Sequential law: each round reviews the corrected state from the immediately preceding round; a supported defect is fixed and full File 10 automated QA is run before the next round.

This is repository/source evidence only, not staging/live evidence.

""")
ld = ledger.read_text()
entry = "## R01 — DEFECT FIXED\nWaiting-room capacity enforcement was race-prone and attendee insert/update results were not checked. The live-event row is now serialized with `FOR UPDATE`, capacity is counted inside the transaction, and attendee persistence fails closed on CAS/database failure.\n\n"
if '## R01 —' not in ld:
    ld += entry
ledger.write_text(ld)
print('R01 correction prepared')
