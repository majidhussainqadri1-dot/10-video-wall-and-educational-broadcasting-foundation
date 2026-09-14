#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIVE="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-live.php"
REST="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-rest.php"

need(){ grep -F -- "$1" "$2" >/dev/null || { echo "R133 contract missing: $3" >&2; exit 1; }; }

need "array('record'=>false,'publish_replay'=>false,'consent_required'=>true,'consent_version'=>'v1')" "$LIVE" r133-service-recording-default
need "array('record'=>false,'publish_replay'=>false,'consent_required'=>true)" "$REST" r133-rest-recording-default
if grep -F "array('record'=>true,'publish_replay'=>false)" "$LIVE" >/dev/null; then
  echo "R133 unsafe record-on fallback remains" >&2
  exit 1
fi
php -l "$LIVE" >/dev/null

echo "R133 recording default contracts PASS"
