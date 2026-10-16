#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
F="$P/includes/class-vwlb-future-intelligence.php"
A="$P/includes/class-vwlb-future-adapters.php"
R="$P/includes/class-vwlb-future-rest.php"
fail(){ echo "FAIL future-24: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }

for id in $(seq -w 1 24); do
  need "F10-FUT-0${id}" "$F" "implementation-id-$id"
  need "F10-FUT-0${id}" "$P/includes/class-vwlb-contracts.php" "contract-id-$id"
done

# 01–09: production, guest, screen/slides, DVR/latency, ingest, simulcast, redundancy, health.
need "production_sources" "$F" multicamera-sources
need "production_scenes" "$F" multicamera-scenes
need "remote_guest" "$F" guest-source
need "broadcast_guests" "$F" scoped-guests
need "'screen','slides','browser'" "$F" screen-slides
need "dvr_window_seconds" "$F" live-dvr
need "ultra_low" "$F" ultra-low-latency
need "array('rtmp','srt','webrtc')" "$F" professional-ingest
need "vwlb_protocol_unavailable" "$F" ingest-fail-closed
need "vwlb_provider_apply_future_live_policy" "$A" provider-policy-adapter
need "vwlb_provider_future_policy_unavailable" "$A" provider-policy-fail-closed
need "simulcast_targets" "$F" simulcast
need "vwlb_simulcast_secret_forbidden" "$F" no-raw-simulcast-secret
need "vwlb_simulcast_adapter_transition" "$A" simulcast-adapter
need "vwlb_simulcast_adapter_unavailable" "$A" simulcast-fail-closed
need "backup_provider" "$F" backup-stream
need "redundant_recording" "$F" redundant-recording
need "broadcast_health_samples" "$F" health-dashboard
need "packet_loss_bp" "$F" packet-loss
need "dropped_frames" "$F" dropped-frames
need "jitter_ms" "$F" jitter
need "latency_ms" "$F" latency

# 10–15: encoding/modern codecs and reviewed accessibility/language tracks.
need "content_aware_profile" "$F" content-aware-encoding
need "2160" "$F" 4k-readiness
need "'av1','h265','h264'" "$F" codec-readiness
need "low_bandwidth_required" "$F" low-bandwidth-fallback
for track in translation dub audio_description sign_language; do need "'$track'" "$F" "track-$track"; done
need "vwlb_media_track_generation_request" "$A" track-generation-adapter
need "require_human_review" "$A" generated-track-review-contract
need "Human review is required before publishing generated tracks" "$F" generated-track-human-review
need "ai_assisted" "$F" ai-assisted-candidate

# 16–18, 20–21, 24: reviewed knowledge/citation/overlay/correction/search bridge.
for kind in key_moment citation overlay correction knowledge_bridge; do need "'$kind'" "$F" "annotation-$kind"; done
need "vwlb_video_intelligence_suggestions" "$A" intelligence-suggestion-adapter
need "auto_publish'=>false" "$A" no-auto-publication
need "clinical_authority'=>false" "$A" no-ai-clinical-authority
need "source_owner" "$F" canonical-source-owner
need "source_ref" "$F" canonical-source-ref
need "vwlb_annotation_source_required" "$F" source-fail-closed
need "transcript_segments" "$F" transcript-index
need "search_transcript" "$F" search-inside-video
need "VideoTimestampCorrectionPublished" "$F" timestamp-correction-event

# 19: educational polls explicitly not diagnostic.
need "live_polls" "$F" live-polls
need "knowledge_check" "$F" knowledge-check
need "'not_diagnostic'=>true" "$F" no-diagnostic-poll
need "/live-polls/(?P<id>[A-Za-z0-9_-]+)/answers" "$R" poll-answer-endpoint

# 22: consent expiry/withdrawal must restrict media and trigger derivative purge.
need "consent_links" "$F" consent-links
need "consent_auto_restrict" "$F" consent-auto-restrict
need "vwlb_purge_media_derivative_caches" "$F" consent-cache-purge
need "reconcile_consent_expiry" "$F" consent-expiry-job

# 23: optional rights protection with explicit honesty guardrail.
need "watermark_policies" "$F" watermark-policy
need "forensic" "$F" forensic-watermark
need "not an absolute copying-prevention guarantee" "$F" watermark-honesty
need "/grant" "$R" watermark-grant

# Progressive REST and UI are available without becoming alternate canonical owners.
need "/future/capabilities" "$R" future-capabilities-endpoint
need "/future-config/apply" "$R" provider-policy-endpoint
need "/simulcast-targets/(?P<target>" "$R" simulcast-transition-endpoint
need "/generate" "$R" track-generation-endpoint
need "/intelligence/suggest" "$R" suggestion-endpoint
need "/search-inside" "$R" search-endpoint
need "/consent-links" "$R" consent-endpoint
need "[vwlb_future_video_tools]" "$P/templates/route.php" future-video-route
need "[vwlb_future_live_tools]" "$P/templates/route.php" future-live-route
need "[vwlb_future_production_studio]" "$P/templates/route.php" future-studio-route
need "min-width:44px" "$P/assets/css/vwlb-future.css" future-touch-target
need "prefers-reduced-motion" "$P/assets/css/vwlb-future.css" future-reduced-motion
need "[dir=\"rtl\"]" "$P/assets/css/vwlb-future.css" future-rtl
node --check "$P/assets/js/vwlb-future.js"

printf '%s\n' 'Future Video & Broadcasting Intelligence 24 contracts PASS'
