from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ACT = ROOT / 'video-wall-and-live-broadcasting/includes/class-vwlb-activator.php'
TEST = ROOT / 'tests/fresh-20-review-2-contracts.sh'
LEDGER = ROOT / 'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'

s = ACT.read_text()
old = "\t\tself::capabilities(); self::pages(); self::schedules(); VWLB_Compatibility::migrate_legacy();\n"
new = """\t\tself::capabilities();\n\t\t$pages = self::pages();\n\t\tif ( is_wp_error( $pages ) ) {\n\t\t\tdeactivate_plugins( plugin_basename( VWLB_FILE ) );\n\t\t\twp_die( esc_html( $pages->get_error_message() ) );\n\t\t}\n\t\tself::schedules(); VWLB_Compatibility::migrate_legacy();\n"""
if new not in s:
    if old not in s:
        raise SystemExit('R02 activate anchor missing')
    s = s.replace(old, new, 1)

start = s.index("\tprivate static function pages() {")
end = s.rfind("\n}")
new_pages = r'''\tprivate static function pages() {
\t\t$pages = array(
\t\t\t'videos'=>array('title'=>__('Video Wall',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_wall]'),
\t\t\t'video'=>array('title'=>__('Video',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_video]'),
\t\t\t'live'=>array('title'=>__('Live',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_live]'),
\t\t\t'channel'=>array('title'=>__('Channel',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_channel]'),
\t\t\t'studio-video'=>array('title'=>__('Video Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_video]'),
\t\t\t'studio-live'=>array('title'=>__('Live Studio',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_studio_live]'),
\t\t\t'video-history'=>array('title'=>__('Video History',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_history]'),
\t\t\t'podcasts'=>array('title'=>__('Podcasts',VWLB_TEXT_DOMAIN),'content'=>'[vwlb_podcasts]')
\t\t);
\t\t$before = get_option( 'vwlb_page_map', array() );
\t\t$snapshot = VWLB_DB::snapshot( 'activation_pages', $before );
\t\tif ( is_wp_error( $snapshot ) ) {
\t\t\treturn $snapshot;
\t\t}
\t\t$map = array();
\t\t$created = array();
\t\t$compensate = static function() use ( &$created ) {
\t\t\t$failed = array();
\t\t\tforeach ( array_reverse( $created ) as $created_id ) {
\t\t\t\tif ( ! wp_delete_post( $created_id, true ) ) {
\t\t\t\t\t$failed[] = $created_id;
\t\t\t\t}
\t\t\t}
\t\t\treturn $failed;
\t\t};
\t\tforeach ( $pages as $slug => $data ) {
\t\t\t$page = get_page_by_path( $slug );
\t\t\tif ( $page && strpos( (string) $page->post_content, '[vwlb_' ) === false ) {
\t\t\t\t$slug = 'file-10-' . $slug;
\t\t\t\t$page = get_page_by_path( $slug );
\t\t\t}
\t\t\tif ( ! $page ) {
\t\t\t\t$id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>$data['title'], 'post_name'=>$slug, 'post_content'=>$data['content'] ), true );
\t\t\t\tif ( is_wp_error( $id ) || ! $id ) {
\t\t\t\t\t$failed = $compensate();
\t\t\t\t\tif ( $failed ) {
\t\t\t\t\t\treturn VWLB_Helpers::error( 'vwlb_activation_compensation_failed', __( 'File 10 page setup failed and created pages could not all be rolled back.', VWLB_TEXT_DOMAIN ), 500, array( 'page_ids'=>$failed ) );
\t\t\t\t\t}
\t\t\t\t\treturn is_wp_error( $id ) ? $id : VWLB_Helpers::error( 'vwlb_activation_page_failed', __( 'A required File 10 page could not be created.', VWLB_TEXT_DOMAIN ), 500 );
\t\t\t\t}
\t\t\t\t$created[] = (int) $id;
\t\t\t\t$map[$slug] = (int) $id;
\t\t\t} else {
\t\t\t\t$map[$slug] = (int) $page->ID;
\t\t\t}
\t\t}
\t\t$stored = update_option( 'vwlb_page_map', $map, false );
\t\tif ( ! $stored && get_option( 'vwlb_page_map', array() ) !== $map ) {
\t\t\t$failed = $compensate();
\t\t\tif ( $failed ) {
\t\t\t\treturn VWLB_Helpers::error( 'vwlb_activation_compensation_failed', __( 'File 10 page mapping failed and created pages could not all be rolled back.', VWLB_TEXT_DOMAIN ), 500, array( 'page_ids'=>$failed ) );
\t\t\t}
\t\t\treturn VWLB_Helpers::error( 'vwlb_page_map_persist_failed', __( 'File 10 page mapping could not be recorded.', VWLB_TEXT_DOMAIN ), 500 );
\t\t}
\t\treturn true;
\t}
'''.replace('\\t', '\t')
if 'vwlb_page_map_persist_failed' not in s:
    s = s[:start] + new_pages + s[end:]
ACT.write_text(s)

checks = """\n# R02 — activation page setup proves rollback snapshot, persistence and compensation.\nneed 'is_wp_error( $snapshot )' \"$P/includes/class-vwlb-activator.php\" r02-snapshot-propagation\nneed \"vwlb_activation_compensation_failed\" \"$P/includes/class-vwlb-activator.php\" r02-compensation\nneed \"vwlb_page_map_persist_failed\" \"$P/includes/class-vwlb-activator.php\" r02-page-map\n"""
ts = TEST.read_text()
if 'r02-page-map' not in ts:
    TEST.write_text(ts + checks)

ls = LEDGER.read_text()
entry = '''## R02 — DEFECT FIXED\nActivation ignored rollback-snapshot failure, tolerated individual page-creation failures, and did not verify page-map persistence. Page setup now requires a durable pre-mutation snapshot, fails closed on page or mapping persistence failure, compensates pages created by the failed activation attempt, and propagates the error to activation before scheduling/version success. The first R02 regression-gate draft itself expanded a shell variable inside the grep pattern; that QA-only defect was corrected within R02 before product changes were accepted.\n\n'''
if '## R02 ' not in ls:
    LEDGER.write_text(ls + entry)

print('R02 correction prepared')
