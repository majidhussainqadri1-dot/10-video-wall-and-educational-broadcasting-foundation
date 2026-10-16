<?php
/** R04 migration/storage verification guard. Runs once per runtime release and fails closed. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R4_Migration_Guard {
	const VERIFIED_OPTION = 'vwlb_schema_verified_release';
	const LOCK_OPTION = 'vwlb_schema_verification_lock';
	const LOCK_TTL = 300;

	private static function release_lock( $token ) {
		global $wpdb;
		$deleted=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",self::LOCK_OPTION,(string)$token));
		if(1===$deleted){wp_cache_delete(self::LOCK_OPTION,'options');wp_cache_delete('notoptions','options');wp_cache_delete('alloptions','options');return true;}
		return false;
	}
	private static function acquire_lock() {
		$token=time().'|'.wp_generate_uuid4();
		if(add_option(self::LOCK_OPTION,$token,'',false))return $token;
		$current=(string)get_option(self::LOCK_OPTION,'');$parts=explode('|',$current,2);$locked_at=absint($parts[0]??0);
		if($locked_at&&(time()-$locked_at)>self::LOCK_TTL&&self::release_lock($current)&&add_option(self::LOCK_OPTION,$token,'',false))return $token;
		return VWLB_Helpers::error('vwlb_schema_verification_busy',__('File 10 schema verification is already in progress. Retry shortly.',VWLB_TEXT_DOMAIN),503);
	}
	private static function verify_table_contract($table,$columns,$indexes=array()){
		global $wpdb;$table=preg_replace('/[^A-Za-z0-9_]/','',(string)$table);if(!$table)return VWLB_Helpers::error('vwlb_schema_contract_invalid',__('A File 10 schema contract is invalid.',VWLB_TEXT_DOMAIN),500);
		$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if($found!==$table)return VWLB_Helpers::error('vwlb_schema_incomplete',__('A required File 10 database table is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));
		$actual_columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);if(!is_array($actual_columns))return VWLB_Helpers::error('vwlb_schema_columns_unreadable',__('File 10 could not verify required database columns.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));foreach((array)$columns as $column)if(!in_array($column,$actual_columns,true))return VWLB_Helpers::error('vwlb_schema_column_missing',__('A required File 10 database column is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'column'=>$column));
		$rows=$wpdb->get_results("SHOW INDEX FROM `{$table}`",ARRAY_A);if(!is_array($rows))return VWLB_Helpers::error('vwlb_schema_indexes_unreadable',__('File 10 could not verify required database indexes.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));$actual=array();foreach($rows as $row){$name=(string)$row['Key_name'];$seq=(int)$row['Seq_in_index'];$actual[$name][$seq]=(string)$row['Column_name'];}foreach($actual as &$cols){ksort($cols);$cols=array_values($cols);}unset($cols);foreach((array)$indexes as $name=>$expected){if(empty($actual[$name]))return VWLB_Helpers::error('vwlb_schema_index_missing',__('A required File 10 database index is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));if(array_values($expected)!==$actual[$name])return VWLB_Helpers::error('vwlb_schema_index_mismatch',__('A required File 10 database index has the wrong column order.',VWLB_TEXT_DOMAIN),500,array('table'=>$table,'index'=>$name));}return true;
	}
	private static function verify_podcast_contract() {
		$contracts=array(
			'podcast_series'=>array('columns'=>array('id','public_id','owner_id','channel_id','title','slug','description','language','artwork_id','rights_status','status','visibility','version','created_at','updated_at','deleted_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'slug'=>array('slug'),'owner_status'=>array('owner_id','status'))),
			'podcast_episodes'=>array('columns'=>array('id','public_id','series_id','owner_id','asset_id','title','slug','description','language','duration_seconds','rights_status','consent_status','visibility','status','transcript_caption_id','transcript_text','transcript_status','download_allowed','published_at','version','created_at','updated_at','deleted_at'),'indexes'=>array('PRIMARY'=>array('id'),'public_id'=>array('public_id'),'series_slug'=>array('series_id','slug'),'owner_status'=>array('owner_id','status'),'asset_id'=>array('asset_id'),'published_at'=>array('published_at'))),
		);
		foreach($contracts as $name=>$contract){$result=self::verify_table_contract(VWLB_Helpers::table($name),$contract['columns'],$contract['indexes']);if(is_wp_error($result))return $result;}return true;
	}
	private static function harden_private_storage() {
		$base=trailingslashit(WP_CONTENT_DIR).VWLB_Extensions::PRIVATE_DIR;
		if(!is_dir($base)&&!wp_mkdir_p($base))return VWLB_Helpers::error('vwlb_private_storage_unavailable',__('Private media storage could not be created.',VWLB_TEXT_DOMAIN),503);
		$protect=array('index.php'=>"<?php\nhttp_response_code(404);\nexit;\n",'.htaccess'=>"Require all denied\nDeny from all\nOptions -Indexes\n",'web.config'=>"<?xml version=\"1.0\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>");
		foreach($protect as $file=>$content){$path=trailingslashit($base).$file;$written=file_put_contents($path,$content,LOCK_EX);if(false===$written)return VWLB_Helpers::error('vwlb_private_storage_protection_failed',__('Private media storage protection could not be written.',VWLB_TEXT_DOMAIN),503,array('file'=>$file));$actual=file_get_contents($path);if(false===$actual||!hash_equals(hash('sha256',$content),hash('sha256',$actual)))return VWLB_Helpers::error('vwlb_private_storage_protection_unverified',__('Private media storage protection could not be verified.',VWLB_TEXT_DOMAIN),503,array('file'=>$file));}return true;
	}
	public static function verify_release() {
		if((string)get_option(self::VERIFIED_OPTION,'')===VWLB_VERSION)return true;
		$token=self::acquire_lock();if(is_wp_error($token))return $token;
		try{
			// Re-run every idempotent installer once per runtime release. This repairs stale/partial schema even when older schema markers were over-optimistic.
			$base=VWLB_DB::install_schema();if(is_wp_error($base))return $base;
			$ext=VWLB_Extensions::install_schema();if(is_wp_error($ext))return $ext;
			$future=VWLB_Future_Intelligence::install_schema();if(is_wp_error($future))return $future;
			$pod=self::verify_podcast_contract();if(is_wp_error($pod))return $pod;
			$storage=self::harden_private_storage();if(is_wp_error($storage))return $storage;
			$saved=update_option(self::VERIFIED_OPTION,VWLB_VERSION,false);if(!$saved&&(string)get_option(self::VERIFIED_OPTION,'')!==VWLB_VERSION)return VWLB_Helpers::error('vwlb_schema_release_marker_failed',__('File 10 verified schema release marker could not be recorded.',VWLB_TEXT_DOMAIN),500);
			VWLB_Helpers::audit('system',10,'schema_release_verified','',VWLB_VERSION,'Base, extension, Future and podcast installers plus podcast structural contract and private-storage protection verified.');return true;
		}finally{self::release_lock($token);}
	}
}
