#!/usr/bin/env bash
# G01 deployed checksum: real isolated PHP file/symlink inputs, NO WordPress server.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FIXTURE="$(mktemp -d)"
trap 'rm -rf "$FIXTURE"' EXIT
PLUGINS="$FIXTURE/plugins"
SAMPLE="$PLUGINS/video-wall-and-live-broadcasting"
mkdir -p "$SAMPLE" "$FIXTURE/wp"
printf '%s\n' '<?php /* Plugin Name: Fake File10 */' > "$SAMPLE/video-wall-and-live-broadcasting.php"
cat > "$FIXTURE/cli-fixture.php" <<'PHP'
<?php
// Minimal fake wp-cli runtime intentionally uses a disposable directory only.
define('ABSPATH', getenv('FIXTURE_WP_ROOT') . '/');
define('WP_CLI', true);
define('WP_PLUGIN_DIR', getenv('FIXTURE_PLUGIN_ROOT'));
$GLOBALS['wp_version'] = '7.1.4';
$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public $last_error = '';
    public function db_version() { return '10.11.0'; }
    public function esc_like($text) { return addcslashes($text, '_%'); }
    public function prepare($q, ...$args) { return $q; }
    public function get_var($q) { return null; }
};
function get_option($key,$fallback=null) { return $fallback; }
function wp_json_encode($data,$flags=0) { return json_encode($data,$flags); }
require getenv('COLLECTOR');
PHP

export FIXTURE_WP_ROOT="$FIXTURE/wp"
export FIXTURE_PLUGIN_ROOT="$PLUGINS"
export COLLECTOR="$ROOT/tools/file10-live-reality-freeze-readonly.php"
php "$FIXTURE/cli-fixture.php" > "$FIXTURE/regular.json"
php -r '
  $j=json_decode(file_get_contents($argv[1]),true);
  if(!is_array($j) || ($j["plugin"]["fingerprint_status"]??"")!=="complete" ||
     !preg_match("/^[0-9a-f]{64}$/", (string)($j["plugin"]["expanded_tree_sha256"]??""))) {
    fwrite(STDERR,"File10 G01 regular-only checksum should be complete\n");exit(1);
  }
' "$FIXTURE/regular.json"

ln -s 'video-wall-and-live-broadcasting.php' "$SAMPLE/linked-active-code.php"
php "$FIXTURE/cli-fixture.php" > "$FIXTURE/symlink.json"
php -r '
  $j=json_decode(file_get_contents($argv[1]),true);
  if(!is_array($j) || ($j["plugin"]["fingerprint_status"]??"")!=="incomplete" ||
     ($j["plugin"]["expanded_tree_sha256"]??"sentinel")!==null ||
     ($j["deployed_vs_repository_parity"]??"")!=="not_compared" ||
     ($j["live_verification_status"]??"")!=="snapshot_only") {
    fwrite(STDERR,"File10 G01 symlink must invalidate complete deployed SHA and parity claim\n");exit(1);
  }
' "$FIXTURE/symlink.json"
printf '%s\n' 'File10 G01 symlink/regular-only deployed fingerprint contract PASS; no real WP live state inferred'
