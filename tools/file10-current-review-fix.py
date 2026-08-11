from pathlib import Path
import re

intel_path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
rest_path=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-rest.php')
reg_path=Path('tests/fresh-40-review-contracts.sh')
t=intel_path.read_text()
r=rest_path.read_text()

invite = r'''	public static function invite_guest( $live_id, $user_id, $role='guest', $scope=array(), $ttl=7200 ) {
		$event=self::live($live_id);if(!self::require_live_control($event,'future_invite_guest'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot invite broadcast guests.',VWLB_TEXT_DOMAIN),403);
		$user_id=absint($user_id);if(!$user_id||!get_userdata($user_id))return VWLB_Helpers::error('vwlb_guest_invalid',__('Guest account is invalid.',VWLB_TEXT_DOMAIN),422);
		$claims=apply_filters('vwlb_identity_claims',null,$user_id,array('contract'=>'File00IdentityClaims.v1','consumer'=>'File 10 guest delegation'));if(!is_array($claims)||empty($claims['identity_approved'])||empty($claims['age_ok'])||empty($claims['guardian_ok'])||!empty($claims['suspended']))return VWLB_Helpers::error('vwlb_guest_identity_unavailable',__('The guest does not currently satisfy File 00 identity and eligibility assertions.',VWLB_TEXT_DOMAIN),409);
		$role=VWLB_Helpers::enum($role,array('guest','cohost','presenter'),'guest');$ttl=max(300,min(DAY_IN_SECONDS,(int)$ttl));
		$scope_allowed=array('camera','microphone','screen','slides','media','scene_control','chat','polls');$scope=array_values(array_unique(array_intersect(array_map('sanitize_key',(array)$scope),$scope_allowed)));
		global $wpdb;$table=VWLB_Helpers::table('broadcast_guests');$now=VWLB_Helpers::now();$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE live_event_id=%d AND user_id=%d",$event['id'],$user_id),ARRAY_A);
		$row=array('role_name'=>$role,'status'=>'invited','scope_json'=>VWLB_Helpers::json_encode($scope),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$ttl),'invited_by'=>get_current_user_id(),'accepted_at'=>null,'updated_at'=>$now);
		if($existing){$row['version']=(int)$existing['version']+1;$changed=$wpdb->update($table,$row,array('id'=>$existing['id'],'version'=>$existing['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Guest delegation changed concurrently.',VWLB_TEXT_DOMAIN),409);$id=(int)$existing['id'];}
		else{$row+=array('public_id'=>VWLB_Helpers::public_id('guest'),'live_event_id'=>(int)$event['id'],'user_id'=>$user_id,'version'=>1,'created_at'=>$now);if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Guest invitation could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		VWLB_Helpers::audit('broadcast_guest',$id,'invite','','invited','',array('live_event_id'=>$event['id'],'guest_user_id'=>$user_id,'role'=>$role,'scope'=>$scope));VWLB_Helpers::outbox('BroadcastGuestInvited','live',$event['id'],array('guest_user_id'=>$user_id,'role'=>$role));return self::public_row('broadcast_guests',$id);
	}
'''
accept = r'''	public static function accept_guest( $guest_public_id ) {
		$row=self::public_row('broadcast_guests',$guest_public_id);if(!$row||(int)$row['user_id']!==get_current_user_id())return VWLB_Helpers::error('vwlb_guest_invite_missing',__('Guest invitation not found.',VWLB_TEXT_DOMAIN),404);
		$claims=VWLB_Security::claims();if(empty($claims['authenticated'])||empty($claims['identity_approved'])||empty($claims['age_ok'])||empty($claims['guardian_ok'])||!empty($claims['suspended']))return VWLB_Helpers::error('vwlb_guest_identity_unavailable',__('Current File 00 identity assertions do not permit guest participation.',VWLB_TEXT_DOMAIN),403);
		$event=self::live($row['live_event_id']);if(!$event||!in_array($event['status'],array('scheduled','rehearsal','ready','live'),true))return VWLB_Helpers::error('vwlb_guest_event_unavailable',__('The live event is no longer accepting guest participation.',VWLB_TEXT_DOMAIN),409);
		if('invited'!==$row['status']||strtotime($row['expires_at'].' UTC')<=time())return VWLB_Helpers::error('vwlb_guest_invite_expired',__('Guest invitation is no longer active.',VWLB_TEXT_DOMAIN),409);
		global $wpdb;$changed=$wpdb->update(VWLB_Helpers::table('broadcast_guests'),array('status'=>'accepted','accepted_at'=>VWLB_Helpers::now(),'version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$row['id'],'version'=>$row['version'],'status'=>'invited'));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Guest invitation changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::audit('broadcast_guest',$row['id'],'accept','invited','accepted','',array('live_event_id'=>$event['id'],'guest_user_id'=>get_current_user_id()));VWLB_Helpers::outbox('BroadcastGuestAccepted','live',$event['id'],array('guest_user_id'=>get_current_user_id(),'guest_public_id'=>$row['public_id']));return self::public_row('broadcast_guests',$row['id']);
	}

	public static function revoke_guest( $guest_public_id ) {
		$row=self::public_row('broadcast_guests',$guest_public_id);if(!$row)return VWLB_Helpers::error('vwlb_guest_invite_missing',__('Guest delegation not found.',VWLB_TEXT_DOMAIN),404);$event=self::live($row['live_event_id']);if(!self::require_live_control($event,'future_revoke_guest'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot revoke this broadcast guest.',VWLB_TEXT_DOMAIN),403);
		if(in_array($row['status'],array('revoked','expired'),true))return self::public_row('broadcast_guests',$row['id']);
		global $wpdb;$changed=$wpdb->update(VWLB_Helpers::table('broadcast_guests'),array('status'=>'revoked','version'=>(int)$row['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$row['id'],'version'=>$row['version']));if(1!==$changed)return VWLB_Helpers::error('vwlb_version_conflict',__('Guest delegation changed concurrently.',VWLB_TEXT_DOMAIN),409);
		VWLB_Helpers::audit('broadcast_guest',$row['id'],'revoke',$row['status'],'revoked','',array('live_event_id'=>$event['id'],'guest_user_id'=>$row['user_id']));VWLB_Helpers::outbox('BroadcastGuestRevoked','live',$event['id'],array('guest_user_id'=>(int)$row['user_id'],'guest_public_id'=>$row['public_id']));return self::public_row('broadcast_guests',$row['id']);
	}
'''

def replace_method(name,replacement):
    global t
    pattern=re.compile(r"\tpublic static function "+re.escape(name)+r"\(.*?\n\t}\n",re.S)
    m=pattern.search(t)
    if not m: raise SystemExit(f'R06 method {name} not found')
    t=t[:m.start()]+replacement+t[m.end():]

replace_method('invite_guest',invite)
replace_method('accept_guest',accept)
intel_path.write_text(t)

route_anchor="\t\t\t$this->route($n,'/broadcast-guests/(?P<id>[A-Za-z0-9_-]+)/accept','POST','guest_accept','login');\n"
route_new=route_anchor+"\t\t\t$this->route($n,'/broadcast-guests/(?P<id>[A-Za-z0-9_-]+)/revoke','POST','guest_revoke','broadcast');\n"
if "/revoke','POST','guest_revoke'" not in r:
    if route_anchor not in r: raise SystemExit('R06 REST route anchor missing')
    r=r.replace(route_anchor,route_new,1)
cb_anchor="\tpublic function guest_accept(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::accept_guest($r['id']));}\n"
cb_new=cb_anchor+"\tpublic function guest_revoke(WP_REST_Request $r){return $this->response(VWLB_Future_Intelligence::revoke_guest($r['id']));}\n"
if "function guest_revoke" not in r:
    if cb_anchor not in r: raise SystemExit('R06 REST callback anchor missing')
    r=r.replace(cb_anchor,cb_new,1)
rest_path.write_text(r)

reg=reg_path.read_text()
marker="""# R06 — F10-FUT-002 guest/co-host delegation is File00-eligible, scoped, expiring, audited, CAS-protected and revocable.\nneed \"File 00 identity and eligibility assertions\" \"$P/includes/class-vwlb-future-intelligence.php\" r06-target-identity\nneed \"Guest invitation changed concurrently\" \"$P/includes/class-vwlb-future-intelligence.php\" r06-accept-cas\nneed \"BroadcastGuestRevoked\" \"$P/includes/class-vwlb-future-intelligence.php\" r06-revoke-event\nneed \"/revoke','POST','guest_revoke'\" \"$P/includes/class-vwlb-future-rest.php\" r06-revoke-route\n"""
if '# R06 —' not in reg: reg=reg.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg_path.write_text(reg)
