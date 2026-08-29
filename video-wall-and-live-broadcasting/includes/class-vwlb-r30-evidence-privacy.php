<?php
/** R30/R41: encrypted audit/outbox fallback reconciliation plus exhaustive bounded legacy plaintext migration. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R30_Evidence_Privacy {
	const MIGRATION_OPTION = 'vwlb_r30_evidence_fallback_migration';
	const MIGRATION_PAGE_SIZE = 250;
	const MIGRATION_MAX_PAGES = 200; // Hard ceiling: at most 50,000 fallback rows per namespace per pass.
	public static function register(){
		remove_action('vwlb_reconcile_states',array('VWLB_Review_Hardening','reconcile_fallbacks'),60);
		add_action('vwlb_reconcile_states',array(__CLASS__,'reconcile'),60);
		self::migrate_legacy();
	}
	private static function options($prefix,$limit=100,$after_id=0){
		global $wpdb;
		$like=$wpdb->esc_like($prefix).'%';
		$limit=max(1,min(500,(int)$limit));
		$after_id=max(0,(int)$after_id);
		return $wpdb->get_results($wpdb->prepare("SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id ASC LIMIT %d",$like,$after_id,$limit),ARRAY_A);
	}
	private static function legacy_row($value){$row=maybe_unserialize($value);return is_array($row)&&empty($row['ciphertext'])?$row:null;}
	private static function migrate_prefix($prefix,$kind){
		$after_id=0;
		for($page=0;$page<self::MIGRATION_MAX_PAGES;$page++){
			$rows=self::options($prefix,self::MIGRATION_PAGE_SIZE,$after_id);
			if(!$rows)return true;
			foreach($rows as $option){
				$after_id=max($after_id,absint($option['option_id']??0));
				$legacy=self::legacy_row($option['option_value']);
				if(!$legacy)continue;
				$encrypted=VWLB_Helpers::encrypt_evidence_fallback($kind,$legacy);if(is_wp_error($encrypted))return $encrypted;
				$saved=update_option($option['option_name'],$encrypted,false);
				if(!$saved){$existing=get_option($option['option_name'],null);$decoded=VWLB_Helpers::decrypt_evidence_fallback($existing,$kind);if(is_wp_error($decoded)||$decoded!==$legacy)return VWLB_Helpers::error('vwlb_evidence_legacy_migration_failed',__('Legacy evidence fallback could not be encrypted safely.',VWLB_TEXT_DOMAIN),503);}
			}
			if(count($rows)<self::MIGRATION_PAGE_SIZE)return true;
		}
		return VWLB_Helpers::error('vwlb_evidence_legacy_migration_limit',__('Legacy evidence fallback migration exceeded its bounded automatic scan limit and remains incomplete.',VWLB_TEXT_DOMAIN),503);
	}
	private static function verify_no_legacy($prefix){
		$after_id=0;
		for($page=0;$page<self::MIGRATION_MAX_PAGES;$page++){
			$rows=self::options($prefix,self::MIGRATION_PAGE_SIZE,$after_id);
			if(!$rows)return true;
			foreach($rows as $option){$after_id=max($after_id,absint($option['option_id']??0));if(self::legacy_row($option['option_value']))return false;}
			if(count($rows)<self::MIGRATION_PAGE_SIZE)return true;
		}
		return false;
	}
	public static function migrate_legacy(){
		if('complete'===get_option(self::MIGRATION_OPTION,''))return true;
		$audit=self::migrate_prefix(VWLB_Review_Hardening::AUDIT_FALLBACK_PREFIX,'audit');if(is_wp_error($audit)){do_action('vwlb_operational_failure','evidence',$audit->get_error_code(),array('phase'=>'r30-audit-migration'));return $audit;}
		$outbox=self::migrate_prefix(VWLB_Review_Hardening::OUTBOX_FALLBACK_PREFIX,'outbox');if(is_wp_error($outbox)){do_action('vwlb_operational_failure','evidence',$outbox->get_error_code(),array('phase'=>'r30-outbox-migration'));return $outbox;}
		// R41: prove the complete bounded namespace scan, not merely the first 500 rows, before marking migration complete.
		foreach(array(VWLB_Review_Hardening::AUDIT_FALLBACK_PREFIX,VWLB_Review_Hardening::OUTBOX_FALLBACK_PREFIX) as $prefix){if(!self::verify_no_legacy($prefix))return VWLB_Helpers::error('vwlb_evidence_legacy_migration_incomplete',__('Legacy evidence fallback migration remains incomplete.',VWLB_TEXT_DOMAIN),503);}
		$saved=update_option(self::MIGRATION_OPTION,'complete',false);if(!$saved&&'complete'!==get_option(self::MIGRATION_OPTION,''))return VWLB_Helpers::error('vwlb_evidence_legacy_migration_marker_failed',__('Evidence fallback migration marker could not be persisted.',VWLB_TEXT_DOMAIN),503);return true;
	}
	private static function reconcile_prefix($prefix,$kind,$table){
		global $wpdb;foreach(self::options($prefix,50) as $option){$stored=maybe_unserialize($option['option_value']);$row=VWLB_Helpers::decrypt_evidence_fallback($stored,$kind);if(is_wp_error($row)){do_action('vwlb_operational_failure','evidence',$row->get_error_code(),array('kind'=>$kind,'option_hash'=>hash('sha256',$option['option_name'])));continue;}$saved=$wpdb->insert(VWLB_Helpers::table($table),$row);if(false===$saved)continue;$deleted=delete_option($option['option_name']);if(!$deleted&&false!==get_option($option['option_name'],false))do_action('vwlb_operational_failure','evidence','vwlb_evidence_fallback_release_failed',array('kind'=>$kind,'option_hash'=>hash('sha256',$option['option_name'])));}
	}
	public static function reconcile(){
		$migrated=self::migrate_legacy();if(is_wp_error($migrated))return;
		self::reconcile_prefix(VWLB_Review_Hardening::AUDIT_FALLBACK_PREFIX,'audit','audit');
		self::reconcile_prefix(VWLB_Review_Hardening::OUTBOX_FALLBACK_PREFIX,'outbox','outbox');
	}
}
