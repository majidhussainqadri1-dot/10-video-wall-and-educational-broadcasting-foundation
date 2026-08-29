#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CURRENT_VERSION='1.2.10-rc1'
run_rebased_124(){ local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased.XXXXXX.sh")"; sed "s/1\\.2\\.4-rc1/${CURRENT_VERSION}/g" "$src" > "$tmp"; bash "$tmp"; rm -f "$tmp"; }
run_rebased_127(){ local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased127.XXXXXX.sh")"; sed "s/1\\.2\\.7-rc1/${CURRENT_VERSION}/g" "$src" > "$tmp"; bash "$tmp"; rm -f "$tmp"; }
run_rebased_128(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased128.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.8-rc1', version)
text = text.replace('round `R59` completed', 'sequential cycle rounds `R61–R80` completed at repository source-review level')
text = text.replace('R60 remains pending', 'R80 found final release-hygiene defects')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_r21_r40(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased-r21-r40.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.7-rc1', version)
text = text.replace('Cycle baseline exact HEAD: `83558aea2e581e6f7b76084e21695989254704b7`', 'Cycle baseline exact HEAD: `7a6ff440cb54730dd6824698856b25a397978d32`')
text = text.replace('Review boundary: final sequential cycle round `R40`', 'Review boundary: sequential cycle rounds `R61–R80` completed at repository source-review level')
text = text.replace('R40 found additional package/release-hygiene defects', 'R80 found final release-hygiene defects')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
run_rebased_legacy40(){
  local src="$1" tmp; tmp="$(mktemp "$ROOT/tests/.rebased40.XXXXXX.sh")"
  python3 - "$src" "$tmp" "$CURRENT_VERSION" <<'PY'
import pathlib, sys
src, dst, version = sys.argv[1:]
text = pathlib.Path(src).read_text()
text = text.replace('1.2.4-rc1', version)
text = text.replace('if(!is_wp_error(\\$published))', 'if(is_wp_error(\\$published))return \\$published')
text = text.replace('"$P/includes/class-vwlb-live.php" r21-credential-write', '"$P/includes/class-vwlb-r46-stream-credential-durability.php" r21-credential-write')
pathlib.Path(dst).write_text(text)
PY
  bash "$tmp"; rm -f "$tmp"
}
find "$ROOT/video-wall-and-live-broadcasting" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb.js"
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb-future.js"
php "$ROOT/tests/unit-state-machine.php"
bash "$ROOT/tests/static-contracts.sh"
run_rebased_legacy40 "$ROOT/tests/fresh-40-review-contracts.sh"
run_rebased_124 "$ROOT/tests/fresh-40-review-adversarial.sh"
run_rebased_124 "$ROOT/tests/fresh-20-review-contracts.sh"
run_rebased_128 "$ROOT/tests/fresh-20-review-2-contracts.sh"
bash "$ROOT/tests/third-fresh-20-review-contracts.sh"
run_rebased_127 "$ROOT/tests/fourth-fresh-20-review-contracts.sh"
bash "$ROOT/tests/file10-sequential-20-contracts.sh"
bash "$ROOT/tests/file10-sequential-late-contracts.sh"
run_rebased_r21_r40 "$ROOT/tests/file10-r21-r40-contracts.sh"
bash "$ROOT/tests/file10-r41-r60-contracts.sh"
run_rebased_128 "$ROOT/tests/file10-r51-r60-contracts.sh"
bash "$ROOT/tests/file10-r60-contracts.sh"
bash "$ROOT/tests/file10-r61-r80-contracts.sh"
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-a.zip >/dev/null
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-b.zip >/dev/null
cmp /tmp/vwlb-build-a.zip /tmp/vwlb-build-b.zip
unzip -t /tmp/vwlb-build-a.zip >/dev/null
[[ "$(unzip -Z1 /tmp/vwlb-build-a.zip | head -n1)" == video-wall-and-live-broadcasting/* ]] || { echo 'bad top folder' >&2; exit 1; }
if command -v grep >/dev/null; then ! grep -R -E "(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{30,}|BEGIN (RSA|OPENSSH) PRIVATE KEY)" "$ROOT/video-wall-and-live-broadcasting" >/dev/null; fi
echo "all File 10 automated checks PASS"
