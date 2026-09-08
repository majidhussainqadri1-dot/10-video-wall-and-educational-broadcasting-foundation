from pathlib import Path


def read(p): return Path(p).read_text()
def write(p, s): Path(p).write_text(s)
def once(p, old, new):
    s = read(p)
    n = s.count(old)
    if n != 1:
        raise SystemExit(f'{p}: expected one occurrence, found {n}')
    write(p, s.replace(old, new, 1))

# R116-F1: private storage hardening writes must be verified and fail closed.
p = 'video-wall-and-live-broadcasting/includes/class-vwlb-extensions.php'
once(
    p,
    "\t\tforeach ( $protect as $file => $content ) {\n\t\t\t$path = trailingslashit( $base ) . $file;\n\t\t\tif ( ! file_exists( $path ) ) {\n\t\t\t\t@file_put_contents( $path, $content, LOCK_EX );\n\t\t\t}\n\t\t}\n\t\treturn $base;",
    "\t\tforeach ( $protect as $file => $content ) {\n\t\t\t$path = trailingslashit( $base ) . $file;\n\t\t\tif ( ! file_exists( $path ) ) {\n\t\t\t\t$written = file_put_contents( $path, $content, LOCK_EX );\n\t\t\t\tif ( false === $written ) {\n\t\t\t\t\tdo_action( 'vwlb_operational_failure', 'private_storage', 'vwlb_private_storage_protection_failed', array( 'file' => $file ) );\n\t\t\t\t\treturn VWLB_Helpers::error( 'vwlb_private_storage_protection_failed', __( 'Private media storage protection could not be written safely.', VWLB_TEXT_DOMAIN ), 503, array( 'file' => $file ) );\n\t\t\t\t}\n\t\t\t}\n\t\t\t$actual = file_get_contents( $path );\n\t\t\tif ( false === $actual || ! hash_equals( hash( 'sha256', $content ), hash( 'sha256', $actual ) ) ) {\n\t\t\t\tdo_action( 'vwlb_operational_failure', 'private_storage', 'vwlb_private_storage_protection_unverified', array( 'file' => $file ) );\n\t\t\t\treturn VWLB_Helpers::error( 'vwlb_private_storage_protection_unverified', __( 'Private media storage protection could not be verified safely.', VWLB_TEXT_DOMAIN ), 503, array( 'file' => $file ) );\n\t\t\t}\n\t\t}\n\t\treturn $base;"
)

# R116-F2: remaining authoritative verification reads must use the fail-closed DB helper.
p = 'video-wall-and-live-broadcasting/includes/class-vwlb-security.php'
once(
    p,
    "$row=$wpdb->get_row($wpdb->prepare(\"SELECT counter,window_ends_at FROM $table WHERE limit_key=%s\",$key),ARRAY_A);if(!$row)return VWLB_Helpers::error('vwlb_rate_limit_store_unavailable',__('Request throttling state could not be verified.',VWLB_TEXT_DOMAIN),503);",
    "$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT counter,window_ends_at FROM $table WHERE limit_key=%s\",$key),'rate_limit_verify');if(is_wp_error($row))return $row;if(!$row)return VWLB_Helpers::error('vwlb_rate_limit_store_unavailable',__('Request throttling state could not be verified.',VWLB_TEXT_DOMAIN),503);"
)
once(
    p,
    "$row=$wpdb->get_row($wpdb->prepare(\"SELECT status,response_json FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),ARRAY_A);if($row&&'complete'===$row['status']&&hash_equals((string)$row['response_json'],$encoded))return true;",
    "$row=VWLB_DB::read_row($wpdb->prepare(\"SELECT status,response_json FROM $table WHERE idempotency_key=%s AND scope=%s\",$key,$scope),'idempotency_finish_verify');if(is_wp_error($row))return $row;if($row&&'complete'===$row['status']&&hash_equals((string)$row['response_json'],$encoded))return true;"
)

# Regression reconciliation: R19's durable post-delete re-read contract remains required,
# but R115/R116 hardening moved authoritative reads behind the fail-closed helper.
p = 'tests/file10-sequential-late-contracts.sh'
once(
    p,
    'need "\\$row=\\$wpdb->get_row" "$P/includes/class-vwlb-security.php" r19-post-delete-reread',
    'need "idempotency_expiry_recheck" "$P/includes/class-vwlb-security.php" r19-post-delete-reread'
)

print('R116 correction applicator completed')
