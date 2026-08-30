<?php
/** R61: outer activation transaction for File 10 capabilities across every existing WordPress role. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R61_Activation_Role_Guard {
	const OPTION = 'vwlb_r61_activation_role_snapshot';
	const STALE_SECONDS = 900;
	private static $committed = false;

	private static function caps(){return array(VWLB_Contracts::CAP_SUBMIT,VWLB_Contracts::CAP_PUBLISH,VWLB_Contracts::CAP_BROADCAST,VWLB_Contracts::CAP_MODERATE,VWLB_Contracts::CAP_REVIEW,VWLB_Contracts::CAP_OPERATE,VWLB_Contracts::CAP_MANAGE,VWLB_Contracts::CAP_DIAGNOSTICS);}
	private static function snapshot(){
		$state=array();$roles=function_exists('wp_roles')?wp_roles():null;$names=$roles&&is_array($roles->roles)?array_keys($roles->roles):array('administrator');
		foreach($names as $name){$role=get_role($name);if(!$role)continue;foreach(self::caps() as $cap)$state[$name][$cap]=(bool)$role->has_cap($cap);}
		return $state;
	}
	private static function restore($record){
		if(!is_array($record)||!is_array($record['roles']??null))return false;$ok=true;
		foreach($record['roles'] as $name=>$caps){$role=get_role($name);if(!$role){$ok=false;continue;}foreach(self::caps() as $cap){$had=!empty($caps[$cap]);if($had&&!$role->has_cap($cap))$role->add_cap($cap);elseif(!$had&&$role->has_cap($cap))$role->remove_cap($cap);}}
		return $ok;
	}
	public static function activation_begin(){
		$existing=get_option(self::OPTION,false);if(is_array($existing)){$age=time()-absint($existing['started_at']??0);if($age>0&&$age<self::STALE_SECONDS)wp_die(esc_html__('File 10 role-capability activation rollback is already in progress. Retry shortly.',VWLB_TEXT_DOMAIN));if(!self::restore($existing))wp_die(esc_html__('A stale File 10 role-capability snapshot could not be restored safely.',VWLB_TEXT_DOMAIN));delete_option(self::OPTION);}
		$record=array('started_at'=>time(),'token'=>wp_generate_uuid4(),'roles'=>self::snapshot());$created=add_option(self::OPTION,$record,'',false);if(!$created&&get_option(self::OPTION,null)!==$record)wp_die(esc_html__('File 10 role-capability rollback evidence could not be persisted.',VWLB_TEXT_DOMAIN));register_shutdown_function(array(__CLASS__,'activation_shutdown'));
	}
	public static function activation_commit(){
		$current=get_option(self::OPTION,false);if(!is_array($current))wp_die(esc_html__('File 10 role-capability rollback evidence disappeared before commit.',VWLB_TEXT_DOMAIN));$deleted=delete_option(self::OPTION);if(!$deleted&&false!==get_option(self::OPTION,false))wp_die(esc_html__('File 10 role-capability rollback evidence could not be closed safely.',VWLB_TEXT_DOMAIN));self::$committed=true;
	}
	public static function activation_shutdown(){if(self::$committed)return;$record=get_option(self::OPTION,false);if(!is_array($record))return;$ok=self::restore($record);if($ok)delete_option(self::OPTION);try{do_action('vwlb_operational_failure','activation','vwlb_activation_role_caps_compensated',array('rollback_verified'=>$ok));}catch(Throwable $e){}}
}
