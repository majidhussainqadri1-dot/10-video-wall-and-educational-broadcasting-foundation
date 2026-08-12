#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
need(){ grep -R -F -- "$1" "$2" >/dev/null || { echo "FAIL second-fresh-20: $3" >&2; exit 1; }; }

# R01 — transaction boundaries and rollback snapshots fail closed.
need "vwlb_transaction_start_failed" "$P/includes/class-vwlb-db.php" r01-start
need "vwlb_transaction_commit_failed" "$P/includes/class-vwlb-db.php" r01-commit
need "vwlb_snapshot_persist_failed" "$P/includes/class-vwlb-db.php" r01-snapshot

# R02 — activation page setup proves rollback snapshot, persistence and compensation.
need 'is_wp_error( $snapshot )' "$P/includes/class-vwlb-activator.php" r02-snapshot-propagation
need "vwlb_activation_compensation_failed" "$P/includes/class-vwlb-activator.php" r02-compensation
need "vwlb_page_map_persist_failed" "$P/includes/class-vwlb-activator.php" r02-page-map

# R03 — migration lock takeover and release are owner-token/compare-and-delete bound.
need "delete_migration_lock_if_matches" "$P/includes/class-vwlb-activator.php" r03-lock-helper
need "option_name=%s AND option_value=%s" "$P/includes/class-vwlb-activator.php" r03-lock-cas
need 'self::delete_migration_lock_if_matches( $token )' "$P/includes/class-vwlb-activator.php" r03-owner-release

# R04 — source/scene edits require the caller's current optimistic version.
need "Production source changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-source-client-version
need "Scene changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r04-scene-client-version
need 'version'=>\$expected_version "$P/includes/class-vwlb-future-intelligence.php" r04-cas-version

# R05 — simulcast target edits require the caller current version.
need "Simulcast target changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r05-target-client-version
need "expected_version=absint" "$P/includes/class-vwlb-future-intelligence.php" r05-target-version-parse

# R06 — provider failure paths must durably leave simulcast transitioning state or demand reconciliation.
need "provider failed and local failure state could not be finalized" "$P/includes/class-vwlb-future-adapters.php" r06-provider-failure-persist
need "provider was unavailable and local failure state could not be finalized" "$P/includes/class-vwlb-future-adapters.php" r06-unavailable-persist
need "'status'=>'transitioning'" "$P/includes/class-vwlb-future-adapters.php" r06-failure-lease-bound

# R07 — public poll answers use opaque option IDs only; internal numeric PKs are not accepted.
need "WHERE poll_id=%d AND public_id=%s" "$P/includes/class-vwlb-future-intelligence.php" r07-public-option-id
forbid(){ ! grep -F -- "$1" "$2" >/dev/null || { echo "FAIL second-fresh-20: $3" >&2; exit 1; }; }
forbid "ctype_digit(\$raw)" "$P/includes/class-vwlb-future-intelligence.php" r07-no-numeric-pk-answer

# R08 — reviewed/published Future tracks have a public-safe viewer delivery contract.
need "public static function published_tracks" "$P/includes/class-vwlb-future-intelligence.php" r08-published-track-contract
need "AND status=%s" "$P/includes/class-vwlb-future-intelligence.php" r08-only-published
need "vwlb_public_media_track_ref" "$P/includes/class-vwlb-future-intelligence.php" r08-provider-resolution
need "media_tracks" "$P/includes/class-vwlb-rest.php" r08-rest-delivery

# R09 — public annotation listing exposes only explicitly published records.
need "\"('published')\"" "$P/includes/class-vwlb-future-intelligence.php" r09-public-published-only

# R10 — draft polls are not publicly readable before explicit open/close lifecycle.
need "future_poll_preview" "$P/includes/class-vwlb-future-intelligence.php" r10-poll-preview-guard
need "array('open','closed')" "$P/includes/class-vwlb-future-intelligence.php" r10-public-poll-status

# R11 — consent updates must carry the client-observed version.
need "Consent record changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r11-consent-client-version

# R12 — active consent cannot enter already expired.
need "Active consent expiry must be in the future." "$P/includes/class-vwlb-future-intelligence.php" r12-active-expiry

# R13 — correction publication fact is tied to the publish transition.
need "VideoTimestampCorrectionPublished" "$P/includes/class-vwlb-future-intelligence.php" r13-correction-publication-event

# R14 — arbitrary annotation metadata is reviewer-only.
need "can_internal" "$P/includes/class-vwlb-future-intelligence.php" r14-annotation-metadata-guard

# R15 — auxiliary track delivery fails closed without an explicit public/signed resolver.
need "vwlb_public_media_track_ref', ''" "$P/includes/class-vwlb-future-intelligence.php" r15-track-resolver-fail-closed

# R16 — watermark grants are non-cacheable stateful POST operations.
need "/grant','POST','watermark_grant'" "$P/includes/class-vwlb-future-rest.php" r16-watermark-post
need "private, no-store" "$P/includes/class-vwlb-future-rest.php" r16-watermark-no-store

# R17 — live viewer state containing delivery refs is private/no-store.
need "media_tracks" "$P/includes/class-vwlb-rest.php" r17-live-track-state
need "Cache-Control','private, no-store" "$P/includes/class-vwlb-rest.php" r17-live-no-store

# R18 — automatic guest expiry is versioned, audited and emitted as a lifecycle fact.
need "BroadcastGuestExpired" "$P/includes/class-vwlb-future-intelligence.php" r18-guest-expired-event
need "delegation_ttl" "$P/includes/class-vwlb-future-intelligence.php" r18-guest-expiry-audit

# R19 — existing guest delegation updates require the caller-observed version.
need "Guest delegation changed. Refresh and submit its current version." "$P/includes/class-vwlb-future-intelligence.php" r19-guest-client-version
