<?php
defined( 'ABSPATH' ) || exit;
final class VWLB_Activator {
	const MIGRATION_LOCK = 'vwlb_schema_migration_lock';
	const MIGRATION_LOCK_TTL = 300;

	public static function activate() {
		if ( version_compare(PHP_VERSION,'8.3','<') ) { deactivate_plugins(plugin_basename(VWLB_FILE)); wp_die(esc_html__('File 10 requires PHP 8.3 or newer.',VWLB_TEXT_DOMAIN)); }
		if ( isset($GLOBALS['wp_version']) && version_compare((string)$GLOBALS['wp_version'],'7.0','<') ) { deactivate_plugins(plugin_basename(VWLB_FILE)); wp_die(esc_html__('File 10 requires WordPress 7.0 or newer.',VWLB_TEXT_DOMAIN)); }
		$migration=self::reconcile_schema();
		if(is_wp_error($migration)){deactivate_plugins(plugin_basename(VWLB_FILE));wp_die(esc_html($migration->get_error_message()));}
		self::capabilities();
		$pages = self::pages();
		if ( is_wp_error( $pages ) ) {
			deactivate_plugins( plugin_basename( VWLB_FILE ) );
			wp_die( esc_html( $pages->get_error_message() ) );
		}
		$scheduled=self::schedules();if(is_wp_error($scheduled)){deactivate_plugins(plugin_basename(VWLB_FILE));wp_die(esc_html($scheduled->get_error_message()));}$legacy=VWLB_Compatibility::migrate_legacy();if(is_wp_error($legacy)){deactivate_plugins(plugin_basename(VWLB_FILE));wp_die(esc_html($legacy->get_error_message()));}
		update_option('vwlb_version',VWLB_VERSION,false); update_option('vwlb_safe_mode',0,false); flush_rewrite_rules(false);
	}

	/**
	 * Serialize base, extension and Future schema reconciliation. WordPress options have
	 * a unique key, so add_option() is the cross-request compare-and-set primitive here.
	 * A bounded stale-lock takeover prevents a crashed upgrader from wedging File 10.
	 */
	private static function delete_migration_lock_if_matches( $expected ) {
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				self::MIGRATION_LOCK,
				(string) $expected
			)
		);
		if ( 1 === $deleted ) {
			wp_cache_delete( self::MIGRATION_LOCK, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			return true;
		}
		return false;
	}

	public static function reconcile_schema() {
		$token = time() . '|' . wp_generate_uuid4();
		$acquired = add_option( self::MIGRATION_LOCK, $token, '', false );
		if ( ! $acquired ) {
			$current = (string) get_option( self::MIGRATION_LOCK, '' );
			$parts = explode( '|', $current, 2 );
			$locked_at = absint( $parts[0] ?? 0 );
			if ( $locked_at && ( time() - $locked_at ) > self::MIGRATION_LOCK_TTL ) {
				if ( self::delete_migration_lock_if_matches( $current ) ) {
					$acquired = add_option( self::MIGRATION_LOCK, $token, '', false );
				}
			}
		}
		if ( ! $acquired ) {
			return VWLB_Helpers::error( 'vwlb_schema_migration_busy', __( 'File 10 schema migration is already in progress. Retry shortly.', VWLB_TEXT_DOMAIN ), 503 );
		}
		try {
			if ( get_option( 'vwlb_schema_version' ) !== VWLB_SCHEMA_VERSION ) {
				$result = VWLB_DB::install_schema();
				if ( is_wp_error( $result ) ) return $result;
			}
			if ( get_option( VWLB_Extensions::OPTION ) !== VWLB_EXT_SCHEMA_VERSION ) {
				$result = VWLB_Extensions::install_schema();
				if ( is_wp_error( $result ) ) return $result;
			}
			if ( get_option( VWLB_Future_Intelligence::OPTION ) !== VWLB_FUTURE_SCHEMA_VERSION ) {
				$result = VWLB_Future_Intelligence::install_schema();
				if ( is_wp_error( $result ) ) return $result;
			}
			return true;
		} catch ( Throwable $e ) {
			return VWLB_Helpers::error( 'vwlb_schema_migration_failed', __( 'File 10 schema migration failed safely.', VWLB_TEXT_DOMAIN ), 500, array( 'exception'=>get_class( $e ) ) );
		} finally {
			self::delete_migration_lock_if_matches( $token );
		}
	}

	public static function deactivate() { foreach(array('vwlb_process_jobs','vwlb_publish_outbox','vwlb_reconcile_states','vwlb_cleanup') as $hook){ wp_clear_scheduled_hook($hook); } flush_rewrite_rules(false); }
	private static function capabilities() {
		$roles=array('administrator'=>array(VWLB_Contracts::CAP_SUBMIT,VWLB_Contracts::CAP_PUBLISH,VWLB_Contracts::CAP_BROADCAST,VWLB_Contracts::CAP_MODERATE,VWLB_Contracts::CAP_REVIEW,VWLB_Contracts::CAP_OPERATE,VWLB_Contracts::CAP_MANAGE,VWLB_Contracts::CAP_DIAGNOSTICS));
		$roles=(array)apply_filters('vwlb_activation_role_capabilities',$roles);
		foreach($roles as $name=>$caps){$role=get_role($name);if($role){foreach($caps as $cap){$role->add_cap($cap);}}}
	}
	public static function schedules() {
		add_filter('cron_schedules',array(__CLASS__,'cron_schedules'));$defs=array(array('vwlb_process_jobs',60,'vwlb_five_minutes'),array('vwlb_publish_outbox',90,'vwlb_five_minutes'),array('vwlb_reconcile_states',120,'hourly'),array('vwlb_cleanup',300,'daily'));foreach($defs as $def){[$hook,$delay,$recurrence]=$def;if(!wp_next_scheduled($hook)){$scheduled=wp_schedule_event(time()+$delay,$recurrence,$hook,array(),true);if(is_wp_error($scheduled)||false===$scheduled)return is_wp_error($scheduled)?$scheduled:VWLB_Helpers::error('vwlb_cron_schedule_failed',__('A required File 10 background worker could not be scheduled.',VWLB_TEXT_DOMAIN),500,array('hook'=>$hook));}if(!wp_next_scheduled($hook))return VWLB_Helpers::error('vwlb_cron_schedule_unverified',__('A required File 10 background worker schedule could not be verified.',VWLB_TEXT_DOMAIN),500,array('hook'=>$hook));}return true;
	}
	public static function cron_schedules($s){$s['vwlb_five_minutes']=array('interval'=>300,'display'=>'Every five minutes');return $s;}
	private static function pages() {
		$pages = array(
			'videos'=>array('title'=>__('Video Wall',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_wall]'),
			'video'=>array('title'=>__('Video',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_video]'),
			'live'=>array('title'=>__('Live',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_live]'),
			'channel'=>array('title'=>__('Channel',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_channel]'),
			'studio-video'=>array('title'=>__('Video Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_video]'),
			'studio-live'=>array('title'=>__('Live Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_live]'),
			'video-history'=>array('title'=>__('Video History',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_history]'),
			'podcasts'=>array('title'=>__('Podcasts',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_podcasts]')
		);
		$before = get_option( 'vwlb_page_map', array() );
		$snapshot = VWLB_DB::snapshot( 'activation_pages', $before );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$map = array();
		$created = array();
		$compensate = static function() use ( &$created ) {
			$failed = array();
			foreach ( array_reverse( $created ) as $created_id ) {
				if ( ! wp_delete_post( $created_id, true ) ) {
					$failed[] = $created_id;
				}
			}
			return $failed;
		};
		foreach ( $pages as $slug => $data ) {
			$page = get_page_by_path( $slug );
			if ( $page && strpos( (string) $page->post_content, '[vwlb_' ) === false ) {
				$slug = 'file-10-' . $slug;
				$page = get_page_by_path( $slug );
			}
			if ( ! $page ) {
				$id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>$data['title'], 'post_name'=>$slug, 'post_content'=>$data['content'] ), true );
				if ( is_wp_error( $id ) || ! $id ) {
					$failed = $compensate();
					if ( $failed ) {
						return VWLB_Helpers::error( 'vwlb_activation_compensation_failed', __( 'File 10 page setup failed and created pages could not all be rolled back.', VWLB_TEXT_DOMAIN ), 500, array( 'page_ids'=>$failed ) );
					}
					return is_wp_error( $id ) ? $id : VWLB_Helpers::error( 'vwlb_activation_page_failed', __( 'A required File 10 page could not be created.', VWLB_TEXT_DOMAIN ), 500 );
				}
				$created[] = (int) $id;
				$map[$slug] = (int) $id;
			} else {
				$map[$slug] = (int) $page->ID;
			}
		}
		$stored = update_option( 'vwlb_page_map', $map, false );
		if ( ! $stored && get_option( 'vwlb_page_map', array() ) !== $map ) {
			$failed = $compensate();
			if ( $failed ) {
				return VWLB_Helpers::error( 'vwlb_activation_compensation_failed', __( 'File 10 page mapping failed and created pages could not all be rolled back.', VWLB_TEXT_DOMAIN ), 500, array( 'page_ids'=>$failed ) );
			}
			return VWLB_Helpers::error( 'vwlb_page_map_persist_failed', __( 'File 10 page mapping could not be recorded.', VWLB_TEXT_DOMAIN ), 500 );
		}
		return true;
	}

}
