#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)";P="$ROOT/video-wall-and-live-broadcasting";fail(){ echo "FAIL r61-r80: $*" >&2; exit 1; };need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R61
need "class-vwlb-r61-activation-role-guard.php" "$P/video-wall-and-live-broadcasting.php" r61-autoload;need "wp_roles()" "$P/includes/class-vwlb-r61-activation-role-guard.php" r61-all-roles;need "VWLB_PUBLISH_REVIEW_ARTIFACT: '0'" "$ROOT/.github/workflows/file10-release.yml" r61-no-intermediate-artifact
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
# R69 — webhook verification and reconciliation must be exception-safe and fair across poison rows.
need "RECONCILE_CURSOR_OPTION = 'vwlb_r69_webhook_reconcile_cursor'" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-cursor;need "RECONCILE_PAGE_SIZE = 25" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-page;need "vwlb_webhook_verifier_exception" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-verifier;need "id>%d" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-fair-query;need "vwlb_webhook_reconcile_cursor_failed" "$P/includes/class-vwlb-r31-webhook-integrity.php" r69-cursor-failure;need "vwlb_r69_webhook_reconcile_cursor" "$P/uninstall.php" r69-purge
printf '%s\n' 'File 10 R61-R80 sequential contracts PASS'
