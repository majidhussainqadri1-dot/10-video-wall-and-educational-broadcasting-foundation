#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
LEGACY="$ROOT/video-wall/video-wall.php"
fail(){ echo "FAIL r81-r100: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
reject(){ ! grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R81
need "Plugin Name: Video Wall and Live Broadcasting" "$P/video-wall-and-live-broadcasting.php" r81-canonical-plugin
need "Canonical runtime: ../video-wall-and-live-broadcasting/" "$LEGACY" r81-archive-marker
reject "Plugin Name:" "$LEGACY" r81-no-legacy-plugin-header
reject "register_activation_hook" "$LEGACY" r81-no-legacy-activation
reject "add_action( 'plugins_loaded'" "$LEGACY" r81-no-legacy-runtime
need 'SOURCE="$ROOT/video-wall-and-live-broadcasting"' "$ROOT/tools/build-package.sh" r81-package-canonical-source
# R85
need "vwlb_frontend_state_unreadable" "$P/includes/class-vwlb-plugin.php" r85-unavailable-error
need "VWLB_Repository::read_failed()" "$P/includes/class-vwlb-plugin.php" r85-repository-failure
need "\$wpdb->last_error=''" "$P/includes/class-vwlb-plugin.php" r85-channel-db-reset
need "status_header(503)" "$P/includes/class-vwlb-plugin.php" r85-http-503
need "vwlb_route_unavailable" "$P/templates/route.php" r85-unavailable-template
need "Video service temporarily unavailable" "$P/templates/route.php" r85-user-state
# R86
need "anonymized_subject_id" "$P/includes/class-vwlb-privacy.php" r86-surrogate-helper
need "array('live_attendees','user_id',array('user_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-live-surrogate
need "array('creator_metrics_daily','owner_id',array('owner_id'=>\$anon_uid" "$P/includes/class-vwlb-privacy.php" r86-metrics-surrogate
reject "array('live_attendees','user_id',array('user_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-live-zero
reject "array('creator_metrics_daily','owner_id',array('owner_id'=>0" "$P/includes/class-vwlb-privacy.php" r86-no-metrics-zero
# R91
need "'unlisted'===\$visibility" "$P/includes/class-vwlb-security.php" r91-unlisted-explicit
need "vwlb_unlisted_access_authorized" "$P/includes/class-vwlb-security.php" r91-unlisted-hook
need "VWLB_R91_Unlisted_Access_Guard::register" "$P/video-wall-and-live-broadcasting.php" r91-guard
# R94
need "VWLB_R94_Live_External_Uncertainty_Guard::register" "$P/video-wall-and-live-broadcasting.php" r94-guard
need "vwlb_provider_emergency_end_reconcile_required" "$P/includes/class-vwlb-r94-live-external-uncertainty-guard.php" r94-uncertainty
# R97
need "VWLB_R97_Privacy_Storage_Erasure_Guard::register" "$P/video-wall-and-live-broadcasting.php" r97-guard
need "WHERE owner_id=%d ORDER BY id ASC LIMIT %d" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-all-status
need "LOCK_EX|LOCK_NB" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-lock
need "VWLB_R50_Privacy_Proof::erase" "$P/includes/class-vwlb-r97-privacy-storage-erasure-guard.php" r97-proof-chain
# R100 — current immutable release/package identity and 20-round traceability closure.
need "Version: 1.2.11-rc1" "$P/video-wall-and-live-broadcasting.php" r100-version
need "define( 'VWLB_VERSION', '1.2.11-rc1' );" "$P/video-wall-and-live-broadcasting.php" r100-constant
need "Stable tag: 1.2.11-rc1" "$P/readme.txt" r100-stable
need "video-wall-and-live-broadcasting-1.2.11-rc1.zip" "$ROOT/.github/workflows/file10-release.yml" r100-workflow-package
need "file10-video-wall-live-1.2.11-rc1" "$ROOT/.github/workflows/file10-release.yml" r100-workflow-artifact
need "video-wall-and-live-broadcasting-1.2.11-rc1.zip" "$ROOT/tools/build-package.sh" r100-builder
need "CURRENT_VERSION='1.2.11-rc1'" "$ROOT/tests/run-all.sh" r100-suite-version
need "# File 10 Release Candidate Manifest — 1.2.11-rc1" "$ROOT/MANIFEST.md" r100-manifest-version
need 'Review boundary: sequential cycle rounds `R81–R100` completed at repository source-review level.' "$ROOT/MANIFEST.md" r100-manifest-boundary
need 'R81, R84, R85, R86, R91, R94, R97, R100' "$ROOT/MANIFEST.md" r100-defect-ledger
need '"version": "1.2.11-rc1"' "$ROOT/SBOM-1.2.11-rc1.json" r100-sbom-version
need "R81-R100 sequential corrective cycle" "$ROOT/SBOM-1.2.11-rc1.json" r100-sbom-boundary
printf '%s\n' 'File 10 R81-R100 sequential contracts PASS'
