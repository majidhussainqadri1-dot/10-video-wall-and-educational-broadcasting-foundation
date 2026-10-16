#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
find "$ROOT/video-wall-and-live-broadcasting" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
node --check "$ROOT/video-wall-and-live-broadcasting/assets/js/vwlb.js"
php "$ROOT/tests/unit-state-machine.php"
bash "$ROOT/tests/static-contracts.sh"
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-a.zip >/dev/null
bash "$ROOT/tools/build-package.sh" /tmp/vwlb-build-b.zip >/dev/null
cmp /tmp/vwlb-build-a.zip /tmp/vwlb-build-b.zip
unzip -t /tmp/vwlb-build-a.zip >/dev/null
[[ "$(unzip -Z1 /tmp/vwlb-build-a.zip | head -n1)" == video-wall-and-live-broadcasting/* ]] || { echo 'bad top folder' >&2; exit 1; }
echo "all File 10 automated checks PASS"
