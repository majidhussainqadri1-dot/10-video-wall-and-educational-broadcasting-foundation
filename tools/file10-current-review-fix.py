from pathlib import Path
import re

p = Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
t = p.read_text()

helper_anchor = "\tprivate static function require_live_control( $event, $purpose ) {\n\t\treturn $event && VWLB_Security::can( VWLB_Contracts::CAP_BROADCAST, $event, $purpose );\n\t}\n"
helper = helper_anchor + r'''
	private static function contains_raw_secret( $value ) {
		if ( ! is_array( $value ) ) return false;
		foreach ( $value as $key => $child ) {
			$key = sanitize_key( (string) $key );
			if ( str_ends_with( $key, '_ref' ) || str_ends_with( $key, '_id' ) ) { if ( is_array($child) && self::contains_raw_secret($child) ) return true; continue; }
			if ( in_array( $key, array('secret','stream_key','password','api_key','access_token','refresh_token','private_key','token'), true ) ) return true;
			if ( is_array( $child ) && self::contains_raw_secret( $child ) ) return true;
		}
		return false;
	}
'''
if "private static function contains_raw_secret" not in t:
    if helper_anchor not in t: raise SystemExit('R05 helper anchor missing')
    t = t.replace(helper_anchor, helper, 1)

source_method = r'''	public static function upsert_source( $live_id, $data ) {
		$event = self::live( $live_id );
		if ( ! self::require_live_control( $event, 'future_production_source' ) ) return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot manage production sources.', VWLB_TEXT_DOMAIN ), 403 );
		$type = VWLB_Helpers::enum( $data['source_type'] ?? 'camera', array( 'camera','microphone','screen','slides','browser','remote_guest','media','whiteboard' ), '' );
		if ( ! $type ) return VWLB_Helpers::error( 'vwlb_source_type_invalid', __( 'Production source type is invalid.', VWLB_TEXT_DOMAIN ), 422 );
		$label = VWLB_Helpers::text( $data['label'] ?? '', 191 );
		if ( ! $label ) return VWLB_Helpers::error( 'vwlb_source_label_required', __( 'Source label is required.', VWLB_TEXT_DOMAIN ), 422 );
		$config=(array)($data['config']??array());if(self::contains_raw_secret($config))return VWLB_Helpers::error('vwlb_source_secret_forbidden',__('Raw credentials cannot be stored in production-source configuration.',VWLB_TEXT_DOMAIN),422);
		global $wpdb; $table = VWLB_Helpers::table( 'production_sources' ); $now = VWLB_Helpers::now();
		$id = absint( $data['id'] ?? 0 );
		$row = array( 'live_event_id'=>(int)$event['id'], 'source_type'=>$type, 'label'=>$label,
			'provider_ref'=>VWLB_Helpers::text( $data['provider_ref'] ?? '', 191 ), 'state'=>VWLB_Helpers::enum( $data['state'] ?? 'ready', array('ready','muted','offline','failed','removed'), 'ready' ),
			'config_json'=>VWLB_Helpers::json_encode( $config ), 'updated_at'=>$now );
		if ( $id ) {
			$current = self::public_row( 'production_sources', $id );
			if ( ! $current || (int)$current['live_event_id'] !== (int)$event['id'] ) return VWLB_Helpers::error( 'vwlb_source_missing', __( 'Production source not found.', VWLB_TEXT_DOMAIN ), 404 );
			$row['version'] = (int)$current['version'] + 1;
			$ok = $wpdb->update( $table, $row, array( 'id'=>$id, 'version'=>(int)$current['version'] ) );
			if ( 1 !== $ok ) return VWLB_Helpers::error( 'vwlb_version_conflict', __( 'Production source changed. Refresh and try again.', VWLB_TEXT_DOMAIN ), 409 );
		} else {
			$public = VWLB_Helpers::public_id( 'src' ); $row['public_id']=$public; $row['owner_id']=get_current_user_id(); $row['created_at']=$now; $row['version']=1;
			if ( ! $wpdb->insert( $table, $row ) ) return VWLB_Helpers::error( 'vwlb_database_error', __( 'Production source could not be saved.', VWLB_TEXT_DOMAIN ), 500 );
			$id = (int)$wpdb->insert_id;
		}
		VWLB_Helpers::audit( 'live', $event['id'], 'production_source_saved', $event['status'], $event['status'], $type, array( 'source_id'=>$id ) );
		do_action( 'vwlb_production_source_changed', $event, self::public_row( 'production_sources', $id ) );
		return self::public_row( 'production_sources', $id );
	}
'''
scene_method = r'''	public static function upsert_scene( $live_id, $data ) {
		$event = self::live( $live_id );
		if ( ! self::require_live_control( $event, 'future_production_scene' ) ) return VWLB_Helpers::error( 'vwlb_forbidden', __( 'You cannot manage production scenes.', VWLB_TEXT_DOMAIN ), 403 );
		$title = VWLB_Helpers::text( $data['title'] ?? '', 191 ); if ( ! $title ) return VWLB_Helpers::error( 'vwlb_scene_title_required', __( 'Scene title is required.', VWLB_TEXT_DOMAIN ), 422 );
		$sources = array_values( array_unique( array_filter( array_map( 'absint', (array)( $data['source_ids'] ?? array() ) ) ) ) );
		foreach($sources as $source_id){$source=self::public_row('production_sources',$source_id);if(!$source||(int)$source['live_event_id']!==(int)$event['id']||'removed'===$source['state'])return VWLB_Helpers::error('vwlb_scene_source_invalid',__('Every scene source must be an active source of the same live event.',VWLB_TEXT_DOMAIN),422,array('source_id'=>$source_id));}
		global $wpdb; $table=VWLB_Helpers::table('production_scenes'); $now=VWLB_Helpers::now();
		$id=absint($data['id']??0); $row=array('live_event_id'=>(int)$event['id'],'title'=>$title,
			'layout_json'=>VWLB_Helpers::json_encode((array)($data['layout']??array())),'source_ids_json'=>VWLB_Helpers::json_encode($sources),'updated_at'=>$now);
		if($id){$current=self::public_row('production_scenes',$id);if(!$current||(int)$current['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);$row['version']=(int)$current['version']+1;$ok=$wpdb->update($table,$row,array('id'=>$id,'version'=>(int)$current['version']));if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);}
		else{$row['public_id']=VWLB_Helpers::public_id('scene');$row['owner_id']=get_current_user_id();$row['state']='saved';$row['is_program']=0;$row['version']=1;$row['created_at']=$now;if(!$wpdb->insert($table,$row))return VWLB_Helpers::error('vwlb_database_error',__('Scene could not be saved.',VWLB_TEXT_DOMAIN),500);$id=(int)$wpdb->insert_id;}
		return self::public_row('production_scenes',$id);
	}
'''
switch_method = r'''	public static function switch_program_scene( $live_id, $scene_id, $expected_version ) {
		$event=self::live($live_id); if(!self::require_live_control($event,'future_switch_scene'))return VWLB_Helpers::error('vwlb_forbidden',__('You cannot switch scenes.',VWLB_TEXT_DOMAIN),403);
		$scene=self::public_row('production_scenes',$scene_id); if(!$scene||(int)$scene['live_event_id']!==(int)$event['id'])return VWLB_Helpers::error('vwlb_scene_missing',__('Scene not found.',VWLB_TEXT_DOMAIN),404);
		if((int)$scene['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);
		global $wpdb; $table=VWLB_Helpers::table('production_scenes');
		return VWLB_DB::transaction(function()use($wpdb,$table,$event,$scene,$expected_version){
			$locked_event=VWLB_Repository::find('live_events',$event['id'],true);if(!$locked_event)return VWLB_Helpers::error('vwlb_live_missing',__('Live event not found.',VWLB_TEXT_DOMAIN),404);
			$fresh=self::public_row('production_scenes',$scene['id']);if(!$fresh||(int)$fresh['live_event_id']!==(int)$event['id']||(int)$fresh['version']!==(int)$expected_version)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene changed. Refresh and try again.',VWLB_TEXT_DOMAIN),409);
			$cleared=$wpdb->query($wpdb->prepare("UPDATE $table SET is_program=0,updated_at=%s WHERE live_event_id=%d AND is_program=1 AND id<>%d",VWLB_Helpers::now(),$event['id'],$fresh['id']));if(false===$cleared)return VWLB_Helpers::error('vwlb_database_error',__('The previous program scene could not be cleared.',VWLB_TEXT_DOMAIN),500);
			$ok=$wpdb->update($table,array('is_program'=>1,'version'=>(int)$fresh['version']+1,'updated_at'=>VWLB_Helpers::now()),array('id'=>$fresh['id'],'version'=>$fresh['version']));
			if(1!==$ok)return VWLB_Helpers::error('vwlb_version_conflict',__('Scene switch conflicted with another operator.',VWLB_TEXT_DOMAIN),409);
			VWLB_Helpers::audit('live',$event['id'],'program_scene_switched',$event['status'],$event['status'],'',array('scene_id'=>$fresh['id']));
			do_action('vwlb_program_scene_switched',$event,self::public_row('production_scenes',$fresh['id']));
			return self::public_row('production_scenes',$fresh['id']);
		});
	}
'''

def replace_method(name, replacement):
    global t
    pattern = re.compile(r"\tpublic static function " + re.escape(name) + r"\(.*?\n\t}\n", re.S)
    m = pattern.search(t)
    if not m: raise SystemExit(f'R05 method {name} not found')
    t = t[:m.start()] + replacement + t[m.end():]

replace_method('upsert_source', source_method)
replace_method('upsert_scene', scene_method)
replace_method('switch_program_scene', switch_method)
p.write_text(t)

reg = Path('tests/fresh-40-review-contracts.sh')
r = reg.read_text()
marker = "# R05 — production sources/scenes preserve ownership, reject raw secrets/cross-event sources, and serialize program switching.\nneed \"contains_raw_secret\" \"$P/includes/class-vwlb-future-intelligence.php\" r05-secret-rejection\nneed \"Every scene source must be an active source of the same live event\" \"$P/includes/class-vwlb-future-intelligence.php\" r05-scene-source-scope\nneed \"find('live_events',\$event['id'],true)\" \"$P/includes/class-vwlb-future-intelligence.php\" r05-program-lock\nneed \"id<>%d\" \"$P/includes/class-vwlb-future-intelligence.php\" r05-single-program\n"
if '# R05 —' not in r:
    r = r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n", marker + "printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
