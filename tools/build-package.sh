#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/packages/10-video-wall-and-educational-broadcasting-foundation-0.2.0.zip}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
cp -a "$root/video-wall" "$tmp/video-wall"
find "$tmp/video-wall" -exec touch -t 202607292328.00 {} +
mkdir -p "$(dirname "$out")"
rm -f "$out"
(
  cd "$tmp"
  find video-wall -type f -print | LC_ALL=C sort | zip -X -q "$out" -@
)
unzip -t "$out" >/dev/null
printf '%s\n' "$out"
