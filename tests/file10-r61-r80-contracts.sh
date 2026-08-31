#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)";P="$ROOT/video-wall-and-live-broadcasting";fail(){ echo "FAIL r61-r80: $*" >&2; exit 1; };need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R61
need "class-vwlb-r61-activation-role-guard.php" "$P/video-wall-and-live-broadcasting.php" r61-autoload;need "wp_roles()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-all-roles;need "VWLB_PUBLISH_REVIEW_ARTIFACT:" "$ROOT/.github/workflows/file10-release.yml" r61-artifact-gate
# R62 clean.
# R63
need "vwlb_provider_future_policy_reconcile_required" "$P/includes/class-vwlb-future-adapters.php" r63-policy;need "vwlb_simulcast_adapter_exception" "$P/includes/class-vwlb-future-adapters.php" r63-simulcast;need "vwlb_track_generation_cancel_exception" "$P/includes/class-vwlb-future-adapters.php" r63-track;need "vwlb_video_intelligence_processor_exception" "$P/includes/class-vwlb-future-adapters.php" r63-intelligence
# R64
need "VWLB_R64_Intelligence_Guard::register" "$P/video-wall-and-live-broadcasting.php" r64-register;need "intelligence/suggest" "$P/includes/class-vwlb-r64-intelligence-guard.php" r64-route
# R65
need "private static \$read_failure=false" "$P/includes/class-vwlb-repository.php" r65-state;need "VWLB_Repository::read_failed" "$P/includes/class-vwlb-r65-repository-read-guard.php" r65-guard
# R66
need "VWLB_R66_Request_DB_Guard::register" "$P/video-wall-and-live-broadcasting.php" r66-register;need "vwlb_request_database_failure" "$P/includes/class-vwlb-r66-request-db-guard.php" r66-signal
# R67
need "vwlb_playback_session_read_failed" "$P/includes/class-vwlb-r3-playback.php" r67-session-read;need "vwlb_playback_provider_exception" "$P/includes/class-vwlb-r3-playback.php" r67-provider;need "vwlb_secure_playback_grant_exception" "$P/includes/class-vwlb-r3-playback.php" r67-grant
# R68
need "vwlb_privacy_export_read_failed" "$P/includes/class-vwlb-privacy.php" r68-export-read;need "vwlb_privacy_counter_read_failed" "$P/includes/class-vwlb-privacy.php" r68-counter-read
# R69
need "RECONCILE_CURSOR_OPTION = 'vwlb_r69_webhook_reconcile_cursor'" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-cursor;need "RECONCILE_PAGE_SIZE = 25" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-page;need "vwlb_webhook_verifier_exception" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-verifier;need "id>%d" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-fair-query;need "vwlb_webhook_reconcile_cursor_failed" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-cursor-failure;need "vwlb_r69_webhook_reconcile_cursor" "$P/uninstall.php" r69-purge
# R70
need "class-vwlb-r70-upload-completion-guard.php" "$P/video-wall-and-live-broadcasting.php" r70-autoload;need "VWLB_R70_Upload_Completion_Guard::register" "$P/video-wall-and-live-broadcasting.php" r70-register;need "vwlb_upload_session_unreadable" "$P/includes/class-vwlb-r70-upload-completion-guard.php" r70-db-read;need "flock(\$fp,LOCK_EX)" "$P/includes/class-vwlb-r70-upload-completion-guard.php" r70-lock;need "vwlb_upload_checksum_unreadable" "$P/includes/class-vwlb-r70-upload-completion-guard.php" r70-hash
# R71
need "class-vwlb-r71-private-download-guard.php" "$P/video-wall-and-live-broadcasting.php" r71-autoload;need "VWLB_R71_Private_Download_Guard::register" "$P/video-wall-and-live-broadcasting.php" r71-register;need "vwlb_secure_download_required" "$P/includes/class-vwlb-r71-private-download-guard.php" r71-secure;need "vwlb_private_download_grant_exception" "$P/includes/class-vwlb-r71-private-download-guard.php" r71-exception;need "Cache-Control','private, no-store" "$P/includes/class-vwlb-r71-private-download-guard.php" r71-cache
# R72
need "class-vwlb-r72-podcast-boundary-guard.php" "$P/video-wall-and-live-broadcasting.php" r72-autoload;need "VWLB_R72_Podcast_Boundary_Guard::register" "$P/video-wall-and-live-broadcasting.php" r72-register;need "strip_numeric_ids" "$P/includes/class-vwlb-r72-podcast-boundary-guard.php" r72-opaque;need "vwlb_podcast_secure_delivery_required" "$P/includes/class-vwlb-r72-podcast-boundary-guard.php" r72-secure;need "vwlb_podcast_secure_delivery_exception" "$P/includes/class-vwlb-r72-podcast-boundary-guard.php" r72-exception
# R73
need "class-vwlb-r73-recording-consent-guard.php" "$P/video-wall-and-live-broadcasting.php" r73-autoload;need "VWLB_R73_Recording_Consent_Guard::register" "$P/video-wall-and-live-broadcasting.php" r73-register;need "vwlb_recording_consent_version_required" "$P/includes/class-vwlb-r73-recording-consent-guard.php" r73-explicit;need "vwlb_recording_consent_unverifiable" "$P/includes/class-vwlb-r73-recording-consent-guard.php" r73-failclosed
# R74
need "class-vwlb-r74-takedown-identity-guard.php" "$P/video-wall-and-live-broadcasting.php" r74-autoload;need "VWLB_R74_Takedown_Identity_Guard::register" "$P/video-wall-and-live-broadcasting.php" r74-register;need "vwlb_takedown_identity_required" "$P/includes/class-vwlb-r74-takedown-identity-guard.php" r74-identity;need "identity_approved" "$P/includes/class-vwlb-r74-takedown-identity-guard.php" r74-verified
# R75
need "class-vwlb-r75-provider-exception-boundary.php" "$P/video-wall-and-live-broadcasting.php" r75-autoload;need "VWLB_R75_Provider_Exception_Boundary::register" "$P/video-wall-and-live-broadcasting.php" r75-register;need "implements VWLB_Provider_Interface" "$P/includes/class-vwlb-r75-provider-exception-boundary.php" r75-wrapper;need "vwlb_provider_adapter_exception" "$P/includes/class-vwlb-r75-provider-exception-boundary.php" r75-error;need "catch(Throwable" "$P/includes/class-vwlb-r75-provider-exception-boundary.php" r75-catch
# R76
need "class-vwlb-r76-cleanup-durability.php" "$P/video-wall-and-live-broadcasting.php" r76-autoload;need "VWLB_R76_Cleanup_Durability::register" "$P/video-wall-and-live-broadcasting.php" r76-register;need "UPLOAD_CURSOR_OPTION='vwlb_r76_upload_cleanup_cursor'" "$P/includes/class-vwlb-r76-cleanup-durability.php" r76-cursor;need "id>%d" "$P/includes/class-vwlb-r76-cleanup-durability.php" r76-fair-query;need "vwlb_upload_cleanup_read_failed" "$P/includes/class-vwlb-r76-cleanup-durability.php" r76-read;need "vwlb_cleanup_delete_failed" "$P/includes/class-vwlb-r76-cleanup-durability.php" r76-db-failure;need "vwlb_r76_upload_cleanup_cursor" "$P/uninstall.php" r76-purge
# R77
need "class-vwlb-r77-poll-integrity.php" "$P/video-wall-and-live-broadcasting.php" r77-autoload;need "VWLB_R77_Poll_Integrity::register" "$P/video-wall-and-live-broadcasting.php" r77-register;need "MAX_OPTIONS=20" "$P/includes/class-vwlb-r77-poll-integrity.php" r77-bound;need "vwlb_poll_option_limit" "$P/includes/class-vwlb-r77-poll-integrity.php" r77-limit;need "'is_correct'=>!empty(\$a['is_correct'])" "$P/includes/class-vwlb-r77-poll-integrity.php" r77-alignment
# R78
need "class-vwlb-r78-public-delivery-guard.php" "$P/video-wall-and-live-broadcasting.php" r78-autoload;need "VWLB_R78_Public_Delivery_Guard::register" "$P/video-wall-and-live-broadcasting.php" r78-register;need "vwlb_public_podcast_feed_grant" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r78-feed-grant;need "vwlb_podcast_feed_secure_delivery_required" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r78-feed-failclosed;need "Cache-Control','private, no-store" "$P/includes/class-vwlb-r78-public-delivery-guard.php" r78-caption-cache
# R79
need "class-vwlb-r79-watermark-session-guard.php" "$P/video-wall-and-live-broadcasting.php" r79-autoload;need "VWLB_R79_Watermark_Session_Guard::register" "$P/video-wall-and-live-broadcasting.php" r79-register;need "X-VWLB-Playback-Token" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r79-playback-token;need "vwlb_watermark_session_required" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r79-session-required;need "state IN ('approved','joined')" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r79-live-session;need "Cache-Control','private, no-store" "$P/includes/class-vwlb-r79-watermark-session-guard.php" r79-cache
# R80 — historical immutable release/package identity for the completed R61-R80 cycle.
need "\"version\": \"1.2.10-rc1\"" "$ROOT/SBOM-1.2.10-rc1.json" r80-sbom-version;need "R80 final source-review closure for the R61-R80 sequential cycle" "$ROOT/SBOM-1.2.10-rc1.json" r80-sbom-boundary;need "= 1.2.10-rc1 =" "$P/readme.txt" r80-historical-changelog
printf '%s\n' 'File 10 R61-R80 sequential contracts PASS'
