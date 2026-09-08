from pathlib import Path


def read(p): return Path(p).read_text()
def write(p,s): Path(p).write_text(s)
def once(p, old, new):
    s=read(p); n=s.count(old)
    if n != 1: raise SystemExit(f'{p}: expected one occurrence, found {n}')
    write(p,s.replace(old,new,1))

# R115-02: verified rollback boundary.
p='video-wall-and-live-broadcasting/includes/class-vwlb-db.php'
once(p,
"final class VWLB_DB {\n\tpublic static function transaction( $callback ) {",
"final class VWLB_DB {\n\tprivate static function rollback_verified($cause='transaction_failure'){global $wpdb;$rolled=$wpdb->query('ROLLBACK');if(false!==$rolled)return true;$cause=sanitize_key((string)$cause);do_action('vwlb_operational_failure','database','vwlb_transaction_rollback_failed',array('cause'=>$cause));return VWLB_Helpers::error('vwlb_transaction_rollback_failed',__('File 10 could not verify that the failed database transaction was rolled back safely.',VWLB_TEXT_DOMAIN),503,array('cause'=>$cause));}\n\tpublic static function transaction( $callback ) {")
once(p,
"\t\t\tif ( is_wp_error( $result ) ) {\n\t\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t\treturn $result;\n\t\t\t}",
"\t\t\tif ( is_wp_error( $result ) ) {\n\t\t\t\t$rolled=self::rollback_verified($result->get_error_code());\n\t\t\t\treturn is_wp_error($rolled)?$rolled:$result;\n\t\t\t}")
once(p,
"\t\t\tif ( false === $committed ) {\n\t\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_commit_failed', __( 'The operation could not be committed safely.', VWLB_TEXT_DOMAIN ), 500 );\n\t\t\t}",
"\t\t\tif ( false === $committed ) {\n\t\t\t\t$rolled=self::rollback_verified('commit_failed');\n\t\t\t\tif(is_wp_error($rolled))return $rolled;\n\t\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_commit_failed', __( 'The operation could not be committed safely.', VWLB_TEXT_DOMAIN ), 500 );\n\t\t\t}")
once(p,
"\t\t} catch ( Throwable $e ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_failed', __( 'The operation could not be completed.', VWLB_TEXT_DOMAIN ), 500, array( 'exception' => get_class( $e ) ) );\n\t\t}",
"\t\t} catch ( Throwable $e ) {\n\t\t\t$rolled=self::rollback_verified('exception_'.sanitize_key(get_class($e)));\n\t\t\tif(is_wp_error($rolled))return $rolled;\n\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_failed', __( 'The operation could not be completed.', VWLB_TEXT_DOMAIN ), 500, array( 'exception' => get_class( $e ) ) );\n\t\t}")

# R115-01: all authoritative idempotency reads fail closed.
p='video-wall-and-live-broadcasting/includes/class-vwlb-security.php'
s=read(p)
s=s.replace("$row=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),ARRAY_A);if($row&&", "$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),'idempotency_begin');if(is_wp_error($row))return $row;if($row&&", 1)
s=s.replace("$row=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),ARRAY_A);if($row&&absint", "$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),'idempotency_expiry_recheck');if(is_wp_error($row))return $row;if($row&&absint", 1)
s=s.replace("$race=$wpdb->get_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),ARRAY_A);if($race&&", "$race=VWLB_DB::read_row($wpdb->prepare(\"SELECT * FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),'idempotency_insert_race');if(is_wp_error($race))return $race;if($race&&", 1)
if s.count("idempotency_begin');if(is_wp_error($row))return $row") != 1 or s.count("idempotency_expiry_recheck") != 1 or s.count("idempotency_insert_race") != 1:
    raise SystemExit('security idempotency replacements incomplete')
write(p,s)

print('R115 correction applicator completed')
