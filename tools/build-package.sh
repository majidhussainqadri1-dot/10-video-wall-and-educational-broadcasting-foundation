#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE="$ROOT/video-wall-and-live-broadcasting"
MAIN="$SOURCE/video-wall-and-live-broadcasting.php"
VERSION="$(sed -n "s/.*define( 'VWLB_VERSION', '\([^']*\)' ).*/\1/p" "$MAIN" | head -n1)"
[[ -n "$VERSION" ]] || { echo 'Unable to determine VWLB_VERSION' >&2; exit 1; }
OUT="${1:-$ROOT/packages/video-wall-and-live-broadcasting-${VERSION}.zip}"
[[ "$OUT" == /* ]] || OUT="$ROOT/$OUT"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/video-wall-and-live-broadcasting" "$(dirname "$OUT")"
cp -R "$SOURCE"/. "$TMP/video-wall-and-live-broadcasting/"
find "$TMP" -exec touch -t 202609060000.00 {} +
rm -f "$OUT" "$OUT.sha256"
( cd "$TMP"; LC_ALL=C find video-wall-and-live-broadcasting -type f -print | LC_ALL=C sort | zip -X -q "$OUT" -@ )
( cd "$(dirname "$OUT")"; sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256" )
echo "$OUT"
