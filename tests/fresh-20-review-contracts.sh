#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL fresh-20-review: $3" >&2; exit 1; }; }
# R01 — waiting-room capacity is serialized and attendee writes fail closed.
need "FOR UPDATE" "$P/includes/class-vwlb-extensions.php" r01-event-lock
need "vwlb_waiting_room_conflict" "$P/includes/class-vwlb-extensions.php" r01-cas
need "Waiting-room attendance could not be saved." "$P/includes/class-vwlb-extensions.php" r01-insert-check

# R02 — recording consent is locked and CAS persistence is verified.
need vwlb_recording_consent_conflict "$P/includes/class-vwlb-extensions.php" r02-consent-cas
need 'live_event_id=%d AND user_id=%d FOR UPDATE' "$P/includes/class-vwlb-extensions.php" r02-consent-lock

# R03 — live Q&A fails closed and moderation is event-scoped.
need 'Question could not be saved.' "$P/includes/class-vwlb-extensions.php" r03-question-insert
need 'CAP_MODERATE,$event,'"'"'moderate_live_question'"'"'' "$P/includes/class-vwlb-extensions.php" r03-event-scope
need vwlb_question_conflict "$P/includes/class-vwlb-extensions.php" r03-question-cas

# R04 — download tokens persist and consume quota atomically.
need 'Download token could not be stored.' "$P/includes/class-vwlb-extensions.php" r04-token-insert
need '1!==$consumed' "$P/includes/class-vwlb-extensions.php" r04-atomic-consume

# R05 — processing jobs reclaim stale running leases with an attempt CAS.
need 'status='"'"'running'"'"' AND locked_at<' "$P/includes/class-vwlb-jobs.php" r05-stale-running
need 'AND status=%s AND attempts=%d' "$P/includes/class-vwlb-jobs.php" r05-claim-cas

# R06 — job finalization is lease-bound and asset completion is transactional.
need vwlb_job_lease_lost "$P/includes/class-vwlb-jobs.php" r06-lease-finalize
need vwlb_asset_finalize_conflict "$P/includes/class-vwlb-jobs.php" r06-asset-finalize
need 'AND attempts=%d AND locked_by=%s' "$P/includes/class-vwlb-jobs.php" r06-owner-token

# R07 — outbox stale publishing leases are reclaimable and finalize by attempt token.
need 'status='"'"'publishing'"'"' AND locked_at<' "$P/includes/class-vwlb-jobs.php" r07-stale-outbox
need 'status='"'"'publishing'"'"' AND attempts=%d' "$P/includes/class-vwlb-jobs.php" r07-outbox-token

# R08 — provider reconciliation refreshes canonical live state and writes through versioned CAS.
need '$fresh=VWLB_Repository::find('"'"'live_events'"'"'' "$P/includes/class-vwlb-jobs.php" r08-fresh-live
need 'update_versioned('"'"'live_events'"'"'' "$P/includes/class-vwlb-jobs.php" r08-live-cas

# R09 — rate limiting is a single atomic upsert and fails closed on storage failure.
need vwlb_rate_limit_store_unavailable "$P/includes/class-vwlb-security.php" r09-rate-store
need 'ON DUPLICATE KEY UPDATE counter=IF' "$P/includes/class-vwlb-security.php" r09-atomic-rate

# R10 — idempotency completion/abort is verified and REST/live success cannot ignore durable replay failure.
need vwlb_idempotency_persist_failed "$P/includes/class-vwlb-security.php" r10-finish-check
need vwlb_idempotency_abort_failed "$P/includes/class-vwlb-security.php" r10-abort-check
need '$idem_done=VWLB_Security::idempotency_finish' "$P/includes/class-vwlb-live.php" r10-live-finish

# R11 — waiting-room/reminder extras are transactional, versioned and propagated by REST.
need vwlb_live_extras_conflict "$P/includes/class-vwlb-extensions.php" r11-live-extras-cas
need 'Live reminder could not be scheduled.' "$P/includes/class-vwlb-extensions.php" r11-reminder-write
need '$extras=VWLB_Extensions::schedule_live_extras' "$P/includes/class-vwlb-rest.php" r11-rest-propagation

# R12 — premiere mapping is idempotent across live-schedule replay and propagates extras failure.
need vwlb_premiere_replay_conflict "$P/includes/class-vwlb-extensions.php" r12-premiere-conflict
need 'WHERE live_event_id=%d LIMIT 1' "$P/includes/class-vwlb-extensions.php" r12-premiere-replay

# R13 — recording consent is bound to the active policy version.
need vwlb_recording_consent_version_stale "$P/includes/class-vwlb-extensions.php" r13-consent-version
need 'recording_consent=0 OR consent_version<>%s' "$P/includes/class-vwlb-extensions.php" r13-finalize-version
need '['"'"'consent_version'"'"']=VWLB_Helpers::text' "$P/includes/class-vwlb-rest.php" r13-policy-version

# R14 — live-resource creation verifies persistence before success.
need 'Live resource could not be saved.' "$P/includes/class-vwlb-extensions.php" r14-resource-insert

# R19 — runtime/build/release artifact identity advances to 1.2.2-rc1 with schema versions unchanged.
need 'Version: 1.2.2-rc1' "$P/video-wall-and-live-broadcasting.php" r19-runtime-version
need 'VWLB_VERSION'"'"', '"'"'1.2.2-rc1' "$P/video-wall-and-live-broadcasting.php" r19-runtime-constant
need 'VWLB_FUTURE_SCHEMA_VERSION'"'"', '"'"'1.2.0' "$P/video-wall-and-live-broadcasting.php" r19-schema-not-inflated
need video-wall-and-live-broadcasting-1.2.2-rc1.zip "$ROOT/tools/build-package.sh" r19-build-artifact
need file10-video-wall-live-1.2.2-rc1 "$ROOT/.github/workflows/file10-release.yml" r19-release-artifact

# R20 — final fresh review/release hygiene: no temporary mutation tooling and no stale current release identity.
[[ ! -e "$ROOT/tools/file10-current-20-review-fix.py" ]] || { echo 'FAIL fresh-20-review: r20-loader-hygiene' >&2; exit 1; }
[[ ! -e "$ROOT/.github/workflows/file10-20-review-fixer.yml" ]] || { echo 'FAIL fresh-20-review: r20-workflow-hygiene' >&2; exit 1; }
if compgen -G "$ROOT/tools/file10-r20-payload-*.txt" >/dev/null; then echo 'FAIL fresh-20-review: r20-payload-hygiene' >&2; exit 1; fi
! grep -F '1.2.1-rc1' "$ROOT/.github/workflows/file10-release.yml" >/dev/null || { echo 'FAIL fresh-20-review: r20-stale-release-identity' >&2; exit 1; }
