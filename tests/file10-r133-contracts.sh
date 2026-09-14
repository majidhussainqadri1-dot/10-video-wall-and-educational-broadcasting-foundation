#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIVE="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-live.php"
REST="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-rest.php"

need(){ grep -F -- "$1" "$2" >/dev/null || { echo "R133/R141 contract missing: $3" >&2; exit 1; }; }

need "array('record'=>false,'publish_replay'=>false,'consent_required'=>true,'consent_version'=>'v1')" "$LIVE" r133-service-recording-default
need "array('record'=>false,'publish_replay'=>false,'consent_required'=>true)" "$REST" r133-rest-recording-default
if grep -F "array('record'=>true,'publish_replay'=>false)" "$LIVE" >/dev/null; then
  echo "R133 unsafe record-on fallback remains" >&2
  exit 1
fi

need 'function()use($event,$changes,$target,$source)' "$LIVE" r141-source-captured
need "array('source'=>\$source)" "$LIVE" r141-source-audited
if grep -F "array('source'=>'provider_reconcile')" "$LIVE" >/dev/null; then
  echo "R141 hard-coded provider lifecycle audit source remains" >&2
  exit 1
fi
php -l "$LIVE" >/dev/null

echo "R133 recording default and R141 lifecycle provenance contracts PASS"
