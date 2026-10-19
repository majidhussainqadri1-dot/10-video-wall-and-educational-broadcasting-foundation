#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
require() {
  if ! grep -Eq "$1" "$2"; then
    printf 'File10↔File11 compatibility check failed: %s\n' "$3" >&2
    exit 1
  fi
}
# The File 11 RSV_File10::contract_compatible() consumer tests for
# defined('VWLB_CONTRACT_VERSION') and a nonempty value before media access.
# Advertise a stable major contract independently of runtime/schema versions.
require "define\( 'VWLB_CONTRACT_VERSION', '1' \);" "$P/video-wall-and-live-broadcasting.php" "missing File 10 shared contract version"
require "const EVENT_VERSION = 1;" "$P/includes/class-vwlb-contracts.php" "File 10 event contract changed without explicit migration"
require "add_filter\('file11_media_source_contract'" "$P/includes/class-vwlb-integrations.php" "missing File 11 media filter"
require "function reels_media_contract\(" "$P/includes/class-vwlb-extensions.php" "missing Reel media source implementation"
for method in playback progress interact; do
  require "function[[:space:]]+$method[[:space:]]*\(" "$P/includes/class-vwlb-videos.php" "missing File 11 compatibility method: $method"
done
echo "File 10 / File 11 contract-version compatibility PASS"
