#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CURRENT_VERSION='1.2.5-rc1'
run_rebased_124(){ local src="$1" tmp; tmp="$(mktemp)"; sed "s/1\\.2\\.4-rc1/${CURRENT_VERSION}/g" "$src" > "$tmp"; bash "$tmp"; rm -f "$tmp"; }
find "$ROOT/video-wall-and-live-broadcasting" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb.js"
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb-future.js"
php "$ROOT/tests/unit-state-machine.php"
bash "$ROOT/tests/static-contracts.sh"
run_rebased_124 "$ROOT/tests/fresh-40-review-contracts.sh"
run_rebased_124 "$ROOT/tests/fresh-40-review-adversarial.sh"
run_rebased_124 "$ROOT/tests/fresh-20-review-contracts.sh"
bash "$ROOT/tests/fresh-20-review-2-contracts.sh"
bash "$ROOT/tests/third-fresh-20-review-contracts.sh"
bash "$ROOT/tests/fourth-fresh-20-review-contracts.sh"
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-a.zip >/dev/null
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-b.zip >/dev/null
cmp /tmp/vwlb-build-a.zip /tmp/vwlb-build-b.zip
unzip -t /tmp/vwlb-build-a.zip >/dev/null
[[ "$(unzip -Z1 /tmp/vwlb-build-a.zip | head -n1)" == video-wall-and-live-broadcasting/* ]] || { echo 'bad top folder' >&2; exit 1; }
if command -v grep >/dev/null; then ! grep -R -E "(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{30,}|BEGIN (RSA|OPENSSH) PRIVATE KEY)" "$ROOT/video-wall-and-live-broadcasting" >/dev/null; fi
echo "all File 10 automated checks PASS"
