#!/usr/bin/env bash
# Source safety/CLI-guard tests, NOT real deployed WordPress evidence.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tools/file10-live-reality-freeze-readonly.php"
test -s "$TOOL"
php -l "$TOOL" >/dev/null
set +e
response="$(php "$TOOL" 2>&1)"
code=$?
set -e
[[ "$code" == "2" ]] || { echo "File10 G01 freeze FAIL: unauthenticated CLI did not refuse" >&2; exit 1; }
[[ "$response" == *"REFUSED: File10 deployed-state snapshot requires authorized WP-CLI."* ]] || {
  echo "File10 G01 freeze FAIL: missing unauthenticated guard response" >&2; exit 1;
}
for token in \
  "deployed_vs_repository_parity" \
  "not_compared" \
  "snapshot_only" \
  "expanded_tree_sha256" \
  "file_fingerprints" \
  "vwlb_schema_version" \
  "vwlb_ext_schema_version" \
  "vwlb_future_schema_version" \
  "vwlb_schema_migration_lock" \
  "vwlb_schema_verification_lock" \
  "SHOW TABLES LIKE" \
  "SHOW COLUMNS FROM" \
  "sample_limit_reached" \
  "patient fields"; do
  grep -qiF "$token" "$TOOL" || { echo "File10 G01 freeze FAIL: missing $token" >&2; exit 1; }
done
! grep -E '(\$wpdb->(insert|update|delete|replace)\s*\(|(update_option|delete_option|add_option|file_put_contents|wp_remote_(get|post))\s*\()' "$TOOL" >/dev/null || {
  echo "File10 G01 freeze FAIL: mutating source function found" >&2; exit 1;
}
printf '%s\n' 'File10 G01 read-only CLI snapshot contract PASS; real WP execution NOT inferred'
