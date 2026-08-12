from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ACT = ROOT / 'video-wall-and-live-broadcasting/includes/class-vwlb-activator.php'
TEST = ROOT / 'tests/fresh-20-review-2-contracts.sh'
F40 = ROOT / 'tests/fresh-40-review-contracts.sh'
LEDGER = ROOT / 'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'

s = ACT.read_text()
start = s.index("\tpublic static function reconcile_schema() {")
end = s.index("\n\tpublic static function deactivate()", start)
new_block = r'''\tprivate static function delete_migration_lock_if_matches( $expected ) {
\t\tglobal $wpdb;
\t\t$deleted = $wpdb->query(
\t\t\t$wpdb->prepare(
\t\t\t\t"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
\t\t\t\tself::MIGRATION_LOCK,
\t\t\t\t(string) $expected
\t\t\t)
\t\t);
\t\tif ( 1 === $deleted ) {
\t\t\twp_cache_delete( self::MIGRATION_LOCK, 'options' );
\t\t\twp_cache_delete( 'notoptions', 'options' );
\t\t\twp_cache_delete( 'alloptions', 'options' );
\t\t\treturn true;
\t\t}
\t\treturn false;
\t}

\tpublic static function reconcile_schema() {
\t\t$token = time() . '|' . wp_generate_uuid4();
\t\t$acquired = add_option( self::MIGRATION_LOCK, $token, '', false );
\t\tif ( ! $acquired ) {
\t\t\t$current = (string) get_option( self::MIGRATION_LOCK, '' );
\t\t\t$parts = explode( '|', $current, 2 );
\t\t\t$locked_at = absint( $parts[0] ?? 0 );
\t\t\tif ( $locked_at && ( time() - $locked_at ) > self::MIGRATION_LOCK_TTL ) {
\t\t\t\tif ( self::delete_migration_lock_if_matches( $current ) ) {
\t\t\t\t\t$acquired = add_option( self::MIGRATION_LOCK, $token, '', false );
\t\t\t\t}
\t\t\t}
\t\t}
\t\tif ( ! $acquired ) {
\t\t\treturn VWLB_Helpers::error( 'vwlb_schema_migration_busy', __( 'File 10 schema migration is already in progress. Retry shortly.', VWLB_TEXT_DOMAIN ), 503 );
\t\t}
\t\ttry {
\t\t\tif ( get_option( 'vwlb_schema_version' ) !== VWLB_SCHEMA_VERSION ) {
\t\t\t\t$result = VWLB_DB::install_schema();
\t\t\t\tif ( is_wp_error( $result ) ) return $result;
\t\t\t}
\t\t\tif ( get_option( VWLB_Extensions::OPTION ) !== VWLB_EXT_SCHEMA_VERSION ) {
\t\t\t\t$result = VWLB_Extensions::install_schema();
\t\t\t\tif ( is_wp_error( $result ) ) return $result;
\t\t\t}
\t\t\tif ( get_option( VWLB_Future_Intelligence::OPTION ) !== VWLB_FUTURE_SCHEMA_VERSION ) {
\t\t\t\t$result = VWLB_Future_Intelligence::install_schema();
\t\t\t\tif ( is_wp_error( $result ) ) return $result;
\t\t\t}
\t\t\treturn true;
\t\t} catch ( Throwable $e ) {
\t\t\treturn VWLB_Helpers::error( 'vwlb_schema_migration_failed', __( 'File 10 schema migration failed safely.', VWLB_TEXT_DOMAIN ), 500, array( 'exception'=>get_class( $e ) ) );
\t\t} finally {
\t\t\tself::delete_migration_lock_if_matches( $token );
\t\t}
\t}
'''.replace('\\t','\t')
if 'delete_migration_lock_if_matches' not in s:
    s = s[:start] + new_block + s[end:]
ACT.write_text(s)

f = F40.read_text()
old_gate = 'need "is_wp_error(\\$result)" "$P/includes/class-vwlb-activator.php" r36-reconcile-fail-closed'
new_gate = "need 'is_wp_error( $result )' \"$P/includes/class-vwlb-activator.php\" r36-reconcile-fail-closed"
if old_gate in f:
    F40.write_text(f.replace(old_gate, new_gate, 1))
elif new_gate not in f:
    raise SystemExit('R03 historical migration regression anchor missing')

checks = """\n# R03 — migration lock takeover and release are owner-token/compare-and-delete bound.\nneed \"delete_migration_lock_if_matches\" \"$P/includes/class-vwlb-activator.php\" r03-lock-helper\nneed \"option_name=%s AND option_value=%s\" \"$P/includes/class-vwlb-activator.php\" r03-lock-cas\nneed 'self::delete_migration_lock_if_matches( $token )' \"$P/includes/class-vwlb-activator.php\" r03-owner-release\n"""
ts = TEST.read_text()
if 'r03-owner-release' not in ts:
    TEST.write_text(ts + checks)

ls = LEDGER.read_text()
entry = '''## R03 — DEFECT FIXED\nThe migration lock stored only a timestamp. After TTL expiry a second upgrader could take over, while the first upgrader's unconditional `finally` deletion could then remove the new owner's lock and permit overlapping schema work. The lock now carries a unique owner token, stale takeover uses an exact value compare-and-delete, and release removes only the lock owned by the current upgrader. A historical whitespace-sensitive static assertion was updated within R03 after it rejected the semantically stronger fail-closed code.\n\n'''
if '## R03 ' not in ls:
    LEDGER.write_text(ls + entry)

print('R03 correction prepared')
