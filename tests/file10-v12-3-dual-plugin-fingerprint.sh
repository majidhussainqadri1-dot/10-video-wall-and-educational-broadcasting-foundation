#!/usr/bin/env bash
# Disposable isolated fixture: tests both real File10 slug identities, not live WP.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COLLECTOR="$ROOT/tools/file10-live-reality-freeze-readonly.php"
D="$(mktemp -d)"
trap 'rm -rf "$D"' EXIT
mkdir -p "$D/wp" "$D/plugins/video-wall" "$D/plugins/video-wall-and-live-broadcasting"
printf '%s\n' '<?php /* Plugin Name: Legacy Video Wall' 'Version: 0.2.0' '*/' > "$D/plugins/video-wall/video-wall.php"
printf '%s\n' '<?php /* Plugin Name: Modern File10' 'Version: 1.2.25-rc1' '*/' > "$D/plugins/video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php"

cat > "$D/fixture.php" <<'PHP'
<?php
define( 'ABSPATH', getenv('FIXTURE_WP_ROOT').'/' );
define( 'WP_CLI', true );
define( 'WP_PLUGIN_DIR', getenv('FIXTURE_PLUGIN_ROOT') );
$GLOBALS['wp_version']='7.0.1';
$GLOBALS['wpdb'] = new class {
    public string $prefix='wp_';
    public string $last_error='';
    public function db_version(){return '10.11.0';}
    public function esc_like($s){return addcslashes($s,'_%');}
    public function prepare($sql,...$args){return $sql;}
    public function get_var($sql){return null;}
};
function get_option($name,$fallback=null){return $fallback;}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function is_plugin_active($relative){return in_array($relative,explode(',',getenv('ACTIVE_FILE10')?:''),true);}
function is_plugin_active_for_network($relative){return false;}
function get_file_data($file,$fields,$context){
    $text=file_get_contents($file);
    preg_match('/Version:\s*([^\r\n]+)/',$text,$m);
    return array('Version'=>trim($m[1]??''));
}
require getenv('COLLECTOR');
PHP

export FIXTURE_WP_ROOT="$D/wp"
export FIXTURE_PLUGIN_ROOT="$D/plugins"
export COLLECTOR
run_case() {
    local slug="$1" expected="$2" outfile="$3"
    ACTIVE_FILE10="$slug" php "$D/fixture.php" > "$outfile"
    php -r '
    $r=json_decode(file_get_contents($argv[1]),true);
    if(!is_array($r)||($r["file10_active_plugin_identity"]??"")!==$argv[2]||
       ($r["deployed_vs_repository_parity"]??"")!=="not_compared"||
       ($r["live_verification_status"]??"")!=="snapshot_only"||
       ($r["plugin"]["header_version"]??"")!=="1.2.25-rc1"||
       ($r["legacy_plugin"]["header_version"]??"")!=="0.2.0")exit(1);
    ' "$outfile" "$expected"
}
run_case 'video-wall/video-wall.php' legacy "$D/legacy.json"
run_case 'video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php' modern "$D/modern.json"
run_case 'video-wall/video-wall.php,video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php' ambiguous_both_active "$D/both.json"
run_case '' none_active "$D/neither.json"
php -r '
$r=json_decode(file_get_contents($argv[1]),true);
foreach(["plugin","legacy_plugin"] as $key){
    if(($r[$key]["fingerprint_status"]??"")!=="complete" ||
       !preg_match("/^[a-f0-9]{64}$/",(string)($r[$key]["expanded_tree_sha256"]??"")))exit(1);
}
' "$D/legacy.json"

ln -s video-wall.php "$D/plugins/video-wall/symlinked-code.php"
ACTIVE_FILE10='video-wall/video-wall.php' php "$D/fixture.php" > "$D/legacy-link.json"
php -r '
$r=json_decode(file_get_contents($argv[1]),true);
if(($r["legacy_plugin"]["fingerprint_status"]??"")!=="incomplete"||
   (!array_key_exists("expanded_tree_sha256",$r["legacy_plugin"]) || $r["legacy_plugin"]["expanded_tree_sha256"]!==null))exit(1);
' "$D/legacy-link.json"
rm "$D/plugins/video-wall/symlinked-code.php"

mv "$D/plugins/video-wall-and-live-broadcasting" "$D/modern-original"
ln -s "$D/modern-original" "$D/plugins/video-wall-and-live-broadcasting"
ACTIVE_FILE10='video-wall-and-live-broadcasting/video-wall-and-live-broadcasting.php' php "$D/fixture.php" > "$D/modern-root-link.json"
php -r '
$r=json_decode(file_get_contents($argv[1]),true);
if(($r["plugin"]["fingerprint_status"]??"")!=="incomplete_symlink_root"||
   (!array_key_exists("expanded_tree_sha256",$r["plugin"]) || $r["plugin"]["expanded_tree_sha256"]!==null))exit(1);
' "$D/modern-root-link.json"
rm "$D/plugins/video-wall-and-live-broadcasting"
mv "$D/modern-original" "$D/plugins/video-wall-and-live-broadcasting"

mv "$D/plugins/video-wall" "$D/legacy-original"
ln -s "$D/legacy-original" "$D/plugins/video-wall"
ACTIVE_FILE10='video-wall/video-wall.php' php "$D/fixture.php" > "$D/legacy-root-link.json"
php -r '
$r=json_decode(file_get_contents($argv[1]),true);
if(($r["legacy_plugin"]["fingerprint_status"]??"")!=="incomplete_symlink_root"||
   (!array_key_exists("expanded_tree_sha256",$r["legacy_plugin"]) || $r["legacy_plugin"]["expanded_tree_sha256"]!==null))exit(1);
' "$D/legacy-root-link.json"
echo 'File10 V12.3 dual-slug deployed snapshot fixture PASS: legacy, modern, both, neither, inner/root symlinks; no live evidence'
