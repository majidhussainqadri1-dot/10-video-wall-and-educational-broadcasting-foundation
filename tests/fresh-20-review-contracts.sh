#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL fresh-20-review: $3" >&2; exit 1; }; }
# R01 — waiting-room capacity is serialized and attendee writes fail closed.
need "FOR UPDATE" "$P/includes/class-vwlb-extensions.php" r01-event-lock
need "vwlb_waiting_room_conflict" "$P/includes/class-vwlb-extensions.php" r01-cas
need "Waiting-room attendance could not be saved." "$P/includes/class-vwlb-extensions.php" r01-insert-check
