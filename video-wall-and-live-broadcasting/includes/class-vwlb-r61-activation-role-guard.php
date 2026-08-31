<?php
/** R61/R101: outer activation transaction for every role capability File 10 will actually mutate. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R61_Activation_Role_Guard {
	const OPTION = 'vwlb_r61_activation_role_snapshot';
	const STALE_SECONDS = 900;
	private static $committed = false;
	private static $token = '';

	private static function restore($record){
		if(!is_array($record))return false;
		$roles=$record['roles']??array();
		if(!is_array($roles))return false;
		$ok=true;
		foreach($roles as $name=>$caps){
			$role=get_role($name);if(!$role){$ok=false;continue;}
			foreach((array)$caps as $cap=>$had){
				if($had&&!$role->has_cap($cap))$role->add_cap($cap);
				elseif(!$had&&$role->has_cap($cap))$role->remove_cap($cap);
			}
		}
		return $ok;
	}

	/**
	 * Capture the exact, fully-filtered role/capability mutation map immediately before
	 * VWLB_Activator mutates roles. This closes rollback gaps for companion filters that
	 * add File 10 capabilities beyond the built-in administrator defaults.
	 */
	public static function capture_role_map($roles){
		remove_filter('vwlb_activation_role_capabilities',array(__CLASS__,'capture_role_map'),PHP_INT_MAX);
		$record=get_option(self::OPTION,false);
		if(!is_array($record)||!self::$token||!hash_equals((string)($record['token']??''),self::$token)){
			wp_die(esc_html__('File 10 role-capability rollback evidence is unavailable before mutation.',VWLB_TEXT_DOMAIN));
		}
		$state=array();
		foreach((array)$roles as $name=>$caps){
			$name=sanitize_key((string)$name);if(!$name||!is_array($caps))continue;
			$role=get_role($name);if(!$role)continue;
			foreach($caps as $cap){$cap=sanitize_key((string)$cap);if($cap)$state[$name][$cap]=(bool)$role->has_cap($cap);}
		}
		$record['roles']=$state;$record['captured_at']=time();
		$saved=update_option(self::OPTION,$record,false);
		if(!$saved&&get_option(self::OPTION,null)!==$record){
			wp_die(esc_html__('File 10 role-capability rollback evidence could not be finalized before mutation.',VWLB_TEXT_DOMAIN));
		}
		return $roles;
	}

	public static function activation_begin(){
		self::$committed=false;self::$token='';
		$existing=get_option(self::OPTION,false);
		if(is_array($existing)){
			$age=time()-absint($existing['started_at']??0);
			if($age>0&&$age<self::STALE_SECONDS)wp_die(esc_html__('File 10 role-capability activation rollback is already in progress. Retry shortly.',VWLB_TEXT_DOMAIN));
			if(!self::restore($existing))wp_die(esc_html__('A stale File 10 role-capability snapshot could not be restored safely.',VWLB_TEXT_DOMAIN));
			$deleted=delete_option(self::OPTION);if(!$deleted&&false!==get_option(self::OPTION,false))wp_die(esc_html__('A stale File 10 role-capability snapshot could not be closed safely.',VWLB_TEXT_DOMAIN));
		}
		self::$token=wp_generate_uuid4();
		$record=array('started_at'=>time(),'token'=>self::$token,'roles'=>array(),'captured_at'=>0);
		$created=add_option(self::OPTION,$record,'',false);
		if(!$created&&get_option(self::OPTION,null)!==$record)wp_die(esc_html__('File 10 role-capability rollback evidence could not be persisted.',VWLB_TEXT_DOMAIN));
		add_filter('vwlb_activation_role_capabilities',array(__CLASS__,'capture_role_map'),PHP_INT_MAX);
		register_shutdown_function(array(__CLASS__,'activation_shutdown'));
	}

	public static function activation_commit(){
		remove_filter('vwlb_activation_role_capabilities',array(__CLASS__,'capture_role_map'),PHP_INT_MAX);
		$current=get_option(self::OPTION,false);
		if(!is_array($current)||empty($current['captured_at']))wp_die(esc_html__('File 10 role-capability rollback evidence was not captured before commit.',VWLB_TEXT_DOMAIN));
		$deleted=delete_option(self::OPTION);if(!$deleted&&false!==get_option(self::OPTION,false))wp_die(esc_html__('File 10 role-capability rollback evidence could not be closed safely.',VWLB_TEXT_DOMAIN));
		self::$committed=true;self::$token='';
	}

	public static function activation_shutdown(){
		remove_filter('vwlb_activation_role_capabilities',array(__CLASS__,'capture_role_map'),PHP_INT_MAX);
		if(self::$committed)return;
		$record=get_option(self::OPTION,false);if(!is_array($record))return;
		$ok=self::restore($record);if($ok)delete_option(self::OPTION);
		try{do_action('vwlb_operational_failure','activation','vwlb_activation_role_caps_compensated',array('rollback_verified'=>$ok));}catch(Throwable $e){}
	}
}
