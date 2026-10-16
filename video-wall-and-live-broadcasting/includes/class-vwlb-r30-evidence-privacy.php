<?php
/** R30: encrypted audit/outbox fallback reconciliation plus legacy plaintext migration. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R30_Evidence_Privacy {
	const MIGRATION_OPTION = 'vwlb_r30_evidence_fallback_migration';
	public static function register(){
		remove_action('vwlb_reconcile_states',array('VWLB_Review_Hardening','reconcile_fallbacks'),60);
		add_action('vwlb_reconcile_states',array(__CLASS__,'reconcile'),60);
		self::migrate_legacy();
	}
	private static function options($prefix,$limit=100){global $wpdb;$like=$wpdb->esc_like($prefix).'%';return $wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",$like,max(1,min(500,(int)$limit))),ARRAY_A);}
	private static function legacy_row($value){$row=maybe_unserialize($value);return is_array($row)&&empty($row['ciphertext'])?$row:null;}
	private static function migrate_prefix($prefix,$kind){
		foreach(self::options($prefix,500) as $option){$legacy=self::legacy_row($option['option_value']);if(!$legacy)continue;$encrypted=VWLB_Helpers::encrypt_evidence_fallback($kind,$legacy);if(is_wp_error($encrypted))return $encrypted;$saved=update_option($option['option_name'],$encrypted,false);if(!$saved){$existing=get_option($option['option_name'],null);$decoded=VWLB_Helpers::decrypt_evidence_fallback($existing,$kind);if(is_wp_error($decoded)||$decoded!==$legacy)return VWLB_Helpers::error('vwlb_evidence_legacy_migration_failed',__('Legacy evidence fallback could not be encrypted safely.',VWLB_TEXT_DOMAIN),503);}}
		return true;
	}
	public static function migrate_legacy(){
		if('complete'===get_option(self::MIGRATION_OPTION,''))return true;$audit=self::migrate_prefix(VWLB_Review_Hardening::AUDIT_FALLBACK_PREFIX,'audit');if(is_wp_error($audit)){do_action('vwlb_operational_failure','evidence',$audit->get_error_code(),array('phase'=>'r30-audit-migration'));return $audit;}$outbox=self::migrate_prefix(VWLB_Review_Hardening::OUTBOX_FALLBACK_PREFIX,'outbox');if(is_wp_error($outbox)){do_action('vwlb_operational_failure','evidence',$outbox->get_error_code(),array('phase'=>'r30-outbox-migration'));return $outbox;}
		// Mark complete only after a second scan proves no legacy plaintext fallback remains.
		foreach(array(VWLB_Review_Hardening::AUDIT_FALLBACK_PREFIX,VWLB_Review_Hardening::OUTBOX_FALLBACK_PREFIX) as $prefix){foreach(self::options($prefix,500) as $option)if(self::legacy_row($option['option_value']))return VWLB_Helpers::error('vwlb_evidence_legacy_migration_incomplete',__('Legacy evidence fallback migration remains incomplete.',VWLB_TEXT_DOMAIN),503);}
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
