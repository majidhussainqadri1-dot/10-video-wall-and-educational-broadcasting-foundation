#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL plan-completion: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
need "Version: 1.2.5-rc1" "$P/video-wall-and-live-broadcasting.php" version
need "Requires at least: 7.0" "$P/video-wall-and-live-broadcasting.php" wordpress-baseline
need "Requires PHP: 8.3" "$P/video-wall-and-live-broadcasting.php" php-baseline
need "video-wall-live-broadcasting/v1" "$P/includes/class-vwlb-contracts.php" canonical-api
for id in F10-CEN-01 AJ-15 AJ-16 AJ-17 CV-107 CV-108 CV-109 CV-110 CV-111 CV-112 CV-113 CV-114 CV-115 CV-116 CV-117 CV-118 CV-125 CV-127 CV-128 CV-242 CV-250 CV-252 CV-262 CV-263 CV-264 CV-265 CV-266 CV-269 CV-276 CV-277 CV-278 CV-280 CV-283 CV-284 CV-285; do need "$id" "$P/includes/class-vwlb-contracts.php" "$id"; done
for id in $(seq -w 1 24); do need "F10-FUT-0${id}" "$P/includes/class-vwlb-contracts.php" "F10-FUT-$id"; done
for token in "class VWLB_Extensions" "class VWLB_Podcasts" "class VWLB_Observability" "class VWLB_Extended_REST" "class VWLB_Future_Intelligence" "class VWLB_Future_Safety" "class VWLB_Future_REST" "class VWLB_Future_Frontend"; do need "$token" "$P/includes" "$token"; done
for table in upload_sessions chapters live_attendees live_questions live_resources download_tokens creator_metrics_daily provider_health premieres podcast_series podcast_episodes future_live_config production_sources production_scenes broadcast_guests simulcast_targets broadcast_health_samples media_tracks transcript_segments video_annotations live_polls consent_links watermark_policies; do need "$table" "$P/includes" "table-$table"; done
need "1024 * 1024 * 1024" "$P/includes/class-vwlb-extensions.php" 1gb-resumable
need "vwlb_malware_scan_result" "$P/includes/class-vwlb-extensions.php" malware-scan-gate
need "Private media is stored safely" "$P/includes/class-vwlb-extensions.php" no-fake-transcode
need "vwlb_allow_legacy_attachment_ingest',false" "$P/includes/class-vwlb-media.php" media-library-fail-closed
need "VideoPremiereScheduled" "$P/includes" premiere
need "rss_xml" "$P/includes/class-vwlb-podcasts.php" podcast-rss
need "recording_consent" "$P/includes/class-vwlb-extensions.php" recording-consent
need "live_questions" "$P/includes/class-vwlb-extensions.php" live-qna
need "creator_studio" "$P/includes/class-vwlb-extensions.php" creator-studio
need "provider_failover" "$P/includes" provider-failover
need "file11_media_source_contract" "$P/includes/class-vwlb-integrations.php" file11-contract
need "file17_live_context_contract" "$P/includes/class-vwlb-integrations.php" file17-bridge
need "watch-time-alone-is-not-a-quality-score" "$P/includes/class-vwlb-extensions.php" insight-guardrail
need "data-no-autoplay=\"1\"" "$P/includes/class-vwlb-frontend.php" autoplay-off
need "restShown" "$P/assets/js/vwlb.js" wellbeing-stop
need "#087A4E" "$P/assets/css/vwlb.css" sabri-green
need "min-width:44px" "$P/assets/css/vwlb.css" touch-targets
need "prefers-reduced-motion" "$P/assets/css/vwlb.css" reduced-motion
need "[dir=\"rtl\"]" "$P/assets/css/vwlb.css" rtl
need "max-width:320px" "$P/assets/css/vwlb.css" 320-reflow
need "Idempotency-Key" "$P/includes/class-vwlb-rest.php" idempotency
need "vwlb_step_up_verified',false" "$P/includes/class-vwlb-security.php" fail-closed-stepup
need "vwlb_object_scope_authorized',false" "$P/includes/class-vwlb-security.php" object-scope
bash "$ROOT/tests/future-video-intelligence-24.sh"
! grep -R -E "(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{30,}|BEGIN (RSA|OPENSSH) PRIVATE KEY)" "$P" >/dev/null || fail secret-pattern
printf '%s\n' 'plan completion contracts PASS'
