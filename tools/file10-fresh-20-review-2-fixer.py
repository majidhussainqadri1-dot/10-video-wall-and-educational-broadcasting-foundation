from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DB = ROOT / 'video-wall-and-live-broadcasting/includes/class-vwlb-db.php'
TEST = ROOT / 'tests/fresh-20-review-2-contracts.sh'
RUN_ALL = ROOT / 'tests/run-all.sh'
LEDGER = ROOT / 'docs/FILE-10-SECOND-FRESH-20-REVIEW-2026-08-12.md'

def replace_once(path, old, new, label):
    s = path.read_text()
    if new in s:
        return
    if old not in s:
        raise SystemExit(f'{label} anchor missing')
    path.write_text(s.replace(old, new, 1))

old_tx = """\tpublic static function transaction( $callback ) {\n\t\tglobal $wpdb; $wpdb->query('START TRANSACTION');\n\t\ttry { $result = call_user_func($callback); if(is_wp_error($result)){ $wpdb->query('ROLLBACK'); return $result; } $wpdb->query('COMMIT'); return $result; }\n\t\tcatch ( Throwable $e ) { $wpdb->query('ROLLBACK'); return VWLB_Helpers::error('vwlb_transaction_failed',__('The operation could not be completed.',VWLB_TEXT_DOMAIN),500,array('exception'=>get_class($e))); }\n\t}\n"""
new_tx = """\tpublic static function transaction( $callback ) {\n\t\tglobal $wpdb;\n\t\t$started = $wpdb->query( 'START TRANSACTION' );\n\t\tif ( false === $started ) {\n\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_start_failed', __( 'The operation could not start a database transaction.', VWLB_TEXT_DOMAIN ), 500 );\n\t\t}\n\t\ttry {\n\t\t\t$result = call_user_func( $callback );\n\t\t\tif ( is_wp_error( $result ) ) {\n\t\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t\treturn $result;\n\t\t\t}\n\t\t\t$committed = $wpdb->query( 'COMMIT' );\n\t\t\tif ( false === $committed ) {\n\t\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_commit_failed', __( 'The operation could not be committed safely.', VWLB_TEXT_DOMAIN ), 500 );\n\t\t\t}\n\t\t\treturn $result;\n\t\t} catch ( Throwable $e ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\treturn VWLB_Helpers::error( 'vwlb_transaction_failed', __( 'The operation could not be completed.', VWLB_TEXT_DOMAIN ), 500, array( 'exception' => get_class( $e ) ) );\n\t\t}\n\t}\n"""
replace_once(DB, old_tx, new_tx, 'R01 transaction')

old_snap = "\tpublic static function snapshot( $type, $payload, $object_type='', $object_id='' ) { global $wpdb; $wpdb->insert(VWLB_Helpers::table('rollback_snapshots'),array('public_id'=>VWLB_Helpers::public_id('snap'),'snapshot_type'=>sanitize_key($type),'object_type'=>sanitize_key($object_type),'object_id'=>(string)$object_id,'payload_json'=>VWLB_Helpers::json_encode($payload),'created_by'=>get_current_user_id(),'created_at'=>VWLB_Helpers::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+90*DAY_IN_SECONDS))); return (int)$wpdb->insert_id; }\n"
new_snap = """\tpublic static function snapshot( $type, $payload, $object_type='', $object_id='' ) {\n\t\tglobal $wpdb;\n\t\t$inserted = $wpdb->insert(\n\t\t\tVWLB_Helpers::table( 'rollback_snapshots' ),\n\t\t\tarray(\n\t\t\t\t'public_id' => VWLB_Helpers::public_id( 'snap' ),\n\t\t\t\t'snapshot_type' => sanitize_key( $type ),\n\t\t\t\t'object_type' => sanitize_key( $object_type ),\n\t\t\t\t'object_id' => (string) $object_id,\n\t\t\t\t'payload_json' => VWLB_Helpers::json_encode( $payload ),\n\t\t\t\t'created_by' => get_current_user_id(),\n\t\t\t\t'created_at' => VWLB_Helpers::now(),\n\t\t\t\t'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 90 * DAY_IN_SECONDS ),\n\t\t\t)\n\t\t);\n\t\tif ( ! $inserted || ! $wpdb->insert_id ) {\n\t\t\treturn VWLB_Helpers::error( 'vwlb_snapshot_persist_failed', __( 'The rollback snapshot could not be stored.', VWLB_TEXT_DOMAIN ), 500 );\n\t\t}\n\t\treturn (int) $wpdb->insert_id;\n\t}\n"""
replace_once(DB, old_snap, new_snap, 'R01 snapshot')

if not TEST.exists():
    TEST.write_text("""#!/usr/bin/env bash\nset -euo pipefail\nROOT=\"$(cd \"$(dirname \"${BASH_SOURCE[0]}\")/..\" && pwd)\"\nP=\"$ROOT/video-wall-and-live-broadcasting\"\nneed(){ grep -R -F -- \"$1\" \"$2\" >/dev/null || { echo \"FAIL second-fresh-20: $3\" >&2; exit 1; }; }\n\n""")
checks = """# R01 — transaction boundaries and rollback snapshots fail closed.\nneed \"vwlb_transaction_start_failed\" \"$P/includes/class-vwlb-db.php\" r01-start\nneed \"vwlb_transaction_commit_failed\" \"$P/includes/class-vwlb-db.php\" r01-commit\nneed \"vwlb_snapshot_persist_failed\" \"$P/includes/class-vwlb-db.php\" r01-snapshot\n"""
ts = TEST.read_text()
if 'r01-snapshot' not in ts:
    TEST.write_text(ts + checks)

rs = RUN_ALL.read_text()
anchor = 'bash "$ROOT/tests/fresh-20-review-contracts.sh"\n'
line = 'bash "$ROOT/tests/fresh-20-review-2-contracts.sh"\n'
if line not in rs:
    if anchor not in rs:
        raise SystemExit('R01 run-all anchor missing')
    RUN_ALL.write_text(rs.replace(anchor, anchor + line, 1))

if not LEDGER.exists():
    LEDGER.write_text("""# File 10 — Second Fresh Sequential 20-Review Corrective Record\n\nDate: 2026-08-12 (Asia/Karachi)\nRepository: `majidhussainqadri1-dot/10-video-wall-and-educational-broadcasting-foundation`\nBranch: `fix/file-10-fresh-20-review-v1.2.3-rc1`\nFrozen starting source HEAD: `38b3705d4947037e5e4407fffaf7f5904a0af46c`\nGoverning basis: Consolidated Central Master Plan v3.0 + File 10 v1.1 Future Video & Broadcasting Intelligence 24 amendment.\n\nSequential law: each round reviews only the corrected state produced by the immediately preceding round. A supported defect is fixed and the full File 10 automated suite must pass before the next review begins. This ledger is repository/source evidence, not staging/live evidence.\n\n""")
ls = LEDGER.read_text()
entry = """## R01 — DEFECT FIXED\nDatabase transaction wrappers could report domain success after `START TRANSACTION` or `COMMIT` storage failure, while rollback snapshots returned an insert ID without proving persistence. Transaction start/commit and snapshot persistence now fail closed with stable errors before a caller can treat the operation as durable.\n\n"""
if '## R01 ' not in ls:
    LEDGER.write_text(ls + entry)

print('R01 correction prepared')
