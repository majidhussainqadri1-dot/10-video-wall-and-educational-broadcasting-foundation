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
