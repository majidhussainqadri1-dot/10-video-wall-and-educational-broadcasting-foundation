#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
U="$ROOT/video-wall-and-live-broadcasting/uninstall.php"
need(){ grep -F -- "$1" "$2" >/dev/null || { echo "FAIL R117: $3" >&2; exit 1; }; }
need "page_delete_failed" "$U" page-delete-verification
need "table_drop_failed" "$U" table-drop-verification
need "table_drop_unverified" "$U" table-drop-reread
need "private_entry_delete_failed" "$U" file-delete-verification
need "private_directory_delete_failed" "$U" directory-delete-verification
need "private_root_delete_failed" "$U" root-delete-verification
need "option_delete_failed" "$U" option-delete-verification
need "pattern_option_delete_failed" "$U" pattern-delete-verification
need "vwlb_uninstall_purge_failed" "$U" durable-failure-marker
need "VWLB destructive purge incomplete; residual state requires operator review." "$U" incomplete-purge-signal
need "VWLB explicit destructive purge completed after dual confirmation." "$U" success-signal
python3 - "$U" <<'PY'
import pathlib,sys
s=pathlib.Path(sys.argv[1]).read_text()
assert s.index("if ( $failures )") < s.index("VWLB explicit destructive purge completed after dual confirmation.")
assert "return;\n}\n\nerror_log( 'VWLB explicit destructive purge completed after dual confirmation.' );" in s
PY
echo "R117 destructive purge integrity contracts PASS"
