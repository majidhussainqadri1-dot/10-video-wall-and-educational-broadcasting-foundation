from pathlib import Path

db=Path('video-wall-and-live-broadcasting/includes/class-vwlb-db.php')
ext=Path('video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php')
fut=Path('video-wall-and-live-broadcasting/includes/class-vwlb-future-intelligence.php')
act=Path('video-wall-and-live-broadcasting/includes/class-vwlb-activator.php')
reg=Path('tests/fresh-40-review-contracts.sh')

d=db.read_text()
old="\tpublic static function install_schema() { require_once ABSPATH.'wp-admin/includes/upgrade.php'; foreach(self::schema_sql() as $sql){ dbDelta($sql); } update_option('vwlb_schema_version',VWLB_SCHEMA_VERSION,false); }\n"
new="\tpublic static function verify_schema_sql($sql){global $wpdb;foreach((array)$sql as $statement){if(!preg_match('/CREATE\\s+TABLE\\s+([^\\s(]+)/i',(string)$statement,$m))continue;$table=trim($m[1],'`');$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if($found!==$table)return VWLB_Helpers::error('vwlb_schema_incomplete',__('A required File 10 database table is missing after migration.',VWLB_TEXT_DOMAIN),500,array('table'=>$table));}return true;}\n\tpublic static function install_schema() { require_once ABSPATH.'wp-admin/includes/upgrade.php'; $sql=self::schema_sql(); foreach($sql as $statement){ dbDelta($statement); } $verified=self::verify_schema_sql($sql);if(is_wp_error($verified))return $verified;if(!update_option('vwlb_schema_version',VWLB_SCHEMA_VERSION,false)&&get_option('vwlb_schema_version')!==VWLB_SCHEMA_VERSION)return VWLB_Helpers::error('vwlb_schema_version_persist_failed',__('File 10 schema version could not be recorded.',VWLB_TEXT_DOMAIN),500);return true; }\n"
if old in d:d=d.replace(old,new,1)
elif new not in d:raise SystemExit('R36 base install pattern missing')
db.write_text(d)

e=ext.read_text()
old_e="\t\tforeach ( $sql as $statement ) {\n\t\t\tdbDelta( $statement );\n\t\t}\n\t\tVWLB_Podcasts::install_schema();\n\t\tself::ensure_private_dir();\n\t\tupdate_option( self::OPTION, VWLB_EXT_SCHEMA_VERSION, false );\n\t\tVWLB_Helpers::audit( 'system', 10, 'extension_schema_upgrade', '', VWLB_EXT_SCHEMA_VERSION, 'File 10 extension schema reconciled.' );\n\t}\n"
new_e="\t\tforeach ( $sql as $statement ) {\n\t\t\tdbDelta( $statement );\n\t\t}\n\t\t$verified=VWLB_DB::verify_schema_sql($sql);if(is_wp_error($verified))return $verified;$podcasts=VWLB_Podcasts::install_schema();if(is_wp_error($podcasts))return $podcasts;$private=self::ensure_private_dir();if(is_wp_error($private))return $private;if(!update_option( self::OPTION, VWLB_EXT_SCHEMA_VERSION, false )&&get_option( self::OPTION )!==VWLB_EXT_SCHEMA_VERSION)return VWLB_Helpers::error('vwlb_schema_version_persist_failed',__('File 10 extension schema version could not be recorded.',VWLB_TEXT_DOMAIN),500);\n\t\tVWLB_Helpers::audit( 'system', 10, 'extension_schema_upgrade', '', VWLB_EXT_SCHEMA_VERSION, 'File 10 extension schema reconciled.' );return true;\n\t}\n"
if old_e in e:e=e.replace(old_e,new_e,1)
elif new_e not in e:raise SystemExit('R36 extension install pattern missing')
ext.write_text(e)

f=fut.read_text()
old_f="\t\tforeach ( $sql as $statement ) {\n\t\t\tdbDelta( $statement );\n\t\t}\n\t\tupdate_option( self::OPTION, self::SCHEMA, false );\n\t\tVWLB_Helpers::audit( 'system', 10, 'future_schema_upgrade', '', self::SCHEMA, 'File 10 Future Video & Broadcasting Intelligence schema reconciled.' );\n\t}\n"
new_f="\t\tforeach ( $sql as $statement ) {\n\t\t\tdbDelta( $statement );\n\t\t}\n\t\t$verified=VWLB_DB::verify_schema_sql($sql);if(is_wp_error($verified))return $verified;if(!update_option( self::OPTION, self::SCHEMA, false )&&get_option( self::OPTION )!==self::SCHEMA)return VWLB_Helpers::error('vwlb_schema_version_persist_failed',__('File 10 Future schema version could not be recorded.',VWLB_TEXT_DOMAIN),500);\n\t\tVWLB_Helpers::audit( 'system', 10, 'future_schema_upgrade', '', self::SCHEMA, 'File 10 Future Video & Broadcasting Intelligence schema reconciled.' );return true;\n\t}\n"
if old_f in f:f=f.replace(old_f,new_f,1)
elif new_f not in f:raise SystemExit('R36 future install pattern missing')
fut.write_text(f)

a=act.read_text()
old_a="\t\t\tif(get_option('vwlb_schema_version')!==VWLB_SCHEMA_VERSION)VWLB_DB::install_schema();\n\t\t\tif(get_option(VWLB_Extensions::OPTION)!==VWLB_EXT_SCHEMA_VERSION)VWLB_Extensions::install_schema();\n\t\t\tif(get_option(VWLB_Future_Intelligence::OPTION)!==VWLB_FUTURE_SCHEMA_VERSION)VWLB_Future_Intelligence::install_schema();\n\t\t\treturn true;"
new_a="\t\t\tif(get_option('vwlb_schema_version')!==VWLB_SCHEMA_VERSION){$result=VWLB_DB::install_schema();if(is_wp_error($result))return $result;}\n\t\t\tif(get_option(VWLB_Extensions::OPTION)!==VWLB_EXT_SCHEMA_VERSION){$result=VWLB_Extensions::install_schema();if(is_wp_error($result))return $result;}\n\t\t\tif(get_option(VWLB_Future_Intelligence::OPTION)!==VWLB_FUTURE_SCHEMA_VERSION){$result=VWLB_Future_Intelligence::install_schema();if(is_wp_error($result))return $result;}\n\t\t\treturn true;"
if old_a in a:a=a.replace(old_a,new_a,1)
elif new_a not in a:raise SystemExit('R36 activator reconciliation pattern missing')
act.write_text(a)

r=reg.read_text();marker="""# R36 — migration versions advance only after required tables and version-option persistence are verified.\nneed \"verify_schema_sql\" \"$P/includes/class-vwlb-db.php\" r36-verify-helper\nneed \"SHOW TABLES LIKE\" \"$P/includes/class-vwlb-db.php\" r36-table-verify\nneed \"vwlb_schema_version_persist_failed\" \"$P/includes\" r36-version-persist\nneed \"is_wp_error(\$result)\" \"$P/includes/class-vwlb-activator.php\" r36-reconcile-fail-closed\n"""
if '# R36 —' not in r:r=r.replace("printf '%s\\n' 'fresh 40-review regression contracts PASS'\n",marker+"printf '%s\\n' 'fresh 40-review regression contracts PASS'\n")
reg.write_text(r)
