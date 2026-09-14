#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIVE="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-live.php"
REST="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-rest.php"
VIDEOS="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-videos.php"
RIGHTS="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-r109-rights-consent-replay-guard.php"
FUTURE_SAFETY="$ROOT/video-wall-and-live-broadcasting/includes/class-vwlb-future-safety.php"

need(){ grep -F -- "$1" "$2" >/dev/null || { echo "R133/R141/R143/R146 contract missing: $3" >&2; exit 1; }; }

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

need '$aborted=VWLB_Security::idempotency_abort($idempotency_key,' "$VIDEOS" r143-create-abort-verified
need '$finished=VWLB_Security::idempotency_finish($idempotency_key,' "$VIDEOS" r143-create-finish-verified
need 'return is_wp_error($aborted)?$aborted:$result' "$VIDEOS" r143-create-abort-fail-closed
need 'if(is_wp_error($finished))return $finished' "$VIDEOS" r143-create-finish-fail-closed
need "VWLB_DB::read_row(\$wpdb->prepare(\"SELECT * FROM \$table WHERE id=%d FOR UPDATE\",\$id),'r143_consent_expiry_lock')" "$RIGHTS" r143-consent-lock-read
if grep -F '$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id),ARRAY_A)' "$RIGHTS" >/dev/null; then
  echo "R143 raw consent-expiry locked-row read remains" >&2
  exit 1
fi

need "try{\$resolved=apply_filters('vwlb_public_media_track_ref'" "$FUTURE_SAFETY" r146-media-track-resolver-contained
need "vwlb_public_media_track_resolver_exception" "$FUTURE_SAFETY" r146-media-track-operational-evidence
need "vwlb_media_track_delivery_failed" "$FUTURE_SAFETY" r146-media-track-typed-failure

php -l "$LIVE" >/dev/null
php -l "$VIDEOS" >/dev/null
php -l "$RIGHTS" >/dev/null
php -l "$FUTURE_SAFETY" >/dev/null

echo "R133/R141/R143/R146 regression contracts PASS"
